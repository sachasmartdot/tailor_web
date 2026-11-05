<?php
declare(strict_types=1);

// Page : cancel.php (dans public/)
// Rôle : URL d'annulation. Le client revient ici s'il annule le paiement chez Aangaraa Pay.
//        Son panier n'est pas touché.

require_once __DIR__ . '/../includes/bootstrap.php';

unset($_SESSION['paiement']);

$pageTitle = 'Paiement annulé - Tailor Platform';
require __DIR__ . '/../includes/header.php';
?>
<div class='container py-5'>
  <h1 class='h4 mb-3'>Paiement annulé</h1>
  <p>Vous avez annulé le paiement. Votre panier est conservé.</p>
  <a href='cart.php' class='btn btn-primary'>Retour au panier</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
