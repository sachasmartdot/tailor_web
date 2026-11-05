# Paiement, suivi des commandes et retraits

Ce document est la version propre du besoin, telle qu'elle a été réalisée.

## Principe

1. Le client paie depuis le panier (MTN ou Orange). Au clic sur « Payer », `checkout.php` prépare **une commande par tailleur** et un enregistrement dans la collection `payments`. Ces commandes restent **invisibles** (client et tailleur) tant que le paiement n'est pas confirmé. L'argent arrive sur le compte Aangaraa Pay de la plateforme.
2. Le tailleur met à jour l'avancement : statut, message, photo facultative.
3. Quand le tailleur a terminé, le client confirme la réception depuis son tableau de bord.
4. Seulement à ce moment, le tailleur peut retirer l'argent de cette commande sur le numéro de son profil.

## Règles

- Une commande n'apparaît qu'une fois son paiement confirmé (`paiement_statut` = `SUCCESSFUL`). Les paiements refusés, annulés ou expirés ne créent aucune commande visible.
- Le paiement est confirmé par deux chemins qui utilisent le même code (`PaymentRepository::applyGatewayStatus`, rejouable sans effet double) : `callback.php` quand le client revient sur le site, et `notify.php` (webhook d'Aangaraa) s'il a fermé la page. Le statut reçu par le webhook n'est jamais appliqué tel quel : on le redemande à Aangaraa Pay avec notre `payToken`.
- Le client télécharge la facture PDF de chaque commande depuis « Mes commandes » ; elle se télécharge aussi automatiquement après le paiement (une par tailleur).
- Le bouton de retrait de chaque commande affiche le prix de la commande : c'est le montant envoyé (aucune saisie de montant).
- Un retrait n'est possible que si la commande est validée par le client et n'a pas déjà été retirée. Cette règle est vérifiée côté serveur, pas seulement en cachant le bouton.
- Le client ne peut confirmer la réception que si le tailleur a marqué la commande "Terminée".
- Le tailleur ne peut plus modifier l'avancement d'une commande déjà validée par le client.
- Le montant retiré est le total de la commande (aucune commission pour le moment).
- Au moment du retrait, le tailleur choisit MTN ou Orange et saisit le numéro à créditer (pré-rempli avec le numéro de son profil, modifiable).
- Le tailleur fait passer la commande de « Initiée » à « En cours de confection » puis « Terminée » depuis `tailor_orders.php`.
- Si la connexion à Aangaraa Pay est perdue pendant un retrait, la commande reste « en cours » (pas de nouveau retrait automatique) : vérifier dans le compte Aangaraa Pay avant de relancer.
- Un clic double ne peut pas déclencher deux retraits : la commande est verrouillée dans une transaction avant l'appel à l'API.

## Les soldes du tailleur (bouton "Consulter mes soldes")

| Solde | Ce qu'il contient |
| --- | --- |
| Solde retirable | commandes validées par le client, pas encore retirées |
| En cours de traitement | commandes payées mais pas encore validées par le client |
| Déjà retiré | commandes dont le retrait est fait ou en cours |

## Pages

| Page | Rôle |
| --- | --- |
| `tailor_orders.php` | tableau de bord du tailleur : soldes, avancement, retraits |
| `client_orders.php` | tableau de bord du client : avancement, confirmation de réception, facture |
| `receipt.php` | facture PDF d'une commande payée (client uniquement) |
| `checkout.php` | prépare les commandes et le paiement, lance la demande chez Aangaraa Pay |
| `callback.php` | page d'attente après le paiement : vérifie l'état, ouvre la confirmation |
| `order_confirmation.php` | confirmation, téléchargement automatique des factures |
| `notify.php` / `cancel.php` | URL de notification (webhook) et d'annulation demandées par Aangaraa Pay |

## Champs ajoutés à une commande (collection `orders`)

| Champ | Valeurs |
| --- | --- |
| `statut` | `en_attente`, `en_cours`, `termine` |
| `suivi_note`, `suivi_image` | message et photo du tailleur |
| `paiement_ref` | référence du paiement (`transaction_id`) ; `TEST` pour une commande de test |
| `paiement_statut` | `PENDING`, `SUCCESSFUL`, `FAILED`, `CANCELLED`, `EXPIRED` (seule `SUCCESSFUL` rend la commande visible) |
| `paiement_txnid`, `paiement_reason` | identifiant côté Aangaraa et motif d'un échec |
| `recu` | `true` quand le client a confirmé (avec `recu_at`) |
| `retrait` | vide, `en_cours` ou `effectue` (avec `retrait_ref`, `retrait_operateur`, `retrait_at`) |

Les anciennes commandes sans ces champs fonctionnent : elles sont lues comme "non reçues" et "non retirées".

## Collection `payments` (un document par tentative de paiement)

`transaction_id`, `client_uid`, `amount`, `operator`, `phone_number`, `order_ids` (les commandes préparées), `status`, `pay_token` (référence de suivi donnée par Aangaraa), `gateway_txnid`, `reason`, `created_at`, `updated_at`.

Un statut final (`SUCCESSFUL`) n'est jamais modifié ; un paiement `FAILED` ou `EXPIRED` peut encore passer à `SUCCESSFUL` si Aangaraa le confirme plus tard.

## API Aangaraa Pay utilisées

- `POST /api/v1/no_redirect/payment` : paiement du client
- `POST /api/v1/aangaraa_check_status` : vérification du paiement
- `POST /api/v1/aangaraa-pay/withdrawal` : retrait vers le tailleur
- `GET /api/v1/check_withdrawal_status/{reference}?payment_method=...` : état d'un retrait en attente, relu à chaque ouverture du tableau de bord du tailleur

Tous les réglages (clé, adresse du site, opérateurs) sont dans `includes/aangaraa.php`.

## À décider plus tard

- Commission de la plateforme sur chaque commande.
- Frais de retrait Aangaraa Pay.
- Litiges : que faire si le client ne confirme jamais la réception (confirmation automatique après un délai, par exemple).
