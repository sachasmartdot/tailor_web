<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cards.php';

use App\Data\CatalogueRepository;

/*
 * The home page shows real garments and real tailors from Firestore.
 * We have no view counts, ratings or sales figures to rank them by, so the
 * only honest ordering is "newest first". The page says so.
 */
$garments = [];
$tailors  = [];
$loadFailed = false;

try {
    $garments = CatalogueRepository::getLatest(8);
    $tailors  = CatalogueRepository::getTailors();
} catch (\Throwable $e) {
    $loadFailed = true;
}

$tailorNames = [];
foreach ($tailors as $t) {
    $tailorNames[$t['id']] = $t['pseudo'];
}
$featuredTailors = array_slice($tailors, 0, 4);

$pageTitle = 'Tailor Platform - Made-to-measure clothing from African tailors';
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">
    <div class="hero__inner">
        <p class="eyebrow">Africa's tailor marketplace</p>
        <h1 class="hero__title">Crafted for you.<span>Made to last.</span></h1>
        <p class="hero__text">
            Discover garments from independent tailors, choose your colour and size,
            and pay with mobile money.
        </p>
        <div class="hero__actions">
            <a href="catalogue.php" class="btn btn-primary btn-lg">Browse garments</a>
            <a href="tailors.php" class="btn btn-light btn-lg">Meet the tailors</a>
        </div>
    </div>
</section>

<div class="page-section home-search-wrap">
    <form class="home-search" action="catalogue.php" method="get" role="search">
        <label for="homeSearch" class="visually-hidden">Search garments</label>
        <input id="homeSearch" name="q" type="search" class="home-search__input"
               placeholder="Search garments, for example &quot;boubou&quot; or &quot;suit&quot;" autocomplete="off">
        <button type="submit" class="btn btn-primary home-search__button">Search</button>
    </form>
</div>

<?php if ($loadFailed): ?>
    <div class="page">
        <div class="alert alert-warning" role="alert">
            We could not load the latest garments and tailors just now. Please try again in a moment.
        </div>
    </div>
<?php endif; ?>

<div class="page page-section">
    <div class="section-head">
        <div>
            <p class="eyebrow">Newly added</p>
            <h2 class="section-title">Fresh from the workshop</h2>
        </div>
        <a href="catalogue.php" class="section-head__link">See all garments &rarr;</a>
    </div>

    <?php if ($garments === [] && !$loadFailed): ?>
        <p class="empty-note">No garments yet. The first pieces will appear here as soon as tailors publish them.</p>
    <?php else: ?>
        <div class="garment-grid">
            <?php foreach ($garments as $garment): ?>
                <?php renderGarmentCard($garment, (string) ($tailorNames[$garment['uid'] ?? ''] ?? '')); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="page page-section">
    <div class="section-head">
        <div>
            <p class="eyebrow">Newly joined</p>
            <h2 class="section-title">Tailors to meet</h2>
        </div>
        <a href="tailors.php" class="section-head__link">See all tailors &rarr;</a>
    </div>

    <?php if ($featuredTailors === [] && !$loadFailed): ?>
        <p class="empty-note">No tailors yet. The first workshops will appear here as they join.</p>
    <?php else: ?>
        <div class="atelier-grid">
            <?php foreach ($featuredTailors as $tailor): ?>
                <?php renderAtelierCard($tailor); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>