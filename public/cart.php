<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Data\PaymentRepository;
use App\Support\CartService;
use App\Support\SizeGuide;

// If the payment was confirmed in the background while the customer was away, empty the cart now
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
        $error = 'Please sign in to place your order (use the Sign in button at the top of the page).';
    } elseif (CartService::count() > 0) {
        try {
            require __DIR__ . '/checkout.php';
        } catch (\Throwable $e) {
            $error = 'We could not place your order: ' . $e->getMessage();
        }
    }
}

$items = CartService::getItems();

$pageTitle = 'My cart - Tailor Platform';

require __DIR__ . '/../includes/header.php';
?>

<div class="page page--narrow">
    <a href="catalogue.php" class="back-link">&larr; Keep browsing</a>

    <h1 class="page-title">My cart <span class="page-title__count">(<?= CartService::count() ?>)</span></h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <div class="empty-state">
            <p>Your cart is empty for now.</p>
            <a href="catalogue.php" class="btn btn-primary">Browse garments</a>
        </div>
    <?php else: ?>

        <div class="cart-list">
            <?php foreach ($items as $item): ?>
                <?php $sizeLine = SizeGuide::summary($item); ?>
                <div class="cart-item">
                    <div class="cart-item__media">
                        <?php if (!empty($item['image'])): ?>
                            <img src="<?= e((string) $item['image']) ?>" alt="<?= e((string) ($item['title'] ?? 'Garment')) ?>" class="cart-item-image">
                        <?php endif; ?>
                    </div>

                    <div class="cart-item__info">
                        <div class="cart-item-title"><?= e((string) ($item['title'] ?? 'Garment')) ?></div>

                        <div class="cart-item-details">
                            <?php if (!empty($item['couleur'])): ?>
                                <div><strong>Colour:</strong> <?= e((string) $item['couleur']) ?></div>
                            <?php endif; ?>

                            <?php if ($sizeLine !== ''): ?>
                                <div><strong>Size:</strong> <?= e($sizeLine) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cart-item-price"><?= e(formatPrice((float) ($item['prix'] ?? 0))) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="total-row">
            <span class="total-row__label">Total</span>
            <span class="total-row__amount"><?= e(formatPrice(CartService::total())) ?></span>
        </div>

        <form method="post" class="checkout-form">
            <input type="hidden" name="action" value="commander">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

            <label class="form-label fw-semibold" for="telephone">Phone number to charge</label>
            <input type="tel" id="telephone" name="telephone" class="form-control checkout-form__phone" placeholder="6XXXXXXXX"
                   inputmode="tel" autocomplete="tel-national" required
                   onkeydown="if (event.key === 'Enter') { event.preventDefault(); }">
            <p class="form-text">Cameroon mobile number, 9 digits, starting with 6.</p>

            <p class="mb-2 fw-semibold">Pay with:</p>
            <div class="checkout-form__buttons">
                <button type="submit" name="operateur" value="mtn" class="btn btn-momo">MTN Mobile Money</button>
                <button type="submit" name="operateur" value="orange" class="btn btn-orange">Orange Money</button>
            </div>
        </form>

    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>