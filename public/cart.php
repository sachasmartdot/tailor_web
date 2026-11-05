<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Data\PaymentRepository;
use App\Support\CartService;

// Paiement confirmé en arrière-plan (webhook) pendant que le client était parti : on vide le panier
if (!empty($_SESSION['paiement']['reference'])) {
	$dejaPaye = PaymentRepository::find((string) $_SESSION['paiement']['reference']);
	if ($dejaPaye !== null && strtoupper((string) ($dejaPaye['status'] ?? '')) === 'SUCCESSFUL') {
		CartService::clear();
		unset($_SESSION['paiement']);
	}
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'commander') {
	if (!isset($_SESSION['user'])) {
		$error = 'Connectez-vous pour commander (bouton Connexion en haut de la page).';
	}
	else if (CartService::count() > 0) {
		try {
			require __DIR__ .'/checkout.php';
			
		} catch (\Throwable $e) {
			$error = 'Commande impossible : ' . $e->getMessage();
		}
	}
}

$items = CartService::getItems();

$pageTitle = 'Mon panier - Tailor Platform';

require __DIR__ . '/../includes/header.php';

?>

<section>
	<a href='catalogue.php' class='back-link mb-3 d-inline-block'>&larr; Retour au catalogue</a>
	<h1 class='page-title mb-4'>Mon panier (<?= CartService::count() ?>)</h1>
	<?php if ($error !== ''): ?>
		<div class='alert alert-danger'><?= e($error) ?></div>
	<?php endif; ?>
	<?php if ($items === []): ?>
		<p class='text-muted'>Votre panier est vide.</p>
		<a href='catalogue.php' class='btn btn-outline-primary'>Voir le catalogue</a>
	<?php else: ?>
		<ul class='list-group mb-4'>


<?php foreach ($items as $item): ?>

    <div class="cart-item">

        <div class="row align-items-center">

            <div class="col-md-2">

                <?php if (!empty($item['image'])): ?>

                    <img
                        src="<?= htmlspecialchars($item['image']) ?>"
                        alt="<?= htmlspecialchars($item['title'] ?? 'Modèle') ?>"
                        class="cart-item-image"
                    >

                <?php endif; ?>

            </div>

            <div class="col-md-6">

                <div class="cart-item-title">
                    <?= htmlspecialchars($item['title'] ?? 'Modèle') ?>
                </div>

                <div class="cart-item-details">

                    <?php if (!empty($item['couleur'])): ?>

                        <div>
                            <strong>Couleur :</strong>
                            <?= htmlspecialchars($item['couleur']) ?>
                        </div>

                    <?php endif; ?>

                    <?php if (!empty($item['taille'])): ?>

                        <div>
                            <strong>Taille :</strong>
                            <?= htmlspecialchars($item['taille']) ?>
                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <div class="col-md-4 text-md-end">

                <div class="cart-item-price">

                    <?= number_format(
                        (float) ($item['prix'] ?? 0),
                        0,
                        ',',
                        ' '
                    ) ?>

                    FCFA

                </div>

            </div>

        </div>

    </div>

<?php endforeach; ?>



		</ul>
		<div class='d-flex justify-content-between align-items-center total-row mb-4'>
			<span class='h5 mb-0'>Total</span>
			<span class='h5 mb-0'><?= formatPrice(CartService::total()) ?></span>
		</div>
		<form method='post'>
			<input type='hidden' name='action' value='commander'>
			<input type='hidden' name='csrf' value='<?= e(csrfToken()) ?>'>
			<label class='form-label fw-semibold' for='telephone'>Numéro de téléphone à débiter</label>
			<input type='tel' id='telephone' name='telephone' class='form-control mb-3' style='max-width:260px' placeholder='6XXXXXXXX' required onkeydown="if (event.key === 'Enter') { event.preventDefault(); }">
			<p class='mb-2 fw-semibold'>Payer avec :</p>
			<button type='submit' name='operateur' value='mtn' class='btn btn-warning btn-lg me-2'>MTN Mobile Money</button>
			<button type='submit' name='operateur' value='orange' class='btn btn-primary btn-lg' style='background:#ff7900;border-color:#ff7900'>Orange Money</button>
		</form>
	<?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
