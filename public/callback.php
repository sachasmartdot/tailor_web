<?php
declare(strict_types=1);

// Page : callback.php (dans public/)
// Rôle : page d'attente après le paiement. Elle demande l'état du paiement à Aangaraa Pay
//        et le note dans le registre des paiements (PaymentRepository). Quand c'est confirmé,
//        les commandes préparées par checkout.php deviennent visibles et la confirmation s'affiche.
//        (Elle ne crée AUCUNE commande : checkout.php les a déjà créées, en attente de paiement.)

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/aangaraa.php';

use App\Data\OrderRepository;
use App\Data\PaymentRepository;
use App\Support\CartService;

$ref     = (string) ($_GET['transaction_id'] ?? $_SESSION['paiement']['reference'] ?? '');
$payment = $ref !== '' ? PaymentRepository::find($ref) : null;

// Il faut un paiement qui appartient au client connecté
if ($payment === null || !isset($_SESSION['user']) || ($payment['client_uid'] ?? '') !== ($_SESSION['user']['uid'] ?? '')) {
    header('Location: cart.php');
    exit;
}

$statut  = strtoupper((string) ($payment['status'] ?? 'PENDING'));
$message = '';

// Pas encore confirmé : on demande l'état à Aangaraa Pay
if ($statut !== 'SUCCESSFUL') {
    $payToken = (string) ($payment['pay_token'] ?? $_GET['payToken'] ?? $ref);

    try {
        $res = appelAangaraa('/aangaraa_check_status', ['payToken' => $payToken, 'app_key' => AANGARAA_KEY]);

        // Journal des vérifications (storage/payments/status.log) : montre ce que répond Aangaraa Pay
        if (!is_dir(__DIR__ . '/../storage/payments')) {
            mkdir(__DIR__ . '/../storage/payments', 0777, true);
        }
        file_put_contents(__DIR__ . '/../storage/payments/status.log', date('c') . ' ' . $ref . ' ' . json_encode($res) . PHP_EOL, FILE_APPEND);

        $lu = PaymentRepository::lireStatut(is_array($res) ? $res : null);
        if ($lu !== '') {
            $raison = (string) ($res['details']['reason'] ?? $res['data']['details']['reason'] ?? $res['message'] ?? '');
            $maj    = PaymentRepository::applyGatewayStatus($ref, $lu, '', $raison);
            if ($maj !== null) {
                $payment = $maj;
            }
            $statut = strtoupper((string) ($payment['status'] ?? $lu));
        }
    } catch (\Throwable $e) {
        $message = $e->getMessage();
    }
}

// Payé : on ouvre la confirmation (les commandes existent déjà, elles sont maintenant visibles)
if ($statut === 'SUCCESSFUL') {
    $ids      = array_values($payment['order_ids'] ?? []);
    $articles = 0;
    foreach ($ids as $id) {
        $o = OrderRepository::findById((string) $id);
        $articles += is_array($o['items'] ?? null) ? count($o['items']) : 0;
    }

    // première fois seulement : on vide le panier et on prépare le téléchargement des factures
    if (($_SESSION['paiement']['reference'] ?? '') === $ref) {
        CartService::clear();
        $_SESSION['dernieres_commandes'] = $ids;
        unset($_SESSION['paiement']);
    }

    header('Location: order_confirmation.php?total=' . rawurlencode((string) ($payment['amount'] ?? 0)) . '&count=' . $articles);
    exit;
}

// Échec définitif : on garde le panier
$echec = in_array($statut, ['FAILED', 'CANCELLED', 'EXPIRED'], true);
if ($echec) {
    unset($_SESSION['paiement']);
}

// Plus de 5 minutes d'attente : on arrête le rafraîchissement automatique
$creeLe = $payment['created_at'] ?? null;
$age    = (is_object($creeLe) && method_exists($creeLe, 'get')) ? time() - $creeLe->get()->getTimestamp() : 0;
$delai  = !$echec && $age > 300;

$pageTitle = 'Paiement - Tailor Platform';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-5" style="max-width: 720px;">

  <?php if ($echec): ?>
    <h1 class="h4 mb-3">Paiement non confirmé</h1>
    <p>Statut : <strong><?= e($statut) ?></strong>. <?= e((string) ($payment['reason'] ?? '')) ?></p>
    <p>Aucun montant n'a été validé. Votre panier est conservé.</p>
    <a href="cart.php" class="btn btn-primary">Retour au panier</a>

  <?php elseif ($delai): ?>
    <h1 class="h4 mb-3">Toujours pas de confirmation</h1>
    <p>Si vous avez validé le paiement sur votre téléphone, patientez un instant puis vérifiez à nouveau.</p>
    <a href="callback.php?transaction_id=<?= e(rawurlencode($ref)) ?>" class="btn btn-primary me-2">Vérifier à nouveau</a>
    <a href="cart.php" class="btn btn-outline-secondary">Retour au panier</a>

  <?php else: ?>
    <div class="text-center">
      <div class="spinner-border mb-3" role="status"></div>
      <h1 class="h4 mb-3">En attente de confirmation</h1>
      <p>Validez la demande de paiement de <strong><?= e(formatPrice((float) ($payment['amount'] ?? 0))) ?></strong>
         sur votre téléphone (<?= e((string) ($payment['phone_number'] ?? '')) ?>).</p>
      <p class="text-muted">Cette page se met à jour toute seule.</p>
      <?php if ($message !== ''): ?><p class="text-muted small"><?= e($message) ?></p><?php endif; ?>
    </div>
    <script>setTimeout(function () { location.reload(); }, 5000);</script>
  <?php endif; ?>

</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
