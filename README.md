# Tailor Platform Web

Plateforme de couture sur mesure : les clients commandent, les tailleurs publient leurs modèles,
suivent leurs commandes et retirent leur paiement. PHP 8.1, Firebase (Authentication + Firestore),
Aangaraa Pay (MTN MoMo / Orange Money), Bootstrap.

## Lancer le projet en local (3 terminaux)

**1. Les émulateurs Firebase** (dossier `firebase/`, à part de ce projet)

    firebase emulators:start --project demo-tailor --import=./emulator-data --export-on-exit=./emulator-data

**2. Le tunnel public** (Aangaraa Pay doit pouvoir joindre le site)

    ngrok http 8000

**3. Le site** : copie `lancer_local.example.sh` en `lancer_local.sh`, remplis ta clé Aangaraa et
l'adresse ngrok, puis :

    bash lancer_local.sh

Ouvre ensuite **l'adresse ngrok** dans le navigateur (pas `localhost`) : la session doit rester sur le
même domaine pendant tout le paiement.

PHP doit avoir les extensions `curl`, `bcmath` et `fileinfo` (`php -m` pour vérifier).

## Local ou production

Dans `includes/bootstrap.php`, `$useEmulator = true` utilise les émulateurs (aucune clé Firebase
nécessaire). Pour la production, passe-le à `false` et place `cle-firebase.json` à la racine du projet.

## Tester les commandes sans payer

Connecté en client, sans commande, ouvre « Mes commandes » : le lien « créer une commande de test »
crée une commande payée fictive. Les retraits des commandes de test sont simulés : aucun vrai argent.
Cette page ne fonctionne que si `$useEmulator = true`.

## Organisation du code

| Dossier | Contenu |
| --- | --- |
| `public/` | les pages du site (point d'entrée du serveur) |
| `includes/` | socle commun : `bootstrap.php`, `aangaraa.php`, `header.php`, `footer.php`, `firebase_local.php` |
| `src/Data/` | accès à Firestore : `CatalogueRepository`, `OrderRepository`, `PaymentRepository` |
| `src/Model/` | `User` (inscription) |
| `src/Support/` | `CartService` (panier), `ImageUploader`, `ReceiptPdf` (facture) |
| `docs/` | `paiement_et_retraits.md` : le parcours commande, paiement, retrait |
| `storage/payments/` | journaux d'Aangaraa Pay (créés automatiquement) |

## Secrets

La clé Aangaraa n'est écrite dans aucun fichier : elle vient de la variable `AANGARAA_KEY`.
`cle-firebase.json` et `lancer_local.sh` sont dans `.gitignore`. Ne les envoie jamais (zip, chat, dépôt).
