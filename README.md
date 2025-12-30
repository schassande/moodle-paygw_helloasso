# Plugin HelloAsso pour Moodle

Plugin de passerelle de paiement permettant d'intégrer HelloAsso dans Moodle pour gérer les inscriptions payantes aux cours.

## Vue d'ensemble

Ce plugin permet aux utilisateurs de payer leur inscription à un cours via HelloAsso, une plateforme de paiement française dédiée aux associations. Le plugin utilise l'**API HelloAsso Checkout v5** pour créer des intentions de paiement sécurisées et conformes aux bonnes pratiques.

**Documentation officielle :** [https://dev.helloasso.com/docs/intégrer-le-paiement-sur-votre-site](https://dev.helloasso.com/docs/intégrer-le-paiement-sur-votre-site)

## Flux de paiement

1. ✅ **Appel POST** à `https://api.helloasso.com/v5/organizations/{org-slug}/checkout-intents`
2. ✅ HelloAsso retourne un `id` et une `redirectUrl` (valide 15 minutes)
3. ✅ Redirection de l'utilisateur vers cette `redirectUrl`
4. ✅ Après paiement, retour vers `returnUrl` avec `checkoutIntentId`, `code=succeeded`, `orderId`
5. ✅ **Vérification obligatoire via API** pour éviter la fraude
6. ✅ Validation et délivrance du service

## Architecture du plugin

### Fichiers de configuration de base

#### **version.php**
- Définit les métadonnées du plugin (version, compatibilité Moodle, maturité)
- Permet à Moodle de gérer les mises à jour
- Doit être incrémenté à chaque modification pour forcer la mise à jour

#### **lib.php**
- Contient les fonctions callbacks pour Moodle
- `paygw_helloasso_payment_gateways()` : enregistre la passerelle HelloAsso dans le système de paiement de Moodle
- Déclare le nom d'affichage et le composant

#### **settings.php**
- Crée la page de configuration globale du plugin dans l'administration Moodle
- Définit les champs de configuration :
  - **clientid** : Client ID de l'API HelloAsso
  - **clientsecret** : Client Secret de l'API HelloAsso
  - **org_slug** : Identifiant de l'organisation (slug)
  - **base_url** : URL de base (`helloasso.com` ou `helloasso-sandbox.com`)
  - **debugmode** : Mode debug pour logging détaillé
- Accessible via : Administration du site → Plugins → Passerelles de paiement → HelloAsso

---

### Base de données

#### **db/install.xml**
- Définit la structure de la table `payment_helloasso_logs` lors de l'installation
- Contient les champs pour logger toutes les actions :
  - Actions de paiement (initiation, succès, échec)
  - Erreurs et codes de réponse HTTP
  - Détection de fraude (tentatives multiples, IP suspectes)
  - Référence HelloAsso et montants

#### **db/access.php**
- Définit les permissions/capacités du plugin
- `paygw/helloasso:manage` : capacité pour gérer la passerelle (réservée aux managers)
- Hérite des permissions de `moodle/site:config`

#### **db/services.php**
- Déclare les services web (API AJAX) utilisables par JavaScript
- `paygw_helloasso_get_config_for_js` : service web qui permet au JavaScript de :
  - Créer une transaction de paiement
  - Obtenir l'URL de redirection vers HelloAsso
  - Passer les paramètres nécessaires (montant, référence, URLs de retour)

---

### Classes principales

#### **classes/gateway.php**
**Classe centrale** du plugin qui étend `core_payment\gateway`

**Méthodes principales :**
- `get_supported_currencies()` : retourne `['EUR']` (seule devise supportée par HelloAsso)
- `add_configuration_to_gateway_form($form)` : vide - la configuration est globale, pas par compte
- `validate_gateway_form($form, $data, $files, &$errors)` : vide - pas de validation spécifique
- `generate_payment_url($config, $paymentid, $amount, $useremail, $itemname, $payerinfo)` : **méthode principale**
  - Obtient un token OAuth2
  - Crée un checkout intent via POST à `/v5/organizations/{org-slug}/checkout-intents`
  - Envoie : totalAmount, initialAmount, itemName, URLs de retour, metadata, informations du payeur
  - Retourne l'URL de redirection HelloAsso
- `initiate_payment($payment, $options)` : délègue à `generate_payment_url()`
- `get_helloasso_token()` : obtient le token OAuth2 via `grant_type=client_credentials`
  - Construit automatiquement l'URL API depuis `base_url`
  - Token valide environ 30 minutes
- `get_API_url()` : retourne `https://api.{base_url}` (construction dynamique)
- `can_refund()` : retourne `false` (les remboursements ne sont pas encore implémentés)

#### **classes/logger.php**
Gère tous les logs du plugin dans la table `payment_helloasso_logs`

**Méthodes principales :**
- `log_action($paymentid, $userid, $action, $status, $amount, $message, $response_code, $reference)` : enregistre une action
  - Actions : `payment_initiation`, `payment_return`, `token_request`, `checkout_intent_creation`, etc.
  - Status : `success`, `error`, `fraud_detected`, `cancelled`
- `get_payment_logs($paymentid)` : récupère tous les logs d'un paiement spécifique
- `get_error_logs($limit)` : récupère les erreurs récentes (utile pour le monitoring)
- `get_fraud_alerts($limit)` : récupère les alertes de fraude (tentatives suspectes)

#### **classes/external/get_config_for_js.php**
Service web appelé par JavaScript lors de l'initiation du paiement

**Processus :**
1. Récupère le compte de paiement configuré pour HelloAsso
2. Récupère la configuration globale (org_slug, clientid, clientsecret, base_url)
3. Calcule le coût du cours
4. **Crée une transaction de paiement** dans la table `payments` de Moodle
5. Construit les informations du payeur depuis le profil Moodle :
   - email, firstName, lastName
   - city, country (si disponibles)
6. Construit le nom de l'article (itemName) selon le contexte
7. Appelle `gateway::generate_payment_url()` pour créer le checkout intent
8. Enregistre un log de l'initiation
9. Retourne l'URL de redirection au JavaScript

---

### JavaScript (AMD - Asynchronous Module Definition)

#### **amd/src/gateways_modal.js** (source)
Code JavaScript ES6 lisible et maintenable

**Fonction principale :**
- `process(component, paymentArea, itemId, description)` :
  - Appelée automatiquement quand l'utilisateur clique sur "Payer avec HelloAsso"
  - Appelle le service web `paygw_helloasso_get_config_for_js` via Ajax
  - Récupère l'URL de redirection
  - Redirige l'utilisateur vers HelloAsso avec `window.location.href`

#### **amd/build/gateways_modal.min.js** (compilé)
- Version minifiée et optimisée du fichier JS pour la production
- Chargée automatiquement par Moodle lors de l'affichage de la modal de paiement
- Générée via Grunt : `npm run build`

#### **amd/build/gateways_modal.min.js.map**
- Fichier de mapping pour le débogage
- Permet de relier le code minifié au code source dans les outils de développement du navigateur

---

### Pages de retour utilisateur

#### **return.php**
Page de **retour après paiement réussi** sur HelloAsso

**Processus :**
1. Récupère les paramètres GET : `paymentid`, `sesskey`, `checkoutIntentId`, `code`, `orderId`
2. **Vérifie le sesskey** (protection anti-CSRF)
   - Si invalide : log de fraude + erreur 403
3. Charge l'enregistrement du paiement depuis la base de données (`payments` table)
4. Vérifie que `code === 'succeeded'`
5. **⚠️ IMPORTANT : Vérification obligatoire via API** (fonction `verify_helloasso_payment()`)
   - Obtient un token OAuth2
   - Récupère le checkout intent : `GET /v5/organizations/{org-slug}/checkout-intents/{checkoutIntentId}`
   - Vérifie que :
     - Le checkout intent existe et contient un order.id
     - L'orderId correspond
     - Le montant correspond (order.amount.total)
     - Un paiement avec statut "Authorized" ou "Processed" existe
     - Les metadata correspondent (moodle_payment_id)
6. **Débloque l'accès** au cours via `\core_payment\helper::deliver_order()`
   - Inscrit l'utilisateur au cours
   - Déclenche les événements Moodle associés
7. Enregistre un log de succès
8. Affiche un message de confirmation avec `$OUTPUT->notification()`

**Sécurité renforcée :**
- ✅ Ne fait **JAMAIS** confiance uniquement aux paramètres d'URL
- ✅ Vérification complète via API avant de délivrer le service

#### **cancel.php**
Page affichée si l'utilisateur **annule** le paiement sur HelloAsso (`backUrl`)

**Processus :**
1. Récupère le `paymentid` depuis les paramètres d'URL
2. **Enregistre un log d'annulation** dans `payment_helloasso_logs`
3. Affiche un message d'annulation avec bouton de retour
4. L'utilisateur peut retourner au cours et réessayer

#### **error.php**
Page affichée en cas d'**erreur technique** sur HelloAsso (`errorUrl`)

**Processus :**
1. Récupère les paramètres : `paymentid`, `sesskey`, `checkoutIntentId`, `error`
2. Vérifie le sesskey
3. Charge le paiement depuis la base de données
4. **Enregistre un log d'erreur technique**
5. Affiche le message d'erreur
6. Si debug activé : affiche les détails techniques (error code, checkoutIntentId)

#### **webhook.php**
Endpoint pour les **notifications webhook** de HelloAsso (optionnel, non encore implémenté)


---

### Fichiers de langue

#### **lang/en/paygw_helloasso.php**
Toutes les chaînes de texte en **anglais**

**Contient :**
- Labels des formulaires (`clientid`, `clientsecret`, etc.)
- Descriptions des champs (`clientid_desc`, `clientid_help`)
- Messages utilisateur (`payment_success`, `payment_cancelled`, `payment_error`)
- Messages d'erreur (`missingconfig`, `invalidamount`, `paymentnotfound`)

#### **lang/fr/paygw_helloasso.php**
Toutes les chaînes de texte en **français**

**Contient :**
- Traduction exacte des mêmes clés que la version anglaise
- Moodle charge automatiquement la langue selon les préférences de l'utilisateur

---

## Flux complet d'un paiement

### Étape 1 : Initiation
1. **L'utilisateur clique sur "S'inscrire au cours"** dans Moodle
2. Moodle affiche la modal de sélection de passerelle de paiement
3. L'utilisateur sélectionne "HelloAsso"
4. Moodle charge automatiquement `amd/build/gateways_modal.min.js`

### Étape 2 : Préparation (JavaScript)
5. Le JavaScript appelle le service web `paygw_helloasso_get_config_for_js` via Ajax
6. Paramètres envoyés : `component`, `paymentarea`, `itemid`

### Étape 3 : Création de transaction et checkout intent (PHP)
7. Le service web (`classes/external/get_config_for_js.php`) :
   - Vérifie que l'utilisateur est connecté
   - Récupère le compte de paiement et la configuration HelloAsso
   - Calcule le montant
   - **Crée une entrée dans la table `payments`** avec statut "pending"
   - Construit les informations du payeur (email, nom, prénom, ville, pays)
   - Appelle `gateway::generate_payment_url()` qui :
     - Obtient un token OAuth2
     - **Crée un checkout intent** via POST à l'API HelloAsso
     - Envoie : totalAmount, initialAmount, itemName, returnUrl, backUrl, errorUrl, metadata, payer
     - Reçoit : checkoutIntentId et redirectUrl
   - Enregistre un log d'initiation avec le checkoutIntentId
   - Retourne l'URL de redirection au JavaScript

### Étape 4 : Redirection vers HelloAsso
8. Le JavaScript reçoit l'URL et redirige l'utilisateur : `window.location.href = url`
9. L'utilisateur arrive sur la page de paiement HelloAsso (redirectUrl valide 15 minutes)

### Étape 5 : Paiement sur HelloAsso
10. L'utilisateur saisit ses informations de carte bancaire
11. HelloAsso traite le paiement
12. HelloAsso valide ou refuse le paiement

### Étape 6 : Retour sur Moodle
13. **Si succès** : HelloAsso redirige vers `return.php?paymentid=X&sesskey=Y&checkoutIntentId=Z&code=succeeded&orderId=W`
14. **Si annulation** : HelloAsso redirige vers `cancel.php?paymentid=X`
15. **Si erreur technique** : HelloAsso redirige vers `error.php?paymentid=X&sesskey=Y&error=code`

### Étape 7 : Validation et déblocage (return.php)
16. `return.php` vérifie le `sesskey` (sécurité)
17. Charge la transaction depuis la base de données
18. Vérifie que `code === 'succeeded'`
19. **⚠️ OBLIGATOIRE : Vérification via API HelloAsso** (`verify_helloasso_payment()`)
    - Obtient un nouveau token OAuth2
    - Appelle `GET /v5/organizations/{org-slug}/checkout-intents/{checkoutIntentId}`
    - Vérifie l'intégrité complète du paiement (montant, orderId, statut, metadata)
20. **Appelle `\core_payment\helper::deliver_order($payment)`** qui :
    - Inscrit l'utilisateur au cours
    - Met à jour le statut du paiement à "success"
    - Déclenche l'événement `\core\event\payment_successful`
21. Enregistre un log de succès avec le checkoutIntentId
22. Affiche le message de confirmation

---

## Installation

### Prérequis
- Moodle 4.2 ou supérieur
- PHP 7.4 ou supérieur
- Compte HelloAsso avec accès API

### Étapes d'installation

1. **Télécharger le plugin**
Créer un zip du dossier en utilisant la commande:
   ```bash
   npm run zip
   ```
Le fichier zip est créé dans le repertoire parent.

2. **Se connecter à Moodle en tant qu'administrateur**
   - Aller dans **Administration du site → Plugins → Installer des plugins**
   - Uploader le fichier zip
   - Suivre les instructions pour finir l'installation"

3. **Configurer HelloAsso**
   - Aller sur [https://www.helloasso.com](https://www.helloasso.com)
   - Se connecter au back-office de votre association
   - Aller dans **Paramètres → API**
   - Créer un nouveau client API et noter :
     - **Client ID**
     - **Client Secret**
   - Noter votre **Organization Slug** (dans l'URL du back-office : `/organizations/{slug}/...`)

4. **Configurer le plugin dans Moodle**
   - Aller dans **Administration du site → Plugins → Passerelles de paiement → Gérer les passerelles de paiement**
   - Activer "HelloAsso"
   - Cliquer sur "Paramètres"
   - Renseigner :
     - **Client ID** : votre Client ID HelloAsso
     - **Client Secret** : votre Client Secret HelloAsso
     - **Organization Slug** : identifiant de votre organisation
     - **Base URL** :
       - Production : `helloasso.com`
       - Sandbox (tests) : `helloasso-sandbox.com`

5. **Créer un compte de paiement**
   - Aller dans **Administration du site → Paiements → Comptes de paiement**
   - Cliquer sur "Créer un compte de paiement"
   - Nom : "HelloAsso Production" (ou "HelloAsso Sandbox" pour tests)
   - Passerelles activées : cocher "HelloAsso"
   - Enregistrer

6. **Configurer un cours avec inscription payante**
   - Éditer un cours
   - Aller dans **Administration du cours → Utilisateurs → Méthodes d'inscription**
   - Ajouter "Inscription après paiement"
   - Configurer :
     - Compte de paiement : "HelloAsso Production"
     - Coût d'inscription : montant en euros
     - Devise : EUR
   - Enregistrer

7. **Tester le paiement**
   - Se déconnecter
   - Se connecter avec un compte étudiant (ou créer un compte test)
   - Aller sur le cours
   - Cliquer sur "S'inscrire au cours"
   - Sélectionner "HelloAsso"
   - Vérifier la redirection vers HelloAsso
   - Effectuer un paiement test

---

## Configuration avancée

### Mode sandbox (tests)

Pour tester sans effectuer de vrais paiements :

1. **Créer un compte sandbox HelloAsso**
   - Aller sur [https://www.helloasso-sandbox.com](https://www.helloasso-sandbox.com)
   - Créer une organisation de test
   - Générer des clés API sandbox

2. **Configurer le plugin en mode sandbox**
   - Dans Moodle : Administration → Plugins → HelloAsso → Paramètres
   - Base URL : `helloasso-sandbox.com`
   - Client ID et Secret : ceux du sandbox
   - Debug mode : **activé**

3. **Tester un paiement**
   - Utiliser les cartes de test HelloAsso
   - Les paiements sandbox n'ont pas de coût réel

### Logs et monitoring

- **Consulter les logs** : requête SQL directe ou créer une page d'admin
  ```sql
  SELECT * FROM mdl_payment_helloasso_logs 
  ORDER BY timecreated DESC 
  LIMIT 100;
  ```

- **Surveiller les fraudes** :
  ```sql
  SELECT * FROM mdl_payment_helloasso_logs 
  WHERE status = 'fraud_detected' 
  ORDER BY timecreated DESC;
  ```

- **Analyser les erreurs** :
  ```sql
  SELECT * FROM mdl_payment_helloasso_logs 
  WHERE status = 'error' 
  ORDER BY timecreated DESC;
  ```

---

## Développement

### Compiler le JavaScript après modifications

Si vous modifier les sources dans amd alors il faut recalculer la version minifiée en lançant:

```bash
npm run build
```

### Incrémenter la version

Après chaque modification :

1. Éditer `package.json` et `version.php` :
```json
// package.json
"version": "0.5.0",
"build": "2025123000"
```

2. Reconstruire le package :
```bash
npm run zip
```

3. Déployer et aller dans **Administration du site → Notifications** pour appliquer la mise à jour.

### Structure des logs

Chaque action enregistre :
- `paymentid` : ID de la transaction Moodle
- `userid` : ID de l'utilisateur concerné
- `action` : type d'action (`payment_initiation`, `payment_return`, `token_request`, etc.)
- `status` : `success`, `error`, ou `fraud_detected`
- `amount` : montant en euros
- `reference` : référence HelloAsso (CHECKOUT-{checkoutIntentId})
- `message` : détails ou message d'erreur
- `response_code` : code HTTP de l'API (200, 403, 500, etc.)
- `ip_address` : IP du client (pour détection de fraude)
- `timecreated` : timestamp Unix

---

## Dépannage

### Le plugin n'apparaît pas dans les passerelles de paiement
- Vérifier que le dossier est bien nommé `helloasso` dans `payment/gateway/`
- Purger tous les caches : **Administration du site → Développement → Purger tous les caches**
- Vérifier les permissions du dossier (doit être accessible en lecture par le serveur web)

### Erreur "TypeError: a.getConfigForJs is not a function"
- Le fichier JavaScript n'est pas à jour
- Recompiler avec Grunt : `npm run build`
- Purger le cache navigateur (Ctrl+Shift+Delete)
- Purger le cache Moodle

### Écran blanc après sélection de HelloAsso
- Ouvrir la console JavaScript du navigateur (F12)
- Vérifier les erreurs réseau
- Vérifier que le service web `paygw_helloasso_get_config_for_js` est bien déclaré dans **Administration du site → Serveur → Services web → Aperçu**
- Vérifier les logs PHP et JavaScript

### Le paiement ne se valide pas après retour de HelloAsso
- Vérifier le `sesskey` dans l'URL (doit correspondre à la session)
- Vérifier que `return.php` ne génère pas d'erreur PHP
- Consulter les logs dans `payment_helloasso_logs`
- Activer le débogage : **Administration du site → Développement → Débogage**

### Les chaînes de langue apparaissent comme [[gatewayname]]
- Les fichiers de langue ne sont pas chargés
- Vérifier que les fichiers existent : `lang/en/paygw_helloasso.php` et `lang/fr/paygw_helloasso.php`
- Purger le cache des chaînes de langue : **Administration du site → Langue → Packs de langues → Purger le cache**

### Erreur "Failed to obtain authentication token"
- Vérifier que le Client ID et Client Secret sont corrects
- Vérifier que la Base URL correspond à votre environnement
- Vérifier les logs dans `payment_helloasso_logs` (action: `token_request`)
- Tester manuellement le token avec :
  ```bash
  curl -X POST https://api.helloasso.com/oauth2/token \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "grant_type=client_credentials&client_id=YOUR_ID&client_secret=YOUR_SECRET"
  ```

### Erreur HTTP 403 lors de la création du checkout intent
- Vérifier que l'Organization Slug est correct
- Vérifier que votre client API a le privilège "Checkout"
- Vérifier que les URLs de retour sont accessibles publiquement
- Consulter les logs HelloAsso pour plus de détails

### Le paiement est effectué mais l'inscription ne se valide pas
- Vérifier que la fonction `verify_helloasso_payment()` ne retourne pas `false`
- Activer le debug et consulter les logs Moodle
- Vérifier que les metadata envoyées correspondent (moodle_payment_id)
- Vérifier que le montant envoyé correspond au montant reçu

---

## Sécurité

### Bonnes pratiques implémentées

1. **Vérification du sesskey** : protection anti-CSRF sur toutes les pages de retour
2. **⚠️ Vérification obligatoire via API** : ne fait **JAMAIS** confiance aux seuls paramètres d'URL
   - Récupération du checkout intent via API
   - Vérification du montant, orderId, statut, metadata
3. **Metadata de traçabilité** : chaque paiement contient `moodle_payment_id` et `moodle_user_id`
4. **Logs complets** : traçabilité de toutes les actions (création checkout intent, vérification, succès, erreurs)
5. **Validation des montants** : vérification que le montant est positif et correspond
6. **Gestion des permissions** : seuls les managers peuvent configurer la passerelle
7. **Pas de stockage de données sensibles** : pas de numéros de carte en base
8. **Token OAuth2 éphémère** : nouveau token obtenu à chaque paiement (durée limitée)

### Points d'attention

- **Client Secret** : ne jamais exposer dans le code JavaScript ou les logs (utilisé uniquement côté serveur)
- **HTTPS obligatoire** : toutes les URLs de retour doivent être en HTTPS
- **Checkout intent expiré** : l'URL de redirection est valide 15 minutes seulement
- **Debugging en production** : désactiver le mode debug en production pour éviter la fuite d'informations sensibles

---

## Checklist de vérification

Avant de mettre en production, vérifier :

- [ ] Client ID et Client Secret corrects
- [ ] Organization Slug correct
- [ ] Base URL correcte (`helloasso.com` pour production)
- [ ] Compte de paiement créé et passerelle HelloAsso activée
- [ ] Cours configuré avec "Inscription après paiement"
- [ ] Montant d'inscription configuré
- [ ] Test effectué en mode sandbox
- [ ] Vérification des logs : pas d'erreurs
- [ ] Debug mode désactivé en production
- [ ] HTTPS activé sur le site Moodle
- [ ] URLs de retour accessibles publiquement

## Ressources et documentation

- **Documentation officielle HelloAsso Checkout** : [https://dev.helloasso.com/docs/intégrer-le-paiement-sur-votre-site](https://dev.helloasso.com/docs/intégrer-le-paiement-sur-votre-site)
- **API Reference** : [https://dev.helloasso.com/reference](https://dev.helloasso.com/reference)
- **Swagger API** : [https://api.helloasso.com/v5/swagger](https://api.helloasso.com/v5/swagger)
- **Documentation Moodle Payment API** : [https://docs.moodle.org/dev/Payment_API](https://docs.moodle.org/dev/Payment_API)

## Support

Pour toute question ou problème :
1. Consulter les logs dans `mdl_payment_helloasso_logs`
2. Activer le débogage Moodle (DEVELOPER level)
3. Activer le debug mode du plugin
4. Vérifier la console JavaScript (F12)
5. Consulter la documentation HelloAsso
6. Ouvrir une issue sur GitHub

---

## Roadmap

Fonctionnalités possibles pour les prochaines versions :

- [ ] Support des paiements en plusieurs fois (terms)
- [ ] Gestion des remboursements via API (`can_refund()`)
- [ ] Page d'administration pour consulter les logs
- [ ] Support de champs personnalisés du payeur
- [ ] Statistiques de paiement
- [ ] Export des transactions
- [ ] Support des dons optionnels (containsDonation)

## Auteur

Sebastien Chassande-Barrioz

## Licence

GNU GPL v3 ou ultérieure
