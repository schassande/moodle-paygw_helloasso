# Plugin HelloAsso pour Moodle

Plugin de passerelle de paiement permettant d'intégrer HelloAsso dans Moodle pour gérer les inscriptions payantes aux cours.

## Vue d'ensemble

Ce plugin permet aux utilisateurs de payer leur inscription à un cours via HelloAsso, une plateforme de paiement française dédiée aux associations.

## Architecture du plugin

Description de l'intégration du paiement Hello Asso:
[https://dev.helloasso.com/docs/int%C3%A9grer-le-paiement-sur-votre-site](https://dev.helloasso.com/docs/int%C3%A9grer-le-paiement-sur-votre-site)


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
- Définit les champs de configuration (clientid, clientsecret, org_slug, formid)
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
- `add_configuration_to_gateway_form($form)` : ajoute les champs de configuration dans le formulaire du compte de paiement
  - Client ID
  - Client Secret
  - Organization Slug
  - Form ID
- `validate_gateway_form($form, $data, $files, &$errors)` : valide les données saisies (champs obligatoires)
- `initiate_payment($payment, $options)` : génère l'URL de paiement HelloAsso (si redirection côté serveur)
- `get_helloasso_token()` : obtient le token OAuth2 de l'API HelloAsso via `grant_type=client_credentials`
- `can_refund()` : retourne `false` (les remboursements ne sont pas encore implémentés)

#### **classes/logger.php**
Gère tous les logs du plugin dans la table `payment_helloasso_logs`

**Méthodes principales :**
- `log_action($paymentid, $userid, $action, $status, $amount, $message, $response_code, $reference)` : enregistre une action
  - Actions : `payment_initiation`, `payment_return`, `token_request`, `webhook`, etc.
  - Status : `success`, `error`, `fraud_detected`
- `get_payment_logs($paymentid)` : récupère tous les logs d'un paiement spécifique
- `get_error_logs($limit)` : récupère les erreurs récentes (utile pour le monitoring)
- `get_fraud_alerts($limit)` : récupère les alertes de fraude (tentatives suspectes)

#### **classes/external/get_config_for_js.php**
Service web appelé par JavaScript lors de l'initiation du paiement

**Processus :**
1. Récupère le compte de paiement configuré pour HelloAsso
2. Récupère la configuration de la passerelle (org_slug, formid)
3. Calcule le coût du cours en centimes d'euros
4. **Crée une transaction de paiement** dans la table `payments` de Moodle
5. Génère l'URL de redirection HelloAsso avec :
   - `amount` : montant en centimes
   - `reference` : identifiant unique (PAY-{paymentid})
   - `backUrl` : URL de retour après succès
   - `cancelUrl` : URL si l'utilisateur annule
   - `email` : email de l'utilisateur
6. Enregistre un log de l'initiation
7. Retourne l'URL de redirection au JavaScript

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
- Générée via Grunt : `npx grunt amd --root=payment/gateway/helloasso`

#### **amd/build/gateways_modal.min.js.map**
- Fichier de mapping pour le débogage
- Permet de relier le code minifié au code source dans les outils de développement du navigateur

---

### Pages de retour utilisateur

#### **return.php**
Page de **retour après paiement réussi** sur HelloAsso

**Processus :**
1. Récupère les paramètres GET : `paymentid`, `sesskey`, `transactionid` (optionnel)
2. **Vérifie le sesskey** (protection anti-CSRF)
   - Si invalide : log de fraude + erreur 403
3. Charge l'enregistrement du paiement depuis la base de données
4. **(Optionnel)** Vérifie le statut du paiement auprès de l'API HelloAsso
5. **Débloque l'accès** au cours via `\core_payment\helper::deliver_order($payment)`
   - Inscrit l'utilisateur au cours
   - Déclenche les événements Moodle associés
6. Enregistre un log de succès
7. Affiche un message de confirmation avec `$OUTPUT->notification()`

#### **cancel.php**
Page affichée si l'utilisateur **annule** le paiement sur HelloAsso

**Processus :**
- Affiche simplement un message d'annulation
- N'enregistre pas de log (le paiement n'a pas été tenté)
- L'utilisateur peut retourner au cours et réessayer

#### **webhook.php**
Endpoint pour les **notifications webhook** de HelloAsso (optionnel, pour automatisation)

**Processus :**
1. Reçoit une notification POST de HelloAsso (ex: changement de statut de paiement)
2. **Vérifie la signature HMAC** avec le Client Secret pour garantir l'authenticité
   - Calcule : `hash_hmac('sha256', $payload, $clientsecret)`
   - Compare avec le header `X-HelloAsso-Signature`
3. Décode le JSON du payload
4. Récupère le `paymentid` depuis les métadonnées
5. Si le statut est `Paid`, appelle `deliver_order()` pour débloquer l'accès
6. Répond avec HTTP 200 pour confirmer la réception

**Note :** Ce webhook doit être configuré dans le back-office HelloAsso avec l'URL : `https://votresite.com/payment/gateway/helloasso/webhook.php`

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

### Étape 3 : Création de transaction (PHP)
7. Le service web (`classes/external/get_config_for_js.php`) :
   - Vérifie que l'utilisateur est connecté
   - Récupère le compte de paiement et la configuration HelloAsso
   - Calcule le montant en centimes
   - **Crée une entrée dans la table `payments`** avec statut "pending"
   - Génère l'URL HelloAsso avec tous les paramètres
   - Enregistre un log d'initiation
   - Retourne l'URL au JavaScript

### Étape 4 : Redirection vers HelloAsso
8. Le JavaScript reçoit l'URL et redirige l'utilisateur : `window.location.href = url`
9. L'utilisateur arrive sur la page de paiement HelloAsso

### Étape 5 : Paiement sur HelloAsso
10. L'utilisateur saisit ses informations de carte bancaire
11. HelloAsso traite le paiement
12. HelloAsso valide ou refuse le paiement

### Étape 6 : Retour sur Moodle
13. **Si succès** : HelloAsso redirige vers `return.php?paymentid=X&sesskey=Y`
14. **Si annulation** : HelloAsso redirige vers `cancel.php`

### Étape 7 : Validation et déblocage (return.php)
15. `return.php` vérifie le `sesskey` (sécurité)
16. Charge la transaction depuis la base de données
17. **(Optionnel)** Vérifie le statut auprès de l'API HelloAsso
18. **Appelle `\core_payment\helper::deliver_order($payment)`** qui :
    - Inscrit l'utilisateur au cours
    - Met à jour le statut du paiement à "success"
    - Déclenche l'événement `\core\event\payment_successful`
19. Enregistre un log de succès
20. Affiche le message de confirmation

### Étape 8 : Notification webhook (optionnel, asynchrone)
21. HelloAsso envoie un POST vers `webhook.php` pour notifier le changement de statut
22. `webhook.php` vérifie la signature HMAC
23. Si valide, appelle également `deliver_order()` (idempotent, sans effet si déjà traité)
24. Répond HTTP 200

---

## Installation

### Prérequis
- Moodle 4.2 ou supérieur
- PHP 7.4 ou supérieur
- Compte HelloAsso avec accès API

### Étapes d'installation

1. **Télécharger le plugin**
   ```bash
   cd /chemin/vers/moodle/payment/gateway/
   git clone [URL_DU_REPO] helloasso
   # ou décompresser le ZIP dans payment/gateway/helloasso
   ```

2. **Se connecter à Moodle en tant qu'administrateur**
   - Aller dans **Administration du site → Notifications**
   - Moodle détecte le nouveau plugin et propose de l'installer
   - Cliquer sur "Mettre à jour la base de données"

3. **Configurer HelloAsso**
   - Aller sur [https://www.helloasso.com](https://www.helloasso.com)
   - Se connecter au back-office de votre association
   - Aller dans **Paramètres → API**
   - Créer un nouveau client API et noter :
     - Client ID
     - Client Secret
   - Créer un formulaire de paiement et noter :
     - Organization Slug (dans l'URL : `/associations/{slug}/...`)
     - Form ID (dans l'URL : `/formulaires/{id}/...`)

4. **Configurer le plugin dans Moodle**
   - Aller dans **Administration du site → Plugins → Passerelles de paiement → Gérer les passerelles de paiement**
   - Activer "HelloAsso"
   - Cliquer sur "Paramètres"
   - Renseigner :
     - Client ID
     - Client Secret
     - Organization Slug
     - Form ID

5. **Créer un compte de paiement**
   - Aller dans **Administration du site → Paiements → Comptes de paiement**
   - Cliquer sur "Créer un compte de paiement"
   - Nom : "HelloAsso"
   - Passerelles activées : cocher "HelloAsso"
   - Configurer les paramètres spécifiques si différents des paramètres globaux
   - Enregistrer

6. **Configurer un cours avec inscription payante**
   - Éditer un cours
   - Aller dans **Administration du cours → Utilisateurs → Méthodes d'inscription**
   - Ajouter "Inscription après paiement"
   - Configurer :
     - Compte de paiement : "HelloAsso"
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

### Activation du webhook (recommandé)

1. **Obtenir l'URL du webhook**
   ```
   https://votresite.com/payment/gateway/helloasso/webhook.php
   ```

2. **Configurer dans HelloAsso**
   - Back-office HelloAsso → Paramètres → API → Webhooks
   - Ajouter un nouveau webhook
   - URL : celle obtenue ci-dessus
   - Événements : cocher "Payment.Authorized", "Payment.Paid", "Payment.Refused"
   - Secret : utiliser le même Client Secret que pour l'API

3. **Tester le webhook**
   - HelloAsso propose un outil de test dans le back-office
   - Vérifier les logs dans la table `payment_helloasso_logs`

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

```bash
cd /chemin/vers/moodle
npx grunt amd --root=payment/gateway/helloasso
```

### Incrémenter la version

Après chaque modification, éditer `package.json` :
```php
build: 2024122801; // Format: AAAAMMJJXX
```

Puis aller dans **Administration du site → Notifications** pour appliquer la mise à jour.

### Structure des logs

Chaque action enregistre :
- `paymentid` : ID de la transaction Moodle
- `userid` : ID de l'utilisateur concerné
- `action` : type d'action (`payment_initiation`, `payment_return`, `token_request`, etc.)
- `status` : `success`, `error`, ou `fraud_detected`
- `amount` : montant en euros
- `reference` : référence HelloAsso (PAY-{id})
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
- Recompiler avec Grunt : `npx grunt amd --root=payment/gateway/helloasso`
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

---

## Sécurité

### Bonnes pratiques implémentées

1. **Vérification du sesskey** : protection anti-CSRF sur toutes les pages de retour
2. **Signature HMAC des webhooks** : vérification de l'authenticité des notifications HelloAsso
3. **Logs complets** : traçabilité de toutes les actions et détection de fraude
4. **Validation des montants** : vérification que le montant est positif et cohérent
5. **Gestion des permissions** : seuls les managers peuvent configurer la passerelle
6. **Pas de stockage de données sensibles** : pas de numéros de carte en base

### Points d'attention

- **Client Secret** : ne jamais exposer dans le code JavaScript ou les logs
- **Webhook** : utiliser HTTPS obligatoirement pour l'URL du webhook
- **IP whitelisting** : envisager de filtrer les IPs autorisées pour les webhooks

---

## Licence

GNU GPL v3 ou ultérieure

---

## Support

Pour toute question ou problème :
1. Consulter les logs dans `payment_helloasso_logs`
2. Activer le débogage Moodle
3. Vérifier la documentation HelloAsso : [https://api.helloasso.com/v5/swagger](https://api.helloasso.com/v5/swagger)

---

## Auteur

Développé pour l'intégration de HelloAsso dans Moodle.


