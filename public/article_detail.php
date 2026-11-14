<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Data\CatalogueRepository;
use App\Support\CartService;
use App\Support\SizeGuide;

$id = (string) ($_GET['id'] ?? '');

if ($id === '') {
    header('Location: catalogue.php');
    exit;
}

$model = CatalogueRepository::findById($id);

if ($model === null) {
    header('Location: catalogue.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| What the customer can choose
|--------------------------------------------------------------------------
*/

$colours = [
    'Black' => '#111111',
    'White' => '#ffffff',
    'Beige' => '#d8c3a5',
    'Brown' => '#795548',
    'Blue'  => '#3f51b5',
    'Red'   => '#b71c1c',
    'Green' => '#4f6f52',
];

// Older garments have no category or type. They still work: the guide
// falls back to showing both the men's and the women's reading.
$category    = (string) ($model['category'] ?? '');
$garmentType = (string) ($model['garment_type'] ?? '');
$sizeNote    = trim((string) ($model['size_note'] ?? ''));

$sizes     = SizeGuide::sizesFor($category);
$sizeGuide = SizeGuide::describe($category, $garmentType);

// The tailor's name, for the "By ..." line
$tailorName = '';
$tailorUid  = (string) ($model['uid'] ?? '');
if ($tailorUid !== '') {
    try {
        $tailorDoc = $firebaseDb->collection('users')->document($tailorUid)->snapshot();
        if ($tailorDoc->exists()) {
            $tailorName = (string) ($tailorDoc->data()['pseudo'] ?? '');
        }
    } catch (\Throwable $e) {
        // The page works fine without the name
    }
}

$error         = '';
$chosenColour  = '';
$chosenSize    = '';
$chosenSystem  = '';

/*
|--------------------------------------------------------------------------
| Add to cart
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chosenColour = trim((string) ($_POST['couleur'] ?? ''));
    $chosenSize   = trim((string) ($_POST['taille'] ?? ''));
    $chosenSystem = strtoupper(trim((string) ($_POST['size_reference'] ?? '')));

    if (!array_key_exists($chosenColour, $colours)) {
        $error = 'Please choose a colour.';
    } elseif (!in_array($chosenSize, $sizes, true)) {
        $error = 'Please choose a size.';
    } else {
        // The equivalent is worked out here on the server, never taken from the browser.
        $selection = SizeGuide::selection($category, $garmentType, $chosenSize, $chosenSystem);

        if ($selection === null) {
            $error = 'Please choose a sizing system so we can show what your size means there.';
        } else {
            CartService::add($model, $chosenColour, $chosenSize, $selection);

            header('Location: cart.php');
            exit;
        }
    }
}

$title     = (string) ($model['title'] ?? 'Garment');
$pageTitle = $title . ' - Tailor Platform';

$labels = array_filter([
    SizeGuide::categoryLabel($category),
    SizeGuide::typeLabel($garmentType),
]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="article-detail-page">
    <div class="article-detail-wrapper">
        <a href="catalogue.php" class="back-link">&larr; Back to garments</a>

        <div class="article-detail-card">
            <div class="row g-0">

                <!-- Photo -->
                <div class="col-lg-6">
                    <?php if (!empty($model['image'])): ?>
                        <img src="<?= e((string) $model['image']) ?>" alt="<?= e($title) ?>" class="article-detail-image">
                    <?php else: ?>
                        <div class="article-detail-image article-detail-image--empty">
                            <span>No photo yet</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Details and choices -->
                <div class="col-lg-6">
                    <div class="article-detail-content">

                        <p class="article-detail-label">
                            <?= $labels !== [] ? e(implode(' · ', $labels)) : 'Made to measure' ?>
                        </p>

                        <h1 class="article-detail-title"><?= e($title) ?></h1>

                        <?php if ($tailorName !== ''): ?>
                            <p class="article-detail-tailor">
                                By <a href="tailor_profile.php?uid=<?= e(rawurlencode($tailorUid)) ?>"><?= e($tailorName) ?></a>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($model['description'])): ?>
                            <p class="article-detail-description"><?= nl2br(e((string) $model['description'])) ?></p>
                        <?php endif; ?>

                        <p class="article-detail-price"><?= e(formatPrice((float) ($model['prix'] ?? 0))) ?></p>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                        <?php endif; ?>

                        <form method="post" id="customizeForm">

                            <!-- Colour -->
                            <div class="customization-section">
                                <div class="customization-title">Colour</div>
                                <div class="color-options">
                                    <?php foreach ($colours as $name => $code): ?>
                                        <label class="color-option">
                                            <input type="radio" name="couleur" value="<?= e($name) ?>" required
                                                   <?= $chosenColour === $name ? 'checked' : '' ?>>
                                            <span class="color-swatch" style="background-color: <?= e($code) ?>;"></span>
                                            <span class="visually-hidden"><?= e($name) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Size -->
                            <div class="customization-section">
                                <div class="customization-title">Size</div>
                                <div class="size-options">
                                    <?php foreach ($sizes as $size): ?>
                                        <label class="size-option">
                                            <input type="radio" name="taille" value="<?= e($size) ?>" required
                                                   <?= $chosenSize === $size ? 'checked' : '' ?>>
                                            <span class="size-button"><?= e($size) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Sizing system and equivalent -->
                            <div class="customization-section">
                                <?php if ($sizeGuide['available']): ?>
                                    <div class="customization-title">Sizing system</div>
                                    <p class="customization-hint">
                                        Pick the system you are used to and we will show what your size means there.
                                    </p>
                                    <div class="size-options reference-options">
                                        <?php foreach (SizeGuide::REFERENCES as $code => $fullName): ?>
                                            <label class="size-option">
                                                <input type="radio" name="size_reference" value="<?= e($code) ?>" required
                                                       <?= $chosenSystem === $code ? 'checked' : '' ?>>
                                                <span class="size-button" title="<?= e($fullName) ?>">
                                                    <?= e($code) ?>
                                                    <small><?= e(SizeGuide::REFERENCE_SHORT[$code]) ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="size-equivalence" id="sizeEquivalence" aria-live="polite">
                                        Choose a size and a sizing system to see the equivalent.
                                    </p>
                                    <p class="customization-hint"><?= e($sizeGuide['note']) ?></p>
                                <?php else: ?>
                                    <p class="size-equivalence is-muted"><?= e($sizeGuide['reason']) ?></p>
                                <?php endif; ?>

                                <?php if ($sizeNote !== ''): ?>
                                    <div class="size-note">
                                        <strong>From the tailor:</strong>
                                        <?= nl2br(e($sizeNote)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="add-to-cart-button">Add to cart</button>

                            <p class="customization-note">
                                Your colour, size and sizing system are saved with your order,
                                so your tailor sees exactly what you chose.
                            </p>
                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php if ($sizeGuide['available']): ?>
<script>
    // Shows what the chosen size means in the chosen sizing system.
    // The server works the same thing out again when the form is sent.
    (function () {
        var guide = <?= json_encode($sizeGuide, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
        var names = <?= json_encode(SizeGuide::REFERENCE_SHORT, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var form = document.getElementById('customizeForm');
        var out = document.getElementById('sizeEquivalence');

        if (!form || !out) {
            return;
        }

        function chosen(name) {
            var input = form.querySelector('input[name="' + name + '"]:checked');
            return input ? input.value : '';
        }

        function update() {
            var size = chosen('taille');
            var system = chosen('size_reference');

            if (size === '') {
                out.textContent = 'Choose a size and a sizing system to see the equivalent.';
            } else if (system === '') {
                out.textContent = 'You picked ' + size + '. Now choose a sizing system.';
            } else {
                var row = guide.rows[size];
                var value = row ? row[system] : '';
                out.textContent = value
                    ? 'You picked ' + size + '. In ' + names[system] + ' sizing, that is usually ' + value + '.'
                    : 'There is no equivalent to show for this size.';
            }
        }

        form.addEventListener('change', update);
        update();
    })();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>