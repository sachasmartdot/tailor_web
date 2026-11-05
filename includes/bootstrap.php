<?php
	declare(strict_types=1);
	
	// le lecteur de classe avant session_start() sinon une errreur __PHP_Incomplete_Class
	require_once __DIR__ . '/../vendor/autoload.php';
	
	// on peut debuter la session
	if(session_status() !== PHP_SESSION_ACTIVE){
		session_start();
	}
	
	// Firebase en local
	// pour la production: local si APP_ENV=local (a ajouter dans lancer_local.sh), sinon production
	$useEmulator = (getenv('APP_ENV') === 'local');
	// pour voir les erreurs
	if ($useEmulator) {
		ini_set('display_errors', '1');
		error_reporting(E_ALL);
	}
	
	use Kreait\Firebase\Factory;
	
	// SOLUTION : On informe explicitement les composants Google Cloud sous-jacents
	// de l'emplacement de la clé de service avant d'attaquer le bloc try.
	$clePathEnvironment = __DIR__ . '/../cle-firebase.json';
	putenv("GOOGLE_APPLICATION_CREDENTIALS=" . $clePathEnvironment);
	
	// mode en ligne ou local de la base de donnees 
	try{
		if($useEmulator){
			// v7.x necessite badge et token
			$firebaseFactory = require __DIR__ . '/firebase_local.php';
		}
		else{
			$clePath = __DIR__ . '/../cle-firebase.json';
			if(!file_exists($clePath)){
				throw new \Exception("cle Firebase introuvable : $clePath");
			}
			// correctif pour la production: requis par les composants Google Cloud (voir log/log.txt)
			putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $clePath);
			$firebaseFactory = (new Factory)->withServiceAccount($clePath);
		}
		
		// on cofigure REST car gRPC est assez long a configurer et technique
		$firebaseFactory = $firebaseFactory->withFirestoreClientConfig(['transport' => 'rest']);
		
		// les services firebase dont j'ai besoin
		$firebaseAuth = $firebaseFactory->createAuth(); // comptes
		$firebaseDb = $firebaseFactory->createFirestore()->database(); // base de donnees
	}
	catch(\Throwable $e){
		die("Fatal: Erreur d'initialisation Firebase :: " . $e->getMessage());
	}
	
	// Fonction utile
	/**
	*
	* protection XSS de base: Echapper une chaine avant son affiche HTML 
	*
	*/
	function e(string $value): string{
    	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}
	
	/**
	* Formate un prix en FCFA, sans decimales, avec espace comme separateur de milliers.
	*/
	function formatPrice(float $price): string{
		return number_format($price, 0, ',', ' ') . ' FCFA';
	}
	
	/**
	* Libellé, pourcentage d'avancement et couleur Bootstrap d'un statut de commande.
	*/
	function etatCommande(string $statut): array{
		$etats = [
			'en_attente' => ['Initiée', 33, 'secondary'],
			'en_cours' => ['En cours de confection', 66, 'warning'],
			'termine' => ['Terminée', 100, 'success'],
		];
		return $etats[$statut] ?? $etats['en_attente'];
	}
	
	/** User connected (table) or null */
	function currentUser(): ?array {
		return $_SESSION['user'] ?? null;
	}

	/**  */
	
	/**
	* Réserve une page à un rôle ('tailleur' ou 'client').
	* Sortie : l'utilisateur connecté. Sinon, renvoie à l'accueil.
	* À appeler tout en haut de la page, AVANT d'inclure header.php.
	*/
	function requireRole(string $role): array {
		$user = $_SESSION['user'] ?? null;
		if ($user === null || ($user['type'] ?? '') !== $role) {
			header('Location: index.php');
			exit;
		}
		
		return $user;
	}
	

	/** Jeton anti-CSRF de la session (à mettre dans chaque formulaire POST). */
	function csrfToken(): string {
		if (empty($_SESSION['csrf'])) {
			$_SESSION['csrf'] = bin2hex(random_bytes(16));
		}
		return $_SESSION['csrf'];
	}
	
	/** Vrai si le jeton envoyé par le formulaire est celui de la session. */
	function csrfValid(): bool {
		return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''));
	}
	
	/** Message affiché une seule fois après une action (type : success, danger, warning, info). */
	function flash(string $type, string $message): void {
		$_SESSION['flash'] = [$type, $message];
	}
	
	/** Renvoie puis efface le message flash, ou null. */
	function takeFlash(): ?array {
		$f = $_SESSION['flash'] ?? null;
		unset($_SESSION['flash']);
		return $f;
	}
	
?>
