# Guide lomi. pour PrestaShop

Guide marchand en français pour installer, configurer et mettre en production le module **lomi.** sur PrestaShop **1.7+**.

**Module :** `lomi` · **Version :** `1.0.0`

## Vue d’ensemble

Le module connecte votre boutique PrestaShop au **checkout hébergé lomi.** :

1. Le client choisit **lomi.** à l’étape paiement du checkout.
2. PrestaShop crée une session checkout via l’API lomi. (`POST /checkout-sessions`).
3. Le client est redirigé vers `checkout.lomi.africa` pour payer.
4. lomi. confirme le paiement via **webhook** (`PAYMENT_SUCCEEDED`) et/ou **URL de retour** (`/module/lomi/callback`).
5. La commande est créée avec le statut **Payé via lomi.**

Une **carte de marque lomi.** (image « Pay with lomi. » + icônes des moyens de paiement) s’affiche à l’étape paiement.

## Prérequis

- PrestaShop **1.7.x** ou **8.x**
- PHP avec **cURL**
- Devise boutique : **XOF**, **USD** ou **EUR**
- **HTTPS** en production (obligatoire pour les webhooks)
- Au moins un **transporteur** configuré (exigence checkout PrestaShop)
- Compte lomi. : [dashboard.lomi.africa](https://dashboard.lomi.africa)

## Installation

1. Récupérez le dossier **`lomi`** (celui qui contient `lomi.php`).
2. Compressez **uniquement** ce dossier en zip (la racine de l’archive doit être `lomi/`).
3. Back-office → **Modules → Gestionnaire de modules → Installer un module** → uploadez le zip.
4. Cliquez **Installer**, puis **Activer** si nécessaire.
5. Ouvrez **Configurer** et saisissez vos clés API.

### Activer le paiement pour votre devise

1. **International → Localisation → Devises**: activez **EUR**, **USD** ou **XOF**.
2. **Paiement → Préférences** (ou **Paiement → Moyens de paiement** selon la version), autorisez **lomi.** pour les devises utilisées.

Sans clé secrète ou avec une devise non supportée, **lomi.** n’apparaît pas au checkout.

## Configuration admin

**Modules → Gestionnaire de modules → rechercher `lomi` → Configurer**

La page affiche l’**URL webhook** à copier dans le dashboard lomi.

| Champ | Description |
|-------|-------------|
| **Mode test** | **Oui** = API sandbox ; **Non** = production |
| **Clé secrète test** | `lomi_sk_test_…` (dashboard, mode test) |
| **Clé publique test (optionnel)** | Non requise pour le checkout hébergé |
| **Secret webhook test** | `whsec_…` du webhook **test** |
| **Clé secrète live** | `lomi_sk_live_…` (production) |
| **Clé publique live (optionnel)** | Non requise pour le checkout hébergé |
| **Secret webhook live** | `whsec_…` du webhook **live** |

Cliquez **Enregistrer** après chaque modification. Pour mettre à jour un secret, **recollez la valeur complète** (PrestaShop ne réaffiche pas les secrets enregistrés).

### Configurer le webhook

1. Copiez l’**URL webhook** affichée dans la configuration du module. Exemples :

   ```
   https://votre-boutique.com/module/lomi/webhook
   ```

   ou, si PrestaShop est dans un sous-dossier :

   ```
   https://votre-boutique.com/prestashop/module/lomi/webhook
   ```

   Format alternatif (sans URLs simplifiées) :

   ```
   https://votre-boutique.com/index.php?fc=module&module=lomi&controller=webhook
   ```

2. Dashboard lomi. → **Developers → Webhooks** → créer un endpoint :
   - **URL** : celle copiée ci-dessus
   - **Événements** : **`PAYMENT_SUCCEEDED`** (minimum)
   - **Mode** : **test** ou **live** selon **Mode test** dans PrestaShop

3. Copiez le **signing secret** (`whsec_…`) dans le champ correspondant du module.

4. Envoyez un **Test webhook** depuis le dashboard → attendez **HTTP 200** (corps vide = normal pour un événement test).

**Attention :** le signing secret n’est **pas** la clé API `lomi_sk_…`. Chaque endpoint webhook a son propre `whsec_…`. Les secrets test et live sont différents.

## Parcours client

```
Checkout (étape 3) → Payer avec lomi. → Redirection checkout.lomi.africa
    → Paiement réussi
        → Webhook PAYMENT_SUCCEEDED → Commande « Payé via lomi. »
        → (et/ou) Retour /module/lomi/callback → Confirmation de commande
```

Le **webhook** est le chemin fiable si le client ferme le navigateur avant de revenir sur la boutique.

## Tester en sandbox

1. **Mode test** = Oui
2. Clé secrète test + secret webhook test renseignés
3. Webhook **test** dans le dashboard pointant vers votre boutique
4. Ajoutez un produit, complétez adresse + **transporteur**, choisissez **lomi.**
5. Carte test : **`4242 4242 4242 4242`** (date future, CVC au choix)

### Résultats attendus

| Test | Résultat |
|------|----------|
| Bouton « Test webhook » dashboard | **200**: corps vide (normal) |
| Vrai paiement sandbox | Webhook **PAYMENT_SUCCEEDED** → **200** ; statut **Payé via lomi.** |

Autres cartes : [Sandbox payments](https://docs.lomi.africa/start/sandbox-payments).

## Devises

| Devise | Montant envoyé à l’API |
|--------|------------------------|
| XOF | Francs entiers (505 F → `505`) |
| USD / EUR | Centimes (10,50 € → `1050`) |

## Dépannage

| Problème | Cause probable | Action |
|----------|----------------|--------|
| Webhook **401** | Mauvais `whsec_…` ou mélange test/live | Recopier le secret du bon webhook (même mode que Mode test) |
| Webhook **200** sans commande (test dashboard) | Normal pour `test.webhook` | Faire un vrai paiement sandbox |
| **lomi.** absent au checkout | Clé vide, devise, ou module inactif | Vérifier config + **Paiement → Préférences** |
| Commande non créée après paiement | Webhook absent ou session pas `completed` | Corriger webhook en priorité |
| « Aucun transporteur » | Livraison non configurée | Configurer transporteur + zone |

Consultez les logs : **Paramètres avancés → Logs**: filtrez sur `lomi`.

## Limites connues

- Remboursements : via le dashboard lomi., pas depuis l’admin PrestaShop
- Checkout hébergé uniquement (pas de saisie carte native dans PrestaShop)
- Devises hors XOF / USD / EUR : méthode masquée

## Liens

- [Dashboard lomi.](https://dashboard.lomi.africa)
- [Documentation API](https://docs.lomi.africa)
- [Paiements sandbox](https://docs.lomi.africa/start/sandbox-payments)
- Guide anglais : [README.md](../README.md)

## Support

- Site : [lomi.africa](https://lomi.africa)
- Dashboard : [dashboard.lomi.africa](https://dashboard.lomi.africa)
