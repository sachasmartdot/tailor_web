<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Data\OrderRepository;
use App\Data\PaymentRepository;
use App\Support\CartService;

require_once __DIR__ . '/../includes/aangaraa.php';

if (
    !isset($_SESSION['user'])
    || CartService::count() === 0
    || $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Location: cart.php');
    exit;
}

if (!csrfValid()) {
    throw new RuntimeException(
        'Session de paiement expirée. Rechargez la page puis réessayez.'
    );
}

$telephone = preg_replace(
    '/^237/',
    '',
    preg_replace('/\D/', '', (string) ($_POST['telephone'] ?? ''))
);

if (!preg_match('/^6\d{8}$/', $telephone)) {
    throw new RuntimeException(
        'Numéro de téléphone invalide (ex : 6XXXXXXXX).'
    );
}

$choix = (string) ($_POST['operateur'] ?? '');

if ($choix === 'mtn') {
    $operateur = AANGARAA_MTN_OPERATOR;
} elseif ($choix === 'orange') {
    $operateur = AANGARAA_ORANGE_OPERATOR;
} else {
    throw new RuntimeException(
        'Choisissez un opérateur pour payer.'
    );
}

/*
 * Une tentative unique est créée pour ce clic.
 *
 * Les commandes sont créées AVANT l'appel à Aangaraa afin qu'une
 * tentative puisse être auditée même si le navigateur ferme la page
 * ou si le callback n'arrive jamais.
 */
$items = CartService::getItems();
$total = (int) round(CartService::total());

$transactionId =
    'TP'
    . date('YmdHis')
    . random_int(1000, 9999);

$orderIds = OrderRepository::createPendingFromCart(
    $_SESSION['user'],
    $items,
    $transactionId
);

PaymentRepository::create([
    'transaction_id' => $transactionId,
    'client_uid' => (string) $_SESSION['user']['uid'],
    'amount' => $total,
    'operator' => $operateur,
    'phone_number' => $telephone,
    'order_ids' => $orderIds,
    'status' => 'PENDING',
]);

$payload = [
    'phone_number'   => $telephone,
    'amount'         => (string) $total,
    'description'    => 'Commande Tailor Platform',
    'app_key'        => AANGARAA_KEY,
    'transaction_id' => $transactionId,
    'return_url'     =>
        AANGARAA_SITE
        . '/callback.php?transaction_id='
        . rawurlencode($transactionId),
    'notify_url'     => AANGARAA_SITE . '/notify.php',
    'operator'       => $operateur,
    'devise_id'      => 'XAF',
];

$ch = curl_init(
    AANGARAA_API . '/no_redirect/payment'
);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,

    // Temps maximum pour établir la connexion.
    CURLOPT_CONNECTTIMEOUT => 10,

    // Temps maximum total de la requête.
    CURLOPT_TIMEOUT => 60,

    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],

    CURLOPT_POSTFIELDS => json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
    ),
]);

$reponse = curl_exec($ch);
$erreurCurl = curl_error($ch);
$httpCode = (int) curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

curl_close($ch);

/*
 * ---------------------------------------------------------
 * CAS 1 : erreur réseau / timeout côté PHP-cURL
 * ---------------------------------------------------------
 *
 * IMPORTANT :
 * Une absence de réponse ne signifie PAS que le paiement
 * a été refusé.
 *
 * Aangaraa peut avoir reçu la requête alors que notre serveur
 * n'a pas reçu sa réponse.
 */
if ($reponse === false) {

    PaymentRepository::applyGatewayStatus(
        $transactionId,
        'PENDING',
        '',
        'Aucune réponse reçue d\'Aangaraa Pay : '
        . $erreurCurl
    );

    throw new RuntimeException(
        'Le service de paiement n\'a pas répondu à temps. '
        . 'Votre paiement n\'est pas déclaré comme refusé. '
        . 'La transaction est conservée pour vérification.'
    );
}

/*
 * ---------------------------------------------------------
 * Décodage de la réponse
 * ---------------------------------------------------------
 */
$res = json_decode($reponse, true);

if (!is_array($res)) {

    PaymentRepository::applyGatewayStatus(
        $transactionId,
        'PENDING',
        '',
        'Réponse JSON invalide de Aangaraa Pay'
    );

    throw new RuntimeException(
        'Aangaraa Pay n\'a pas fourni une réponse exploitable. '
        . 'La transaction reste en vérification.'
    );
}

/*
 * ---------------------------------------------------------
 * CAS 2 : HTTP 504
 * ---------------------------------------------------------
 *
 * 504 = Gateway Timeout.
 *
 * Ce code NE signifie PAS que le paiement est forcément refusé.
 *
 * On conserve donc PENDING.
 */
if (
    $httpCode === 504
    || (int) ($res['statusCode'] ?? 0) === 504
) {

    $message = (string) (
        $res['message']
        ?? 'Aangaraa Pay n\'a pas répondu à temps.'
    );

    PaymentRepository::applyGatewayStatus(
        $transactionId,
        'PENDING',
        '',
        'HTTP 504 : ' . $message
    );

    throw new RuntimeException(
        'Le service de paiement n\'a pas répondu à temps (504). '
        . 'Le paiement n\'est pas déclaré comme refusé. '
        . 'La transaction reste en attente de vérification.'
    );
}

/*
 * ---------------------------------------------------------
 * CAS 3 : autres erreurs HTTP
 * ---------------------------------------------------------
 *
 * Exemple :
 *
 * 400 = requête incorrecte
 * 401 = authentification
 * 403 = accès refusé
 *
 * Ces erreurs peuvent être considérées comme des échecs
 * de la tentative.
 */
if (
    $httpCode >= 300
    || (int) ($res['statusCode'] ?? 500) >= 300
) {

    $message = (string) (
        $res['message']
        ?? 'Erreur retournée par Aangaraa Pay'
    );

    PaymentRepository::applyGatewayStatus(
        $transactionId,
        'FAILED',
        '',
        $message
    );

    throw new RuntimeException(
        $message
        . ' La tentative est enregistrée.'
    );
}

/*
 * ---------------------------------------------------------
 * Récupération du payToken
 * ---------------------------------------------------------
 */
$payToken = (string) (
    $res['data']['payToken']
    ?? $res['data']['pay_token']
    ?? $res['data']['orange_response']['data']['payToken']
    ?? ''
);

if ($payToken === '') {

    /*
     * Même ici, on évite de déclarer automatiquement FAILED.
     *
     * L'absence de payToken peut venir d'une réponse inhabituelle
     * du service.
     */
    PaymentRepository::applyGatewayStatus(
        $transactionId,
        'PENDING',
        '',
        'Aangaraa n\'a pas fourni de payToken.'
    );

    throw new RuntimeException(
        'Aangaraa n\'a pas fourni de référence de suivi du paiement. '
        . 'La transaction reste en attente de vérification.'
    );
}

/*
 * ---------------------------------------------------------
 * Enregistrement du payToken
 * ---------------------------------------------------------
 */
global $firebaseDb;

$firebaseDb
    ->collection('payments')
    ->document($transactionId)
    ->set([
        'pay_token' => $payToken,
        'gateway_response_code' =>
            (int) ($res['statusCode'] ?? $httpCode),
        'updated_at' =>
            \Google\Cloud\Firestore\FieldValue::serverTimestamp(),
    ], [
        'merge' => true,
    ]);

/*
 * Sauvegarde en session pour le callback.
 */
$_SESSION['paiement'] = [
    'reference' => $transactionId,
    'pay_token' => $payToken,
    'montant' => $total,
];

header(
    'Location: callback.php?transaction_id='
    . rawurlencode($transactionId)
);

exit;
