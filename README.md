# Moodle HelloAsso Payment Gateway

This plugin allows you to use HelloAsso as a payment gateway in Moodle.

## Configuration

1. Go to Site administration > Plugins > Payment gateways > HelloAsso.
2. Fill in your HelloAsso API Client ID, Client Secret, and organization slug.
3. Replace 'yourformid' in `classes/gateway.php` with your real HelloAsso form ID.

## Usage

When users pay for an item (course or other), they will be redirected to HelloAsso.
After payment, HelloAsso returns them to Moodle.

## Note

This is a minimal integration. For production, add payment confirmation handling (webhook) and error management.