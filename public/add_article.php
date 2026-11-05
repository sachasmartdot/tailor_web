<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Data\CatalogueRepository;
use App\Support\ImageUploader;

$user = requireRole('tailleur');

// renvoie à l'accueil si ce n'est pas un tailleur
$errors = [];
$title = $description = $priceRaw = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$title = trim((string) ($_POST['title'] ?? ''));
	$description = trim((string) ($_POST['description'] ?? ''));
	$priceRaw = trim((string) ($_POST['price'] ?? ''));
	
	if ($title === '') { 
		$errors[] = 'Le titre est obligatoire.'; 
	}
	if ($description === '') { 
		$errors[] = 'La description est obligatoire.';
	}
	if (!is_numeric($priceRaw) || (float) $priceRaw <= 0) {
		$errors[] = 'Le prix doit être un nombre supérieur à 0.';
	}
	if ($errors === []) {
		try {
			// 1. l'image (facultative)
			$imagePath = '';
			
			if (($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
				$imagePath = ImageUploader::save($_FILES['image'], 'assets/users/' . $user['uid'] . '/models/', 'm' . time());
			}

			// 2. le document dans Firestore
			CatalogueRepository::add($user['uid'], $title,$description, (float) $priceRaw, $imagePath);
			header('Location: tailor_profile.php?uid=' .urlencode($user['uid']));
			exit;
		} 
		catch (\Throwable $e) {
			$errors[] = 'Enregistrement impossible : ' . $e->getMessage();
		}
	}
}


$pageTitle = 'Ajouter un modele - Tailor Platform';
require __DIR__ . '/../includes/header.php';
?>

<section>
	<a href="tailor_profile.php" class="back-link mb-3 d-inline-block">&larr; Retour au profil</a>

	<h1 class="page-title mb-4">Ajouter un modele</h1>

	<?php if ($errors !== []): ?>
	  <div class="alert alert-danger">
		<ul class="mb-0">
		  <?php foreach ($errors as $error): ?>
		    <li><?= e($error) ?></li>
		  <?php endforeach; ?>
		</ul>
	  </div>
	<?php endif; ?>

	<form method="post" class="form-card" enctype='multipart/form-data' novalidate>
		<div class="mb-3">
			<label for="title" class="form-label">Titre</label>
			<input
				type="text"
				class="form-control"
				id="title"
				name="title"
				value="<?= e($title) ?>"
				required
			>
		</div>

		<div class="mb-3">
			<label for="description" class="form-label">Description</label>
			<textarea
				class="form-control"
				id="description"
				name="description"
				rows="3"
				required
			><?= e($description) ?></textarea>
		</div>

		<div class="mb-3">
			<label for="price" class="form-label">Prix (FCFA)</label>
			<input
				type="number"
				step="1"
				min="0"
				class="form-control"
				id="price"
				name="price"
				value="<?= e($priceRaw) ?>"
				required
			>
		</div>

		<div class='mb-3'>
			<label for='image' class='form-label'>Photo du modèle</label>
			<input type='file' class='form-control' id='image' name='image' accept='image/*'>
		</div>

	  <div class="d-flex gap-2">
		<button type="submit" class="btn btn-primary">Enregistrer</button>
		<a href="tailor_profile.php" class="btn btn-outline-secondary">Annuler</a>
	  </div>
	</form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
