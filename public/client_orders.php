<?php
declare(strict_types=1);

// Page : client_orders.php
// Rôle : le client suit ses commandes et confirme la réception quand le tailleur a terminé.
//        Cette confirmation débloque le retrait de l'argent côté tailleur.

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Data\OrderRepository;

$user = requireRole('client');

// --- Action : "J'ai bien reçu ma commande" -------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        flash('danger', 'Session expirée, veuillez réessayer.');
    } elseif (($_POST['action'] ?? '') === 'confirm_reception') {
        // confirmReception vérifie côté serveur : commande du client, statut "termine", pas déjà confirmée
        if (OrderRepository::confirmReception((string) ($_POST['order_id'] ?? ''), (string) $user['uid'])) {
            flash('success', 'Merci ! Réception confirmée : le tailleur peut maintenant retirer son paiement.');
        } else {
            flash('warning', "Impossible de confirmer : le tailleur n'a pas encore marqué la commande comme terminée (ou elle est déjà confirmée).");
        }
    }
    header('Location: client_orders.php');
    exit;
}

$orders = OrderRepository::getByClient((string) $user['uid']);
$flash  = takeFlash();

$pageTitle = 'Mes commandes - Tailor Platform';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">

    <h1 class="mb-4">Mes commandes</h1>

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>

        <div class="alert alert-info">
            Vous n'avez encore aucune commande. Une commande apparaît ici dès que son paiement est confirmé.
            <a href="catalogue.php" class="alert-link">Voir le catalogue</a>
            <?php if ($useEmulator): ?>
                <br>Pour tester le suivi sans payer : <a href="commande_test.php" class="alert-link">créer une commande de test</a>
            <?php endif; ?>
        </div>

    <?php else: ?>

        <?php foreach ($orders as $order): ?>
            <?php
            [$libelle, $pourcent, $couleur] = etatCommande((string) ($order['statut'] ?? ''));
            $termine = ($order['statut'] ?? '') === 'termine';
            $recu    = !empty($order['recu']);
            ?>

            <div class="card mb-4 shadow-sm">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-1">Commande #<?= e((string) ($order['id'] ?? '')) ?></h5>
                            <?php $date = OrderRepository::date($order); ?>
                            <?php if ($date !== ''): ?>
                                <small class="text-muted"><?= e($date) ?></small>
                            <?php endif; ?>
                        </div>
                        <span class="badge bg-<?= e($couleur) ?>"><?= e($libelle) ?></span>
                    </div>

                    <!-- avancement -->
                    <div class="progress mb-3" style="height: 8px;">
                        <div class="progress-bar bg-<?= e($couleur) ?>" role="progressbar"
                             style="width: <?= (int) $pourcent ?>%"
                             aria-valuenow="<?= (int) $pourcent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                    <?php if (!empty($order['suivi_note']) || !empty($order['suivi_image'])): ?>
                        <div class="bg-light border rounded p-3 mb-3">
                            <div class="small text-muted mb-1">Message du tailleur</div>
                            <?php if (!empty($order['suivi_note'])): ?>
                                <div><?= nl2br(e((string) $order['suivi_note'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($order['suivi_image'])): ?>
                                <img src="<?= e((string) $order['suivi_image']) ?>" alt="Photo de l'avancement"
                                     class="img-fluid rounded mt-2" style="max-height: 220px;">
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($order['items']) && is_array($order['items'])): ?>
                        <?php foreach ($order['items'] as $item): ?>
                            <div class="border-top pt-3 mt-3">
                                <strong><?= e((string) ($item['title'] ?? 'Modèle')) ?></strong>
                                <?php if (isset($item['prix'])): ?>
                                    <div class="text-muted"><?= e(formatPrice((float) $item['prix'])) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($item['couleur'])): ?>
                                    <div class="small text-muted mt-1"><strong>Couleur :</strong> <?= e((string) $item['couleur']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($item['taille'])): ?>
                                    <div class="small text-muted"><strong>Taille :</strong> <?= e((string) $item['taille']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="border-top mt-4 pt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">

                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <?php if ($recu): ?>
                                <span class="badge bg-success fs-6">✓ Réception confirmée</span>
                            <?php else: ?>
                                <form method="post" class="m-0"
                                      onsubmit="return confirm('Confirmer que vous avez bien reçu cette commande ? Le tailleur pourra alors retirer son paiement.');">
                                    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="confirm_reception">
                                    <input type="hidden" name="order_id" value="<?= e((string) $order['id']) ?>">
                                    <button type="submit" class="btn btn-success" <?= $termine ? '' : 'disabled' ?>>
                                        J'ai bien reçu ma commande
                                    </button>
                                </form>
                                <?php if (!$termine): ?>
                                    <small class="text-muted">Disponible quand le tailleur aura terminé la commande.</small>
                                <?php endif; ?>
                            <?php endif; ?>

                            <a href="receipt.php?id=<?= e(rawurlencode((string) $order['id'])) ?>" class="btn btn-outline-secondary btn-sm">
                                Télécharger la facture
                            </a>
                        </div>

                        <strong>Total : <?= e(formatPrice((float) ($order['total'] ?? 0))) ?></strong>
                    </div>

                </div>
            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<script>
    // mise à jour automatique : l'avancement fixé par le tailleur apparaît ici sans rien faire (toutes les 20 s)
    setTimeout(function () { location.reload(); }, 20000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
