<?php
declare(strict_types=1);

// Webhook Aangaraa Pay : aucune session utilisateur n'est requise (c'est leur serveur qui appelle).
// Règle : on NE CROIT PAS le statut reçu. La notification sert seulement de signal :
// on retrouve le paiement, puis on redemande son état à Aangaraa Pay avec NOTRE payToken
// (celui enregistré par checkout.php). Rejouer le webhook ne change rien (idempotent).

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/aangaraa.php';

use App\Data\PaymentRepository;

$brut    = file_get_contents('php://input') ?: '';
$donnees = json_decode($brut, true);
if (!is_array($donnees)) {
    $donnees = $_POST ?: $_GET;
}

$transactionId = (string) ($donnees['transaction_id'] ?? '');
$payTokenRecu  = (string) ($donnees['paytoken'] ?? $donnees['payToken'] ?? '');

if ($transactionId === '' && $payTokenRecu === '') {
    http_response_code(400);
    echo 'transaction_id manquant';
    exit;
}

// Notre paiement : par transaction_id, sinon par payToken
$payment = $transactionId !== '' ? PaymentRepository::find($transactionId) : null;
if ($payment === null && $payTokenRecu !== '') {
    $payment = PaymentRepository::findByPayToken($payTokenRecu);
}
if ($payment === null) {
    http_response_code(404);
    echo 'transaction inconnue';
    exit;
}

$transactionId = (string) $payment['transaction_id'];
$payToken      = (string) ($payment['pay_token'] ?? '');
if ($payToken === '') {
    http_response_code(409);          // checkout.php n'a pas encore fini d'enregistrer le paiement
    echo 'paiement pas encore prêt';
    exit;
}

// Vérification auprès d'Aangaraa Pay (le statut du webhook n'est jamais appliqué tel quel)
try {
    $verification = appelAangaraa('/aangaraa_check_status', ['payToken' => $payToken, 'app_key' => AANGARAA_KEY]);
} catch (Throwable $e) {
    http_response_code(503);          // panne temporaire : callback.php vérifiera aussi
    echo 'verification impossible';
    exit;
}

$statut = PaymentRepository::lireStatut(is_array($verification) ? $verification : null);
if ($statut === '') {
    $statut = 'PENDING';
}
$gatewayTxnId = (string) ($donnees['txnid'] ?? '');     // identifiant côté Aangaraa : simple information
$reason = (string) ($verification['details']['reason'] ?? $verification['data']['details']['reason'] ?? $verification['message'] ?? '');

PaymentRepository::applyGatewayStatus($transactionId, $statut, $gatewayTxnId, $reason);

$dossier = __DIR__ . '/../storage/payments';
if (!is_dir($dossier)) {
    mkdir($dossier, 0750, true);
}
file_put_contents(
    $dossier . '/notifications.log',
    date('c') . ' ' . json_encode(['transaction_id' => $transactionId, 'recu' => $donnees['status'] ?? '', 'verifie' => $statut, 'txnid' => $gatewayTxnId], JSON_UNESCAPED_UNICODE) . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

http_response_code(200);
echo 'OK';
