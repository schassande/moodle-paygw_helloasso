# HelloAsso Payment Gateway Plugin for Moodle

Payment gateway plugin to integrate HelloAsso into Moodle for managing paid course enrollments.

---

**[🇫🇷 Version française ci-dessous](#plugin-helloasso-pour-moodle)**

---

## Overview

This plugin allows users to pay for course enrollment via HelloAsso, a French payment platform dedicated to associations. The plugin uses the **HelloAsso Checkout API v5** to create secure payment intents following best practices.

**Official documentation:** [https://dev.helloasso.com/docs/intégrer-le-paiement-sur-votre-site](https://dev.helloasso.com/docs/intégrer-le-paiement-sur-votre-site)

## Payment Flow

1. ✅ **POST request** to `https://api.helloasso.com/v5/organizations/{org-slug}/checkout-intents`
2. ✅ HelloAsso returns an `id` and a `redirectUrl` (valid for 15 minutes)
3. ✅ User is redirected to this `redirectUrl`
4. ✅ After payment, return to `returnUrl` with `checkoutIntentId`, `code=succeeded`, `orderId`
5. ✅ **Mandatory API verification** to prevent fraud
6. ✅ Validation and service delivery

## Plugin Architecture

### Basic Configuration Files

#### **version.php**
- Defines plugin metadata (version, Moodle compatibility, maturity)
- Allows Moodle to manage updates
- Must be incremented with each modification to force updates

#### **lib.php**
- Contains callback functions for Moodle
- `paygw_helloasso_payment_gateways()`: registers the HelloAsso gateway in Moodle's payment system
- Declares display name and component

#### **settings.php**
- Creates the global plugin configuration page in Moodle administration
- Defines configuration fields:
  - **clientid**: HelloAsso API Client ID
  - **clientsecret**: HelloAsso API Client Secret
  - **org_slug**: Organization identifier (slug)
  - **base_url**: Base URL (`helloasso.com` or `helloasso-sandbox.com`)
  - **debugmode**: Debug mode for detailed logging
- Accessible via: Site Administration → Plugins → Payment Gateways → HelloAsso

---

### Database

#### **db/install.xml**
- Defines the structure of the `paygw_helloasso_logs` table during installation
- Contains fields to log all actions:
  - Payment actions (initiation, success, failure)
  - Errors and HTTP response codes
  - Fraud detection (multiple attempts, suspicious IPs)
  - HelloAsso reference and amounts

#### **db/access.php**
- Defines plugin permissions/capabilities
- `paygw/helloasso:manage`: capability to manage the gateway (reserved for managers)
- Inherits permissions from `moodle/site:config`

#### **db/services.php**
- Declares web services (AJAX API) usable by JavaScript
- `paygw_helloasso_get_config_for_js`: web service that allows JavaScript to:
  - Create a payment transaction
  - Obtain the redirect URL to HelloAsso
  - Pass necessary parameters (amount, reference, return URLs)

---

### Main Classes

#### **classes/gateway.php**
**Central class** of the plugin that extends `core_payment\gateway`

**Main methods:**
- `get_supported_currencies()`: returns `['EUR']` (only currency supported by HelloAsso)
- `add_configuration_to_gateway_form($form)`: empty - configuration is global, not per account
- `validate_gateway_form($form, $data, $files, &$errors)`: empty - no specific validation
- `generate_payment_url($config, $paymentid, $amount, $useremail, $itemname, $payerinfo)`: **main method**
  - Obtains an OAuth2 token
  - Creates a checkout intent via POST to `/v5/organizations/{org-slug}/checkout-intents`
  - Sends: totalAmount, initialAmount, itemName, return URLs, metadata, payer information
  - Returns the HelloAsso redirect URL
- `initiate_payment($payment, $options)`: delegates to `generate_payment_url()`
- `get_helloasso_token()`: obtains OAuth2 token via `grant_type=client_credentials`
  - Automatically constructs the API URL from `base_url`
  - Token valid for approximately 30 minutes
- `get_API_url()`: returns `https://api.{base_url}` (dynamic construction)
- `can_refund()`: returns `false` (refunds not yet implemented)

#### **classes/logger.php**
Manages all plugin logs in the `paygw_helloasso_logs` table

**Main methods:**
- `log_action($paymentid, $userid, $action, $status, $amount, $message, $response_code, $reference)`: records an action
  - Actions: `payment_initiation`, `payment_return`, `token_request`, `checkout_intent_creation`, etc.
  - Status: `success`, `error`, `fraud_detected`, `cancelled`
- `get_payment_logs($paymentid)`: retrieves all logs for a specific payment
- `get_error_logs($limit)`: retrieves recent errors (useful for monitoring)
- `get_fraud_alerts($limit)`: retrieves fraud alerts (suspicious attempts)

#### **classes/external/get_config_for_js.php**
Web service called by JavaScript during payment initiation

**Process:**
1. Retrieves the payment account configured for HelloAsso
2. Retrieves global configuration (org_slug, clientid, clientsecret, base_url)
3. Calculates course cost
4. **Creates a payment transaction** in Moodle's `payments` table
5. Constructs payer information from Moodle profile:
   - email, firstName, lastName
   - city, country (if available)
6. Constructs item name (itemName) according to context
7. Calls `gateway::generate_payment_url()` to create checkout intent
8. Records an initiation log
9. Returns redirect URL to JavaScript

---

### JavaScript (AMD - Asynchronous Module Definition)

#### **amd/src/gateways_modal.js** (source)
Readable and maintainable ES6 JavaScript code

**Main function:**
- `process(component, paymentArea, itemId, description)`:
  - Automatically called when user clicks "Pay with HelloAsso"
  - Calls web service `paygw_helloasso_get_config_for_js` via Ajax
  - Retrieves redirect URL
  - Redirects user to HelloAsso with `window.location.href`

#### **amd/build/gateways_modal.min.js** (compiled)
- Minified and optimized JS version for production
- Automatically loaded by Moodle when displaying payment modal
- Generated via Grunt: `npm run build`

#### **amd/build/gateways_modal.min.js.map**
- Mapping file for debugging
- Allows linking minified code to source code in browser development tools

---

### User Return Pages

#### **return.php**
Page for **return after successful payment** on HelloAsso

**Process:**
1. Retrieves GET parameters: `paymentid`, `sesskey`, `checkoutIntentId`, `code`, `orderId`
2. **Verifies sesskey** (anti-CSRF protection)
   - If invalid: fraud log + 403 error
3. Loads payment record from database (`payments` table)
4. Verifies that `code === 'succeeded'`
5. **⚠️ IMPORTANT: Mandatory verification via API** (`verify_helloasso_payment()` function)
   - Obtains an OAuth2 token
   - Retrieves checkout intent: `GET /v5/organizations/{org-slug}/checkout-intents/{checkoutIntentId}`
   - Verifies that:
     - Checkout intent exists and contains an order.id
     - OrderId matches
     - Amount matches (order.amount.total)
     - A payment with "Authorized" or "Processed" status exists
     - Metadata matches (moodle_payment_id)
6. **Unlocks access** to course via `\core_payment\helper::deliver_order()`
   - Enrolls user in course
   - Triggers associated Moodle events
7. Records a success log
8. Displays confirmation message with `$OUTPUT->notification()`

**Enhanced security:**
- ✅ **NEVER** trusts URL parameters alone
- ✅ Complete verification via API before delivering service

#### **cancel.php**
Page displayed if user **cancels** payment on HelloAsso (`backUrl`)

**Process:**
1. Retrieves `paymentid` from URL parameters
2. **Records a cancellation log** in `paygw_helloasso_logs`
3. Displays cancellation message with return button
4. User can return to course and try again

#### **error.php**
Page displayed in case of **technical error** on HelloAsso (`errorUrl`)

**Process:**
1. Retrieves parameters: `paymentid`, `sesskey`, `checkoutIntentId`, `error`
2. Verifies sesskey
3. Loads payment from database
4. **Records a technical error log**
5. Displays error message
6. If debug enabled: displays technical details (error code, checkoutIntentId)

#### **webhook.php**
Endpoint for **webhook notifications** from HelloAsso (optional, not yet implemented)

---

### Language Files

#### **lang/en/paygw_helloasso.php**
All text strings in **English**

**Contains:**
- Form labels (`clientid`, `clientsecret`, etc.)
- Field descriptions (`clientid_desc`, `clientid_help`)
- User messages (`payment_success`, `payment_cancelled`, `payment_error`)
- Error messages (`missingconfig`, `invalidamount`, `paymentnotfound`)

#### **lang/fr/paygw_helloasso.php**
All text strings in **French**

**Contains:**
- Exact translation of the same keys as English version
- Moodle automatically loads language according to user preferences

---

## Complete Payment Flow

### Step 1: Initiation
1. **User clicks "Enroll in course"** in Moodle
2. Moodle displays payment gateway selection modal
3. User selects "HelloAsso"
4. Moodle automatically loads `amd/build/gateways_modal.min.js`

### Step 2: Preparation (JavaScript)
5. JavaScript calls web service `paygw_helloasso_get_config_for_js` via Ajax
6. Parameters sent: `component`, `paymentarea`, `itemid`

### Step 3: Transaction and checkout intent creation (PHP)
7. Web service (`classes/external/get_config_for_js.php`):
   - Verifies user is logged in
   - Retrieves payment account and HelloAsso configuration
   - Calculates amount
   - **Creates an entry in `payments` table** with "pending" status
   - Constructs payer information (email, name, first name, city, country)
   - Calls `gateway::generate_payment_url()` which:
     - Obtains an OAuth2 token
     - **Creates a checkout intent** via POST to HelloAsso API
     - Sends: totalAmount, initialAmount, itemName, returnUrl, backUrl, errorUrl, metadata, payer
     - Receives: checkoutIntentId and redirectUrl
   - Records initiation log with checkoutIntentId
   - Returns redirect URL to JavaScript

### Step 4: Redirect to HelloAsso
8. JavaScript receives URL and redirects user: `window.location.href = url`
9. User arrives at HelloAsso payment page (redirectUrl valid for 15 minutes)

### Step 5: Payment on HelloAsso
10. User enters credit card information
11. HelloAsso processes payment
12. HelloAsso validates or refuses payment

### Step 6: Return to Moodle
13. **If success**: HelloAsso redirects to `return.php?paymentid=X&sesskey=Y&checkoutIntentId=Z&code=succeeded&orderId=W`
14. **If cancellation**: HelloAsso redirects to `cancel.php?paymentid=X`
15. **If technical error**: HelloAsso redirects to `error.php?paymentid=X&sesskey=Y&error=code`

### Step 7: Validation and unlocking (return.php)
16. `return.php` verifies `sesskey` (security)
17. Loads transaction from database
18. Verifies that `code === 'succeeded'`
19. **⚠️ MANDATORY: Verification via HelloAsso API** (`verify_helloasso_payment()`)
    - Obtains a new OAuth2 token
    - Calls `GET /v5/organizations/{org-slug}/checkout-intents/{checkoutIntentId}`
    - Verifies complete payment integrity (amount, orderId, status, metadata)
20. **Calls `\core_payment\helper::deliver_order($payment)`** which:
    - Enrolls user in course
    - Updates payment status to "success"
    - Triggers `\core\event\payment_successful` event
21. Records success log with checkoutIntentId
22. Displays confirmation message

---

## Installation

### Prerequisites
- Moodle 4.2 or higher
- PHP 7.4 or higher
- HelloAsso account with API access

### Installation Steps

1. **Download the plugin**
Create a zip of the folder using the command:
   ```bash
   npm run zip
   ```
The zip file is created in the parent directory.

2. **Log in to Moodle as administrator**
   - Go to **Site Administration → Plugins → Install plugins**
   - Upload the zip file
   - Follow instructions to complete installation

3. **Configure HelloAsso**
   - Go to [https://www.helloasso.com](https://www.helloasso.com)
   - Log in to your association's back-office
   - Go to **Settings → API**
   - Create a new API client and note:
     - **Client ID**
     - **Client Secret**
   - Note your **Organization Slug** (in back-office URL: `/organizations/{slug}/...`)

4. **Configure the plugin in Moodle**
   - Go to **Site Administration → Plugins → Payment Gateways → Manage payment gateways**
   - Enable "HelloAsso"
   - Click "Settings"
   - Fill in:
     - **Client ID**: your HelloAsso Client ID
     - **Client Secret**: your HelloAsso Client Secret
     - **Organization Slug**: your organization identifier
     - **Base URL**:
       - Production: `helloasso.com`
       - Sandbox (testing): `helloasso-sandbox.com`

5. **Create a payment account**
   - Go to **Site Administration → Payments → Payment accounts**
   - Click "Create payment account"
   - Name: "HelloAsso Production" (or "HelloAsso Sandbox" for testing)
   - Enabled gateways: check "HelloAsso"
   - Save

6. **Configure a course with paid enrollment**
   - Edit a course
   - Go to **Course Administration → Users → Enrollment methods**
   - Add "Enrollment on payment"
   - Configure:
     - Payment account: "HelloAsso Production"
     - Enrollment fee: amount in euros
     - Currency: EUR
   - Save

7. **Test payment**
   - Log out
   - Log in with a student account (or create a test account)
   - Go to the course
   - Click "Enroll in course"
   - Select "HelloAsso"
   - Verify redirect to HelloAsso
   - Complete a test payment

---

## Advanced Configuration

### Sandbox mode (testing)

To test without making real payments:

1. **Create a HelloAsso sandbox account**
   - Go to [https://www.helloasso-sandbox.com](https://www.helloasso-sandbox.com)
   - Create a test organization
   - Generate sandbox API keys

2. **Configure plugin in sandbox mode**
   - In Moodle: Administration → Plugins → HelloAsso → Settings
   - Base URL: `helloasso-sandbox.com`
   - Client ID and Secret: those from sandbox
   - Debug mode: **enabled**

3. **Test a payment**
   - Use HelloAsso test cards
   - Sandbox payments have no real cost

### Logs and Monitoring

- **View logs**: direct SQL query or create an admin page
  ```sql
  SELECT * FROM mdl_paygw_helloasso_logs 
  ORDER BY timecreated DESC 
  LIMIT 100;
  ```

- **Monitor fraud**:
  ```sql
  SELECT * FROM mdl_paygw_helloasso_logs 
  WHERE status = 'fraud_detected' 
  ORDER BY timecreated DESC;
  ```

- **Analyze errors**:
  ```sql
  SELECT * FROM mdl_paygw_helloasso_logs 
  WHERE status = 'error' 
  ORDER BY timecreated DESC;
  ```

---

## Development

### Compile JavaScript after modifications

If you modify sources in amd then you must recalculate the minified version by running:

```bash
npm run build
```

### Increment version

After each modification:

1. Edit `package.json` and `version.php`:
```json
// package.json
"version": "0.5.0",
"build": "2025123000"
```

2. Rebuild package:
```bash
npm run zip
```

3. Deploy and go to **Site Administration → Notifications** to apply the update.

### Log Structure

Each action records:
- `paymentid`: Moodle transaction ID
- `userid`: Concerned user ID
- `action`: action type (`payment_initiation`, `payment_return`, `token_request`, etc.)
- `status`: `success`, `error`, or `fraud_detected`
- `amount`: amount in euros
- `reference`: HelloAsso reference (CHECKOUT-{checkoutIntentId})
- `message`: details or error message
- `response_code`: API HTTP code (200, 403, 500, etc.)
- `ip_address`: Client IP (for fraud detection)
- `timecreated`: Unix timestamp

---

## Troubleshooting

### Plugin doesn't appear in payment gateways
- Verify folder is named `helloasso` in `payment/gateway/`
- Purge all caches: **Site Administration → Development → Purge all caches**
- Check folder permissions (must be readable by web server)

### Error "TypeError: a.getConfigForJs is not a function"
- JavaScript file is not up to date
- Recompile with Grunt: `npm run build`
- Purge browser cache (Ctrl+Shift+Delete)
- Purge Moodle cache

### White screen after selecting HelloAsso
- Open browser JavaScript console (F12)
- Check network errors
- Verify web service `paygw_helloasso_get_config_for_js` is declared in **Site Administration → Server → Web services → Overview**
- Check PHP and JavaScript logs

### Payment doesn't validate after returning from HelloAsso
- Verify `sesskey` in URL (must match session)
- Verify `return.php` doesn't generate PHP errors
- Check logs in `paygw_helloasso_logs`
- Enable debugging: **Site Administration → Development → Debugging**

### Language strings appear as [[gatewayname]]
- Language files not loaded
- Verify files exist: `lang/en/paygw_helloasso.php` and `lang/fr/paygw_helloasso.php`
- Purge language string cache: **Site Administration → Language → Language packs → Purge cache**

### Error "Failed to obtain authentication token"
- Verify Client ID and Client Secret are correct
- Verify Base URL matches your environment
- Check logs in `paygw_helloasso_logs` (action: `token_request`)
- Test token manually with:
  ```bash
  curl -X POST https://api.helloasso.com/oauth2/token \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "grant_type=client_credentials&client_id=YOUR_ID&client_secret=YOUR_SECRET"
  ```

### HTTP 403 error when creating checkout intent
- Verify Organization Slug is correct
- Verify your API client has "Checkout" privilege
- Verify return URLs are publicly accessible
- Check HelloAsso logs for more details

### Payment is made but enrollment doesn't validate
- Verify `verify_helloasso_payment()` function doesn't return `false`
- Enable debug and check Moodle logs
- Verify sent metadata matches (moodle_payment_id)
- Verify sent amount matches received amount

---

## Security

### Implemented Best Practices

1. **Sesskey verification**: anti-CSRF protection on all return pages
2. **⚠️ Mandatory API verification**: **NEVER** trusts URL parameters alone
   - Checkout intent retrieval via API
   - Verification of amount, orderId, status, metadata
3. **Traceability metadata**: each payment contains `moodle_payment_id` and `moodle_user_id`
4. **Complete logs**: traceability of all actions (checkout intent creation, verification, success, errors)
5. **Amount validation**: verification that amount is positive and matches
6. **Permission management**: only managers can configure gateway
7. **No sensitive data storage**: no card numbers in database
8. **Ephemeral OAuth2 token**: new token obtained for each payment (limited duration)

### Points of Attention

- **Client Secret**: never expose in JavaScript code or logs (used server-side only)
- **HTTPS required**: all return URLs must be HTTPS
- **Expired checkout intent**: redirect URL is valid for 15 minutes only
- **Debugging in production**: disable debug mode in production to avoid leaking sensitive information

---

## Verification Checklist

Before going to production, verify:

- [ ] Client ID and Client Secret correct
- [ ] Organization Slug correct
- [ ] Base URL correct (`helloasso.com` for production)
- [ ] Payment account created and HelloAsso gateway enabled
- [ ] Course configured with "Enrollment on payment"
- [ ] Enrollment fee configured
- [ ] Test performed in sandbox mode
- [ ] Log verification: no errors
- [ ] Debug mode disabled in production
- [ ] HTTPS enabled on Moodle site
- [ ] Return URLs publicly accessible

## Resources and Documentation

- **Official HelloAsso Checkout Documentation**: [https://dev.helloasso.com/docs/intégrer-le-paiement-sur-votre-site](https://dev.helloasso.com/docs/intégrer-le-paiement-sur-votre-site)
- **API Reference**: [https://dev.helloasso.com/reference](https://dev.helloasso.com/reference)
- **Swagger API**: [https://api.helloasso.com/v5/swagger](https://api.helloasso.com/v5/swagger)
- **Moodle Payment API Documentation**: [https://docs.moodle.org/dev/Payment_API](https://docs.moodle.org/dev/Payment_API)

## Support

For any questions or issues:
1. Check logs in `mdl_paygw_helloasso_logs`
2. Enable Moodle debugging (DEVELOPER level)
3. Enable plugin debug mode
4. Check JavaScript console (F12)
5. Consult HelloAsso documentation
6. Open an issue on GitHub

---

## Roadmap

Possible features for future versions:

- [ ] Support for installment payments (terms)
- [ ] Refund management via API (`can_refund()`)
- [ ] Administration page to view logs
- [ ] Support for custom payer fields
- [ ] Payment statistics
- [ ] Transaction export
- [ ] Support for optional donations (containsDonation)

## Author

Sebastien Chassande-Barrioz

## License

GNU GPL v3 or later

---
---

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
- Définit la structure de la table `paygw_helloasso_logs` lors de l'installation
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
Gère tous les logs du plugin dans la table `paygw_helloasso_logs`

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
2. **Enregistre un log d'annulation** dans `paygw_helloasso_logs`
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
  SELECT * FROM mdl_paygw_helloasso_logs 
  ORDER BY timecreated DESC 
  LIMIT 100;
  ```

- **Surveiller les fraudes** :
  ```sql
  SELECT * FROM mdl_paygw_helloasso_logs 
  WHERE status = 'fraud_detected' 
  ORDER BY timecreated DESC;
  ```

- **Analyser les erreurs** :
  ```sql
  SELECT * FROM mdl_paygw_helloasso_logs 
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
- Consulter les logs dans `paygw_helloasso_logs`
- Activer le débogage : **Administration du site → Développement → Débogage**

### Les chaînes de langue apparaissent comme [[gatewayname]]
- Les fichiers de langue ne sont pas chargés
- Vérifier que les fichiers existent : `lang/en/paygw_helloasso.php` et `lang/fr/paygw_helloasso.php`
- Purger le cache des chaînes de langue : **Administration du site → Langue → Packs de langues → Purger le cache**

### Erreur "Failed to obtain authentication token"
- Vérifier que le Client ID et Client Secret sont corrects
- Vérifier que la Base URL correspond à votre environnement
- Vérifier les logs dans `paygw_helloasso_logs` (action: `token_request`)
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
1. Consulter les logs dans `mdl_paygw_helloasso_logs`
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
