<?php
declare(strict_types=1);

// Facture d'une commande, en PDF. Réservée au client qui a passé la commande (commande payée seulement).

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Data\OrderRepository;
use App\Support\ReceiptPdf;

$user = requireRole('client');

$order = OrderRepository::findById((string) ($_GET['id'] ?? ''));
if ($order === null || !OrderRepository::estPayee($order) || ($order['client_uid'] ?? '') !== $user['uid']) {
    header('Location: client_orders.php');
    exit;
}

$fiche = $firebaseDb->collection('users')->document((string) ($order['tailor_uid'] ?? ''))->snapshot();
$tailleur = $fiche->exists() ? ($fiche->data()['pseudo'] ?? 'Tailleur') : 'Tailleur';

$lignes = [
    'Facture de commande',
    'Numéro de commande : ' . $order['id'],
    'Date : ' . OrderRepository::date($order),
    'Référence du paiement : ' . ($order['paiement_ref'] ?? ''),
    'Client : ' . ($order['client_pseudo'] ?? ''),
    'Tailleur : ' . $tailleur,
    '',
    'Articles :',
];
foreach (($order['items'] ?? []) as $item) {
    $lignes[] = '   ' . ($item['title'] ?? '') . ' : ' . formatPrice((float) ($item['prix'] ?? 0));
}
$lignes[] = '';
$lignes[] = 'Total payé : ' . formatPrice((float) ($order['total'] ?? 0));
$lignes[] = 'Avancement : ' . etatCommande($order['statut'] ?? '')[0];
$lignes[] = 'Réception confirmée par le client : ' . (!empty($order['recu']) ? 'oui' : 'non');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="facture-' . $order['id'] . '.pdf"');
echo ReceiptPdf::build($lignes);
