<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$total = isset($_GET['total']) && is_numeric($_GET['total']) ? (float) $_GET['total'] : 0.0;
$count = isset($_GET['count']) && is_numeric($_GET['count']) ? (int) $_GET['count'] : 0;

// commandes qui viennent d'être créées (posées par callback.php). On les efface :
// le téléchargement automatique ne se fait qu'une seule fois.
$ids = $_SESSION['dernieres_commandes'] ?? [];
unset($_SESSION['dernieres_commandes']);

$pageTitle = 'Commande confirmee - Tailor Platform';
require __DIR__ . '/../includes/header.php';
?>

<section class="confirmation-card text-center">
  <div class="confirmation-icon">&#10003;</div>
  <h1 class="page-title">Commande confirmee</h1>
  <p class="text-muted">
    Merci pour votre commande de <?= $count ?> modele<?= $count > 1 ? 's' : '' ?>.
  </p>
  <p class="price fs-4">Total paye : <?= formatPrice($total) ?></p>
  <?php if ($ids): ?>
    <p class="mt-3 mb-1">Vos factures se téléchargent automatiquement (une par tailleur).</p>
    <?php foreach (array_values($ids) as $i => $id): ?>
      <div><a href="receipt.php?id=<?= e(rawurlencode((string) $id)) ?>">Télécharger la facture n° <?= $i + 1 ?></a></div>
    <?php endforeach; ?>
  <?php endif; ?>
  <a href="client_orders.php" class="btn btn-primary btn-lg mt-3">Suivre mes commandes</a>
  <a href="catalogue.php" class="btn btn-outline-secondary btn-lg mt-3">Retour au catalogue</a>
</section>

<?php if ($ids): ?>
<script>
  // téléchargement automatique : une facture par commande, à 1 seconde d'intervalle
  var recus = <?= json_encode(array_map(fn ($id) => 'receipt.php?id=' . rawurlencode((string) $id), array_values($ids)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  recus.forEach(function (url, i) {
    setTimeout(function () {
      var cadre = document.createElement('iframe');
      cadre.style.display = 'none';
      cadre.src = url;
      document.body.appendChild(cadre);
    }, i * 1000);
  });
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
