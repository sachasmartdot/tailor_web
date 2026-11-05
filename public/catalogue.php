<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Data\CatalogueRepository;

$models = CatalogueRepository::getAll();

$pageTitle = 'Catalogue - Tailor Platform';
require __DIR__ . '/../includes/header.php';
?>

<a href="index.php" class="back-link mb-3 d-inline-block">&larr; Retour a l'accueil</a>

<section>
	<h1 class="page-title mb-4">Catalogue</h1>

	<?php if ($models === []): ?>
	  <p class="text-muted">Aucun modele disponible pour le moment.</p>
	<?php else: ?>
		<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
			<?php foreach ($models as $m): ?>
				<?php $img = ($m['image'] ?? '') !== '' ? $m['image'] : 'assets/icones/default-avatar.jpeg'; ?>
				<div class='col'>
					<div class='card h-100 model-card'>
						<img src='<?= e($img) ?>' class='card-img-top' alt='' style='height:220px; object-fit: cover;'>
						<div class='card-body d-flex flex-column'>
							<h2 class='card-title h5'><?= e($m['title'] ?? '') ?></h2>
							<p class='card-text text-muted flex-grow-1'><?= e($m['description'] ?? '') ?></p>
							<p class='price'><?= formatPrice((float) ($m['prix'] ?? 0)) ?></p>
							<a href='article_detail.php?id=<?= e($m['id']) ?>' class='btn btn-primary mt-auto'>Voir</a>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
