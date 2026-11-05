<?php
declare(strict_types=1);

// Page : tailor_orders.php
// Rôle : le tailleur fait avancer ses commandes (Initiée -> En cours -> Terminée),
//        puis, une fois la réception confirmée par le client, retire l'argent
//        sur son numéro MTN ou Orange (via retraitAangaraa()).

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/aangaraa.php';

use App\Data\OrderRepository;
use App\Support\ImageUploader;

$user = requireRole('tailleur');
$uid  = (string) $user['uid'];

const OPERATEURS_RETRAIT = [
    'mtn'    => ['MTN Mobile Money', AANGARAA_MTN_OPERATOR],
    'orange' => ['Orange Money', AANGARAA_ORANGE_OPERATOR],
];

// Numéro à 9 chiffres commençant par 6 (on accepte +237 / espaces), ou null si invalide
function numeroValide(string $brut): ?string {
    $n = preg_replace('/^237/', '', preg_replace('/\D/', '', $brut));
    return preg_match('/^6\d{8}$/', $n) ? $n : null;
}

// --- Actions (formulaires POST) ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = (string) ($_POST['action'] ?? '');
    $orderId = (string) ($_POST['order_id'] ?? '');

    if (!csrfValid()) {
        flash('danger', 'Session expirée, veuillez réessayer.');
    }

    // 1) Changer l'avancement de la commande
    elseif ($action === 'update_suivi') {
        $order  = OrderRepository::findById($orderId);
        $statut = (string) ($_POST['statut'] ?? '');

        if ($order === null || !OrderRepository::estPayee($order) || ($order['tailor_uid'] ?? '') !== $uid) {
            flash('danger', 'Commande introuvable.');
        } elseif (!empty($order['recu'])) {
            flash('warning', 'Le client a déjà validé cette commande : elle ne peut plus être modifiée.');
        } elseif (!in_array($statut, ['en_attente', 'en_cours', 'termine'], true)) {
            flash('danger', 'Statut invalide.');
        } else {
            try {
                $image = '';
                if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $image = ImageUploader::save($_FILES['image'], 'assets/users/' . $uid . '/suivi/', 'o_' . $orderId . '_' . time());
                }
                OrderRepository::updateSuivi($orderId, $statut, trim((string) ($_POST['note'] ?? '')), $image);
                flash('success', $statut === 'termine'
                    ? 'Commande marquée terminée : le client peut maintenant confirmer la réception.'
                    : 'Avancement mis à jour.');
            } catch (Throwable $ex) {
                flash('danger', $ex->getMessage());
            }
        }
    }

    // 2) Retirer l'argent d'une commande validée par le client
    elseif ($action === 'retrait') {
        $choix     = (string) ($_POST['operateur'] ?? '');
        $telephone = numeroValide((string) ($_POST['telephone'] ?? ''));

        if (!isset(OPERATEURS_RETRAIT[$choix])) {
            flash('danger', 'Choisissez MTN ou Orange pour le retrait.');
        } elseif ($telephone === null) {
            flash('danger', 'Numéro invalide (ex : 6XXXXXXXX).');
        } else {
            $operateur = OPERATEURS_RETRAIT[$choix][1];

            // Verrou en transaction : refuse si ce n'est pas sa commande, si le client n'a pas validé,
            // ou si un retrait existe déjà (empêche le double retrait au double clic).
            $order = OrderRepository::lockRetrait($orderId, $uid, $operateur);

            if ($order === null) {
                flash('danger', "Retrait impossible : la commande doit être validée par le client et ne pas avoir déjà été retirée.");
            } else {
                try {
                    // commande de test (créée sans payer, en local) : on simule, aucun vrai argent ne bouge
                    $res = ($order['paiement_ref'] ?? '') === 'TEST'
                        ? ['etat' => 'effectue', 'ref' => 'TEST']
                        : retraitAangaraa((int) round((float) ($order['total'] ?? 0)), $operateur, $telephone, (string) ($user['pseudo'] ?? 'Tailleur'));
                    OrderRepository::setRetrait($orderId, $res['etat'], $res['ref']);
                    flash($res['etat'] === 'effectue' ? 'success' : 'info', $res['etat'] === 'effectue'
                        ? 'Retrait effectué : l\'argent est envoyé sur le ' . $telephone . '.'
                        : 'Retrait lancé : en attente de confirmation de l\'opérateur (le statut se met à jour ici).');
                } catch (Throwable $ex) {
                    if (str_starts_with($ex->getMessage(), 'Connexion')) {
                        // Réponse inconnue : le retrait a peut-être été fait. On NE réinitialise PAS,
                        // pour éviter de payer deux fois. À vérifier dans le compte Aangaraa Pay.
                        flash('warning', "Connexion perdue pendant le retrait : vérifiez dans votre compte Aangaraa Pay avant de réessayer. La commande reste marquée « en cours ».");
                    } else {
                        // Retrait refusé : on libère la commande pour pouvoir réessayer
                        OrderRepository::setRetrait($orderId, '');
                        flash('danger', 'Retrait refusé : ' . $ex->getMessage());
                    }
                }
            }
        }
    }

    header('Location: tailor_orders.php');
    exit;
}

// --- Affichage ----------------------------------------------------------------------------------
$orders = OrderRepository::getByTailor($uid);

// Retraits "en cours" : on demande à Aangaraa Pay où ils en sont
foreach ($orders as $k => $o) {
    if (($o['retrait'] ?? '') === 'en_cours' && !empty($o['retrait_ref']) && !empty($o['retrait_operateur'])) {
        try {
            $etat = statutRetraitAangaraa((string) $o['retrait_ref'], (string) $o['retrait_operateur']);
            if ($etat === 'effectue') {
                OrderRepository::setRetrait($o['id'], 'effectue');
                $orders[$k]['retrait'] = 'effectue';
            } elseif ($etat === 'echec') {
                OrderRepository::setRetrait($o['id'], '');   // on peut réessayer
                $orders[$k]['retrait'] = '';
            }
        } catch (Throwable $ex) {
            // pas grave : on revérifiera à la prochaine ouverture de la page
        }
    }
}

$soldes    = OrderRepository::soldes($orders);
$flash     = takeFlash();
$telProfil = preg_replace('/^\+?237/', '', (string) ($user['telephone'] ?? ''));
if (($telProfil === '' || !preg_match('/^6\d{8}$/', $telProfil)) && !empty($user['uid'])) {
    // le numéro du profil n'est pas forcément dans la session : on le lit dans Firestore
    try {
        $fiche     = $firebaseDb->collection('users')->document($uid)->snapshot();
        $telProfil = $fiche->exists() ? preg_replace('/^\+?237/', '', (string) ($fiche->data()['telephone'] ?? '')) : '';
    } catch (Throwable $ex) {
        $telProfil = '';
    }
}

$pageTitle = 'Commandes reçues - Tailor Platform';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">

    <h1 class="mb-4">Commandes reçues</h1>

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
    <?php endif; ?>

    <!-- soldes -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-success h-100"><div class="card-body">
                <div class="small text-muted">Solde retirable</div>
                <div class="fs-4 fw-bold text-success"><?= e(formatPrice($soldes['retirable'])) ?></div>
                <div class="small text-muted">Commandes validées par le client</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card border-warning h-100"><div class="card-body">
                <div class="small text-muted">En cours de traitement</div>
                <div class="fs-4 fw-bold text-warning"><?= e(formatPrice($soldes['en_cours'])) ?></div>
                <div class="small text-muted">Payées, pas encore validées par le client</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="small text-muted">Déjà retiré</div>
                <div class="fs-4 fw-bold"><?= e(formatPrice($soldes['retire'])) ?></div>
                <div class="small text-muted">Retraits faits ou en cours</div>
            </div></div>
        </div>
    </div>

    <?php if (empty($orders)): ?>

        <div class="alert alert-info">Vous n'avez reçu aucune commande pour le moment.</div>

    <?php else: ?>

        <?php foreach ($orders as $order): ?>
            <?php
            [$libelle, $pourcent, $couleur] = etatCommande((string) ($order['statut'] ?? ''));
            $statutActuel = (string) ($order['statut'] ?? 'en_attente');
            $recu         = !empty($order['recu']);
            $retrait      = (string) ($order['retrait'] ?? '');
            $oid          = (string) $order['id'];
            ?>

            <div class="card mb-4 shadow-sm">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-1">Commande #<?= e($oid) ?></h5>
                            <small class="text-muted">
                                <?= e(OrderRepository::date($order)) ?>
                                <?php if (!empty($order['client_pseudo'])): ?> · Client : <?= e((string) $order['client_pseudo']) ?><?php endif; ?>
                            </small>
                        </div>
                        <span class="badge bg-<?= e($couleur) ?>"><?= e($libelle) ?></span>
                    </div>

                    <?php if (!empty($order['items']) && is_array($order['items'])): ?>
                        <h6 class="mb-3">Articles commandés</h6>
                        <?php foreach ($order['items'] as $item): ?>
                            <div class="border rounded p-3 mb-3">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6 class="mb-2"><?= e((string) ($item['title'] ?? 'Modèle')) ?></h6>
                                        <?php if (!empty($item['couleur'])): ?>
                                            <div class="small mb-1"><strong>Couleur :</strong> <?= e((string) $item['couleur']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['taille'])): ?>
                                            <div class="small mb-1"><strong>Taille :</strong> <?= e((string) $item['taille']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <?php if (isset($item['prix'])): ?>
                                            <strong><?= e(formatPrice((float) $item['prix'])) ?></strong>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="border-top pt-3 text-end mb-3">
                        <strong>Total : <?= e(formatPrice((float) ($order['total'] ?? 0))) ?></strong>
                    </div>

                    <!-- A) Avancement : modifiable tant que le client n'a pas validé -->
                    <?php if (!$recu): ?>
                        <form method="post" enctype="multipart/form-data" class="bg-light border rounded p-3 mb-3">
                            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="update_suivi">
                            <input type="hidden" name="order_id" value="<?= e($oid) ?>">

                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">État de la commande</label>
                                    <select name="statut" class="form-select">
                                        <option value="en_attente" <?= $statutActuel === 'en_attente' ? 'selected' : '' ?>>Initiée</option>
                                        <option value="en_cours"   <?= $statutActuel === 'en_cours' ? 'selected' : '' ?>>En cours de confection</option>
                                        <option value="termine"    <?= $statutActuel === 'termine' ? 'selected' : '' ?>>Terminée</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Message pour le client (facultatif)</label>
                                    <input type="text" name="note" class="form-control" maxlength="300"
                                           value="<?= e((string) ($order['suivi_note'] ?? '')) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Photo (facultatif)</label>
                                    <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>

                    <!-- B) Retrait : activé seulement quand le client a confirmé la réception -->
                    <?php if ($retrait === 'effectue'): ?>
                        <span class="badge bg-success fs-6">✓ Retrait effectué</span>
                    <?php elseif ($retrait === 'en_cours'): ?>
                        <span class="badge bg-warning text-dark fs-6">Retrait en cours de traitement…</span>
                    <?php elseif ($recu): ?>
                        <form method="post" class="border border-success rounded p-3"
                              onsubmit="return confirm('Envoyer <?= e(formatPrice((float) ($order['total'] ?? 0))) ?> sur ce numéro ?');">
                            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="retrait">
                            <input type="hidden" name="order_id" value="<?= e($oid) ?>">

                            <div class="fw-bold text-success mb-2">✓ Le client a confirmé la réception : vous pouvez retirer <?= e(formatPrice((float) ($order['total'] ?? 0))) ?> (le prix de la commande).</div>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Numéro à créditer (6XXXXXXXX)</label>
                                    <input type="tel" name="telephone" class="form-control" required
                                           pattern="[0-9 +]{9,16}" value="<?= e($telProfil) ?>"
                                           onkeydown="if (event.key === 'Enter') { event.preventDefault(); }">
                                </div>
                                <div class="col-md-8 d-flex flex-wrap gap-2">
                                    <!-- le bouton cliqué donne l'opérateur (valeur "mtn" ou "orange") -->
                                    <button type="submit" name="operateur" value="mtn" class="btn btn-warning">Retirer <?= e(formatPrice((float) ($order['total'] ?? 0))) ?> sur MTN MoMo</button>
                                    <button type="submit" name="operateur" value="orange" class="btn text-white" style="background:#ff7900">Retirer <?= e(formatPrice((float) ($order['total'] ?? 0))) ?> sur Orange Money</button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary" disabled>Retirer <?= e(formatPrice((float) ($order['total'] ?? 0))) ?></button>
                        <small class="text-muted ms-2">
                            <?= $statutActuel === 'termine'
                                ? 'En attente de la confirmation de réception par le client.'
                                : 'Disponible après « Terminée » puis confirmation du client.' ?>
                        </small>
                    <?php endif; ?>

                </div>
            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<script>
    // mise à jour automatique (ex. le client a confirmé la réception) toutes les 20 s,
    // sauf si vous êtes en train de remplir un formulaire
    var enSaisie = false;
    document.addEventListener('input', function () { enSaisie = true; });
    setInterval(function () { if (!enSaisie) { location.reload(); } }, 20000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
