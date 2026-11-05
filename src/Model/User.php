<?php

declare(strict_types=1);

namespace App\Model;

use RuntimeException;

/**
 * Represente le tailleur ou le client selon le type.
 * Equivalent de la classe Tailor cote Flutter/Dart.
 */

//use PDO; // on se sert de Firebse 

class User
{
    // un tailleur doit se connecter
    //private PDO $_db;
    
    private string $pseudo;
    private string $password;
    private string $localisation;
    private string $description;
    private string $email;
    private string $telephone;
    private string $type;
    private $photoProfil = [];
    private $photoCouverture = [];


    public function __construct(string $pseudo, string $password, string $localisation, string $description, string $email, string $telephone, string $type, array $photoProfil = [], array $photoCouverture = [])
    {
        $this->pseudo = $pseudo;
        $this->password = $password;
        $this->localisation = $localisation;
        $this->description = $description;
        $this->email = $email;
        $this->telephone = $telephone;
        $this->type = (string) $type;
        
        // images avec url par defaut si vide
		$this->photoProfil = $photoProfil ? $photoProfil : [];
		$this->photoCouverture = $photoCouverture ? $photoCouverture : [];
		

        // $this->setDb($db);
    }

    public function getPseudo(): string
    {
        return $this->pseudo;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getLocalisation(): string
    {
        return $this->localisation;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getType(): string
    {
        return $this->type;
    }
    
    public function getPhotoProfil(): array
    {
        return $this->photoProfil;
    }
    
    public function getPhotoCouverture(): array
    {
        return $this->photoCouverture;
    }

    
    // methodes metier
    
    // inscription dans firebase : compte Auth + fiche dans Firestore
    public function register(): string
    {
        global $firebaseAuth, $firebaseDb;

        // 1. Création du compte Firebase Auth
        $createdUser = $firebaseAuth->createUser([
            'email'       => $this->email,
            'password'    => $this->password,
            'displayName' => $this->pseudo,
            'phoneNumber' => '+237' . $this->telephone,
        ]);
        $userId = $createdUser->uid;

        try {
            // 2. Photo de profil (si fournie)
            $photoPath = '';
            if (!empty($this->photoProfil) && ($this->photoProfil['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $photoPath = $this->savePhoto($userId);
            } else {
                $this->createUserFolders($userId);
            }

            // 3. Fiche de l'utilisateur dans Firestore (collection 'users', id = uid)
            $firebaseDb->collection('users')->document($userId)->set([
                'uid'              => $userId,
                'pseudo'           => $this->pseudo,
                'localisation'     => $this->localisation,
                'description'      => $this->description,
                'email'            => $this->email,
                'telephone'        => '+237' . $this->telephone,
                'type'             => $this->type === 'on' ? 'tailleur' : 'client',
                'photo_profil'     => $photoPath,
                'photo_couverture' => '',
                'created_at'          => new \DateTime()
            ]);
        } catch (\Throwable $e) {
            // Si l'étape 2 ou 3 rate, on annule le compte créé à l'étape 1
            // (sinon l'email serait "pris" sans que le profil existe)
            try { $firebaseAuth->deleteUser($userId); } catch (\Throwable $ignored) {}
            throw $e;
        }

        return $userId;
    }

    // Dossiers de l'utilisateur : public/assets/users/<uid>/profile et /models
    private function createUserFolders(string $userId): string
    {
        $base = __DIR__ . '/../../public/assets/users/' . $userId . '/';
        foreach ([$base . 'profile/', $base . 'models/'] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new RuntimeException("mkdir echoue pour : $dir");
            }
        }
        return $base;
    }

    // Enregistre la photo de profil. Seuls jpg, png et webp sont acceptés.
    private function savePhoto(string $userId): string
    {
        $base = $this->createUserFolders($userId);

        $mime    = (new \finfo(FILEINFO_MIME_TYPE))->file($this->photoProfil['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Photo refusee : formats acceptes = jpg, png, webp.');
        }

        $fileName = 'avatar.' . $allowed[$mime];
        if (!move_uploaded_file($this->photoProfil['tmp_name'], $base . 'profile/' . $fileName)) {
            throw new RuntimeException("Impossible d'enregistrer la photo.");
        }

        // chemin utilisable dans une page web (<img src="...">)
        return 'assets/users/' . $userId . '/profile/' . $fileName;
    }

    /*public function add(){
        $q = $this->_db->prepare("INSERT INTO tbusers SET pseudo = :pseudo, password = :password, description = :description, type = :type");
        
        // c'est une convention dans PHP, envoyer un a la fois
        $q->bindValue(":pseudo", $this->getPseudo());
        $q->bindValue(":password", $this->getPassword());
        $q->bindValue(":description", $this->getDescription());
        $q->bindValue(":type");
        
        
        $q->execute();
    }*/

    // public function addModel(Realisation $model){
    //     $q = $this->_db->prepare("INSERT INTO tbModeles SET image = :image, titreModele = :titreModele, descriptionModele = :descriptionModele, prixModele = :prixModele");
    //     $q->bindValue(":image, :titreModele, :descriptionModele, :prixModele", $model->getImage(), $model->getTitle(), $model->getDescription(), $model->getPrice());
    //     $q->execute();
    // }

    // la connexion a la bd
    // public function setDb(PDO $db){
    // 	var_dump($db);
    //     $this->_db = $db;
    // }

    public static function logout(){
        // bien verifier la connexion sinon la recuperer sinon crash
        if(session_status() !== PHP_SESSION_ACTIVE){
            session_start();
        }
    
        $_SESSION = [];
        session_destroy();
    }
}
