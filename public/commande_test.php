<?php
declare(strict_types=1);

// Page : commande_test.php   >>> POUR LES TESTS EN LOCAL SEULEMENT <<<
// Rôle : crée une commande SANS payer (référence "TEST") pour voir et essayer tout le suivi :
//        facture, état du tailleur, confirmation du client, retrait (simulé : aucun vrai retrait).
// Elle ne marche que si $useEmulator est à true dans includes/bootstrap.php. Tu peux la supprimer.

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Data\CatalogueRepository;
use App\Data\OrderRepository;

if (!$useEmulator) {
    http_response_code(404);
    exit;
}

$user   = requireRole('client');
$models = CatalogueRepository::getAll();

if ($models === []) {
    flash('warning', "Aucun modèle dans le catalogue : connectez-vous en tailleur et publiez d'abord un modèle.");
    header('Location: client_orders.php');
    exit;
}

// le premier modèle du catalogue
$m = $models[0];
$article = [
    'model_id'   => $m['id'],
    'title'      => $m['title'] ?? 'Modèle',
    'prix'       => (float) ($m['prix'] ?? 0),
    'image'      => $m['image'] ?? '',
    'tailor_uid' => $m['uid'] ?? '',
    'couleur'    => 'Test',
    'taille'     => 'M',
];

$ids = OrderRepository::createPendingFromCart($user, [$article], 'TEST', 'SUCCESSFUL');
$_SESSION['dernieres_commandes'] = $ids;

header('Location: order_confirmation.php?total=' . rawurlencode((string) $article['prix']) . '&count=1');
exit;
