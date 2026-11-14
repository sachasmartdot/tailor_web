<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cards.php';

use App\Data\CatalogueRepository;

$query = trim((string) ($_GET['q'] ?? ''));

$models = [];
$tailorNames = [];
$loadFailed = false;

try {
    $models = CatalogueRepository::getLatest();
    foreach (CatalogueRepository::getTailors() as $t) {
        $tailorNames[$t['id']] = $t['pseudo'];
    }
} catch (\Throwable $e) {
    $loadFailed = true;
}

$totalCount = count($models);

// Simple search on the title and description, ignoring upper and lower case
if ($query !== '') {
    $models = array_values(array_filter($models, static function (array $m) use ($query): bool {
        $text = (string) ($m['title'] ?? '') . ' ' . (string) ($m['description'] ?? '');
        return mb_stripos($text, $query) !== false;
    }));
}

$pageTitle = 'Garments - Tailor Platform';
require __DIR__ . '/../includes/header.php';
?>

<div class="page">
    <a href="index.php" class="back-link">&larr; Back to home</a>

    <div class="section-head">
        <div>
            <p class="eyebrow">The catalogue</p>
            <h1 class="page-title">Garments</h1>
        </div>
        <p class="section-head__count">
            <?= count($models) ?> <?= count($models) === 1 ? 'garment' : 'garments' ?>
        </p>
    </div>

    <form class="catalogue-search" action="catalogue.php" method="get" role="search">
        <label for="catalogueSearch" class="visually-hidden">Search garments</label>
        <input id="catalogueSearch" name="q" type="search" class="form-control"
               value="<?= e($query) ?>" placeholder="Search garments" autocomplete="off">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($query !== ''): ?>
            <a href="catalogue.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($loadFailed): ?>
        <div class="alert alert-warning" role="alert">We could not load the garments just now. Please try again in a moment.</div>
    <?php elseif ($models === []): ?>
        <div class="empty-state">
            <?php if ($query !== '' && $totalCount > 0): ?>
                <p>Nothing matches &ldquo;<?= e($query) ?>&rdquo;. Try another word, or clear the search.</p>
            <?php else: ?>
                <p>No garments yet. The first pieces will appear here as soon as tailors publish them.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="garment-grid">
            <?php foreach ($models as $m): ?>
                <?php renderGarmentCard($m, (string) ($tailorNames[$m['uid'] ?? ''] ?? '')); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>