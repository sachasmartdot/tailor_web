<?php

declare(strict_types=1);


require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'Accueil - Tailor Platform';
require __DIR__ . '/../includes/header.php';
?>

<section class="card-body">
	<div class="tab-content">
		<div class="tab-pane fade show active" id="home-hero">
			<div class="home-hero text-start ">
				<div class="hero-content">
					<p class="mb-4">Africa's Tailor Marketplace</p>
					<h1 class="page-title mb-2">Crafted for you.</h1>
					<h1 class="page-title mb-2">Made to last.</h1>
					<p class="mb-4">Plateforme qui met en relation clients et tailleurs.</p>

					<div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
					  <a href="tailors.php" class="btn btn-primary btn-lg px-4">Explore Tailors</a>
					  <a href="tailor_profile.php" class="btn btn-outline-primary btn-lg px-4">Browse Garments</a>
					</div>
				</div>
			</div>

			<!-- search bar -->
			<div class="position-relative z-1 row justify-content-center p-5" style="background-color: white;">
				<!-- From Uiverse.io by alexruix --> 
				<div class="group col-lg-5">
					<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><g><path d="M21.53 20.47l-3.66-3.66C19.195 15.24 20 13.214 20 11c0-4.97-4.03-9-9-9s-9 4.03-9 9 4.03 9 9 9c2.215 0 4.24-.804 5.808-2.13l3.66 3.66c.147.146.34.22.53.22s.385-.073.53-.22c.295-.293.295-.767.002-1.06zM3.5 11c0-4.135 3.365-7.5 7.5-7.5s7.5 3.365 7.5 7.5-3.365 7.5-7.5 7.5-7.5-3.365-7.5-7.5z"></path></g></svg>
					<input placeholder="Search" type="search" class="input">
				</div>
			</div>
		</div>
		  
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
