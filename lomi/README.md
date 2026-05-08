# lomi. — PrestaShop 1.7+ payment module

Hosted checkout via the [lomi.](https://lomi.africa) API (`POST /checkout-sessions`, `GET /checkout-sessions/{id}`).

## Install

1. Zip the `lomi` folder and upload it in **Back office → Modules → Upload a module**.
2. Configure **test/live secret keys** and **webhook signing secret** (URL is shown on the module configuration page).
3. Set shop currency to **EUR**, **USD**, or **XOF**.

## Webhook

Point your lomi. dashboard webhook to:

`https://YOUR-SHOP/index.php?fc=module&module=lomi&controller=webhook`

(or the friendly URL your shop generates for the webhook controller).

## Upgrading

If you used another payment gateway module for the same store, uninstall it before switching, then install this module and re-enter lomi. API keys.
