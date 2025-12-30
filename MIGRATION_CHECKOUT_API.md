# Migration vers l'API HelloAsso Checkout

## Résumé des modifications

Le plugin a été mis à jour pour respecter le flux d'intégration officiel HelloAsso Checkout API v5, conformément à la documentation : https://dev.helloasso.com/docs/intégrer-le-paiement-sur-votre-site

### Ancien flux (incorrect)
- Redirection directe vers un formulaire HelloAsso pré-existant avec paramètres d'URL
- Nécessitait un `formid` configuré pour chaque compte de paiement
- Ne suivait pas les bonnes pratiques de sécurité

### Nouveau flux (conforme API Checkout)
1. **Appel POST** à `https://api.helloasso.com/v5/organizations/{org-slug}/checkout-intents`
2. HelloAsso retourne un `id` et une `redirectUrl` (valide 15 minutes)
3. Redirection de l'utilisateur vers cette `redirectUrl`
4. Après paiement, retour vers `returnUrl` avec `checkoutIntentId`, `code=succeeded`, `orderId`
5. **Vérification obligatoire** via API pour éviter la fraude
6. Validation et délivrance du service

## Fichiers modifiés

### 1. `classes/gateway.php`
**Changements :**
- ✅ Méthode `generate_payment_url()` complètement réécrite pour utiliser l'API Checkout
- ✅ Appel POST à `/v5/organizations/{org-slug}/checkout-intents`
- ✅ Envoi des données structurées (totalAmount, initialAmount, itemName, URLs de retour, metadata)
- ✅ Support production et sandbox via `base_url`
- ✅ Gestion des tokens OAuth2 avec paramètre `$apiurl`
- ✅ Logging des checkout intents créés
- ✅ Suppression du champ `formid` de la configuration (plus nécessaire)

**Nouvelle signature :**
```php
public static function generate_payment_url(
    array $config, 
    int $paymentid, 
    float $amount, 
    string $useremail, 
    string $itemname = 'Paiement Moodle', 
    ?array $payerinfo = null
): moodle_url
```

### 2. `classes/external/get_config_for_js.php`
**Changements :**
- ✅ Suppression de la récupération du `formid` depuis la config du compte
- ✅ Ajout de la construction des informations du payeur depuis le profil Moodle
- ✅ Construction du nom de l'article (itemName) dynamique selon le contexte
- ✅ Appel à `generate_payment_url()` avec les nouveaux paramètres

### 3. `return.php`
**Changements :**
- ✅ Récupération des paramètres HelloAsso Checkout : `checkoutIntentId`, `code`, `orderId`
- ✅ Vérification que `code === 'succeeded'`
- ✅ Fonction `verify_helloasso_payment()` complète qui :
  - Obtient un token OAuth2
  - Récupère le checkout intent via API
  - Vérifie le montant, l'orderId, le statut
  - Valide les metadata pour éviter la fraude
- ✅ Appel correct à `deliver_order()` avec tous les paramètres
- ✅ Meilleure gestion d'erreur et logging

### 4. `error.php` (nouveau fichier)
**Changements :**
- ✅ Nouvelle page pour gérer les erreurs techniques (`errorUrl`)
- ✅ Récupère `checkoutIntentId` et code d'erreur
- ✅ Logging des erreurs techniques
- ✅ Affichage debug si `debugmode` activé

### 5. `settings.php`
**Changements :**
- ✅ Modification de `base_url` : valeur par défaut `helloasso.com` (sans https://)
- ✅ Ajout du paramètre `PARAM_TEXT` pour validation

### 6. `lang/en/paygw_helloasso.php` & `lang/fr/paygw_helloasso.php`
**Changements :**
- ✅ Marquage de `formid` comme `[DEPRECATED]` / `[OBSOLÈTE]`
- ✅ Mise à jour de `base_url_desc` avec instructions claires (sans https://)
- ✅ Ajout de nouvelles strings :
  - `payment_technical_error` : Erreur technique
  - `paymentnotcompleted` : Paiement non complété
  - `tokenfailed` : Échec token OAuth2
  - `checkoutfailed` : Échec création checkout intent

## Configuration requise

### Paramètres globaux du plugin (Administration → Plugins → Passerelles de paiement → HelloAsso)
- **Client ID** : Identifiant API HelloAsso
- **Client Secret** : Secret API HelloAsso
- **Organization slug** : Identifiant de votre organisation (ex: "mon-asso")
- **Base URL** : 
  - Production : `helloasso.com`
  - Sandbox : `sandbox.helloasso.com`
- **Debug mode** : Activer pour voir les logs détaillés

### Paramètres par compte de paiement
- ❌ **formid** : Plus nécessaire avec l'API Checkout

## URLs de retour configurées automatiquement

Le plugin configure automatiquement les URLs suivantes :
- **returnUrl** : `/payment/gateway/helloasso/return.php`
- **backUrl** : `/payment/gateway/helloasso/cancel.php` (retour arrière)
- **errorUrl** : `/payment/gateway/helloasso/error.php` (erreur technique)

## Sécurité renforcée

### Protection contre la fraude
1. ✅ Vérification du `sesskey` Moodle
2. ✅ Vérification que `code === 'succeeded'`
3. ✅ **Appel API pour récupérer le checkout intent** et vérifier :
   - Le montant correspond
   - L'orderId correspond
   - Le statut est `Authorized` ou `Processed`
   - Les metadata correspondent au paiement Moodle
4. ✅ Logging de toutes les tentatives de fraude

### Metadata envoyées
```json
{
  "moodle_payment_id": 123,
  "moodle_user_id": 456
}
```

## Informations du payeur pré-remplies

Le plugin envoie automatiquement les informations disponibles depuis le profil Moodle :
- `email` : Email de l'utilisateur
- `firstName` : Prénom
- `lastName` : Nom
- `city` : Ville (si disponible)
- `country` : Pays au format ISO (si disponible)

Cela facilite le parcours de paiement pour l'utilisateur.

## Logging amélioré

Nouveaux types d'actions loggées :
- `checkout_intent_creation` : Création du checkout intent
- `payment_technical_error` : Erreur technique HelloAsso
- `payment_return` : Retour après paiement avec vérification

Les logs incluent :
- `checkoutIntentId` dans le champ `reference`
- Code HTTP de réponse
- Messages d'erreur détaillés

## Test de l'intégration

### 1. Environnement sandbox
```
base_url = sandbox.helloasso.com
```
Utilisez vos clés API sandbox HelloAsso.

### 2. Activer le debug
```
debugmode = 1
```
Activez également le debug Moodle (Administration → Development → Debugging → DEVELOPER level)

### 3. Tester un paiement
1. Créer un compte de paiement avec la passerelle HelloAsso
2. Configurer une inscription payante (enrol_fee)
3. Tenter une inscription
4. Vérifier les logs dans `mdl_payment_helloasso_logs`

### 4. Vérifier les étapes
- [ ] Token OAuth2 obtenu
- [ ] Checkout intent créé (HTTP 200)
- [ ] Redirection vers HelloAsso
- [ ] Retour avec `code=succeeded`, `checkoutIntentId`, `orderId`
- [ ] Vérification API réussie
- [ ] Inscription validée

## Migration depuis l'ancienne version

### Étape 1 : Mise à jour du code
```bash
cd c:\data\perso\dev\moodle-helloasso
npm run build
npm run zip
```

### Étape 2 : Déploiement
1. Décompresser le nouveau zip dans `/path/to/moodle/payment/gateway/helloasso/`
2. Visiter Administration → Notifications pour lancer les mises à jour

### Étape 3 : Reconfiguration
1. Aller dans Administration → Plugins → Passerelles de paiement → HelloAsso
2. Vérifier/configurer :
   - Client ID
   - Client Secret
   - Organization slug
   - Base URL (nouvelle syntaxe sans https://)
3. Les comptes de paiement existants continueront de fonctionner (le champ formid est ignoré)

### Étape 4 : Test
1. Activer debugmode
2. Effectuer un paiement de test
3. Vérifier les logs

## Points d'attention

⚠️ **IMPORTANT** : L'API Checkout ne nécessite **PAS** de formulaire pré-créé dans HelloAsso. Le paiement est créé dynamiquement via l'API.

⚠️ **Sécurité** : Ne JAMAIS faire confiance uniquement aux paramètres d'URL de retour. Toujours vérifier via l'API HelloAsso (ce qui est maintenant implémenté).

⚠️ **Token** : Les tokens OAuth2 ont une durée de validité limitée. Le code obtient un nouveau token à chaque paiement.

⚠️ **Checkout Intent** : L'URL de redirection est valide **15 minutes seulement**. Au-delà, l'utilisateur devra recommencer.

## Prochaines étapes recommandées

1. [ ] Implémenter la gestion des webhooks HelloAsso pour validation asynchrone
2. [ ] Ajouter le support des paiements par échéances (`terms`)
3. [ ] Implémenter le remboursement via API (`can_refund() => true`)
4. [ ] Ajouter plus de champs optionnels du payeur (adresse, code postal, date de naissance)

## Documentation de référence

- [API HelloAsso Checkout](https://dev.helloasso.com/docs/intégrer-le-paiement-sur-votre-site)
- [Validation des paiements](https://dev.helloasso.com/docs/validation-de-vos-paiements)
- [API Reference](https://dev.helloasso.com/reference)
