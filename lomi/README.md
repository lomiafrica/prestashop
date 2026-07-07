# lomi. for PrestaShop

Accept **PrestaShop 1.7+** payments with **lomi.** hosted checkout in **XOF**, **USD**, and **EUR**.

**Module name:** `lomi`  
**Version:** `1.0.0`

## Overview

The module connects your PrestaShop store to [lomi. hosted checkout](https://docs.lomi.africa/build/checkout):

1. Customer selects **lomi.** at checkout (payment step) and confirms.
2. PrestaShop creates a checkout session via the lomi. API (`POST /checkout-sessions`).
3. Customer is redirected to `checkout.lomi.africa` to pay.
4. PrestaShop confirms payment via **webhook** (`PAYMENT_SUCCEEDED`) and/or **return URL** (`/module/lomi/callback`).
5. Order is created with status **Paid via lomi.**

Checkout displays a **lomi. branding card** (pay-with image + payment method icons), aligned with WooCommerce and Magento plugins.

## Requirements

- PrestaShop **1.7.x** or **8.x**
- PHP with **cURL** enabled
- Store currency **XOF**, **USD**, or **EUR** (default shop currency or cart currency)
- **HTTPS** in production (required for webhooks and secure return URLs)
- At least one **shipping carrier** configured (standard PrestaShop checkout requirement)
- lomi. account, [dashboard.lomi.africa](https://dashboard.lomi.africa)

## Installation

1. Download or clone this repository and locate the **`lomi`** folder (the folder that contains `lomi.php`).
2. Zip the **`lomi`** folder itself (the archive root must be `lomi/`, not a parent directory).
3. In the back office: **Modules → Module Manager → Upload a module** → select the zip file.
4. Click **Install**, then **Enable** if needed.
5. Open **Configure** on the lomi. module and enter your API keys (see below).

### Enable payment for your currency

1. **International → Localization → Currencies**: ensure **EUR**, **USD**, or **XOF** is active.
2. **Payment → Preferences** (or **Payment → Payment methods** depending on your PrestaShop version), under currency restrictions, allow **lomi.** for the currencies you use.

If the secret key is missing or the currency is not supported, **lomi.** will not appear at checkout.

## Configuration

**Modules → Module Manager → search `lomi` → Configure**

The configuration page shows your **webhook URL**: copy it into the lomi. dashboard.

| Setting | Description |
|---------|-------------|
| **Test mode** | **Yes** = sandbox API (`sandbox.api.lomi.africa`); **No** = live API |
| **Test secret key** | `lomi_sk_test_…` from dashboard (test mode) |
| **Test public key (optional)** | Reserved; not required for hosted checkout |
| **Test webhook secret** | `whsec_…` from your **test** webhook endpoint |
| **Live secret key** | `lomi_sk_live_…` (when Test mode is **No**) |
| **Live public key (optional)** | Reserved; not required for hosted checkout |
| **Live webhook secret** | `whsec_…` from your **live** webhook endpoint |

Always click **Save** after pasting secrets. Re-paste the **full** value when updating a field (PrestaShop does not show existing secrets).

### Webhooks (required for reliable order confirmation)

1. Copy the **webhook URL** from the module configuration page. Examples:

   ```
   https://your-store.example/module/lomi/webhook
   ```

   or (without friendly URLs):

   ```
   https://your-store.example/index.php?fc=module&module=lomi&controller=webhook
   ```

   If PrestaShop is in a subfolder (e.g. `/prestashop/`), include it:

   ```
   https://your-store.example/prestashop/module/lomi/webhook
   ```

2. In [dashboard.lomi.africa](https://dashboard.lomi.africa) → **Developers → Webhooks**, create an endpoint:
   - **URL:** the URL from step 1
   - **Events:** at least **`PAYMENT_SUCCEEDED`**
   - **Mode:** **test** or **live** matching **Test mode** in PrestaShop

3. Copy the **signing secret** (`whsec_…`) into the matching field in the module (test or live).

4. Send a **Test webhook** from the dashboard, expect **HTTP 200** (empty response body is normal for test events).

**Important:** The webhook signing secret is **not** your API secret key (`lomi_sk_…`). Each webhook endpoint has its own `whsec_…`. Test and live webhooks use different secrets.

Webhook headers: `X-Lomi-Signature`, `X-Lomi-Event`: see [webhooks documentation](https://docs.lomi.africa/build/webhooks).

### Supported currencies

| Currency | Amount sent to lomi. API |
|----------|--------------------------|
| XOF | Whole francs (e.g. 505 F → `505`) |
| USD / EUR | Minor units / cents (e.g. 10.50 → `1050`) |

Other currencies hide the payment method at checkout.

## Customer flow

```
Checkout (step 3) → Pay with lomi. → Redirect to checkout.lomi.africa
    → Payment success
        → Webhook PAYMENT_SUCCEEDED → Order "Paid via lomi."
        → (and/or) Redirect to /module/lomi/callback → Order confirmation
```

Return URL pattern (generated automatically):

```
https://your-store.example/module/lomi/callback?id_cart=…&key=…
```

The webhook is the **reliable** path if the customer closes the browser before returning to your shop.

## Testing (sandbox)

1. Enable **Test mode** and enter the test secret key + test webhook secret.
2. Create a **test** webhook in the dashboard pointing to your store URL.
3. Add a product to the cart, complete checkout steps (address + **shipping carrier**), choose **lomi.**, and pay.
4. Use test card **`4242 4242 4242 4242`** (any future expiry, any CVC).

### Expected results

| Test | Expected result |
|------|-----------------|
| Dashboard **Test webhook** | **200**: empty body (event is not `PAYMENT_SUCCEEDED`) |
| Real sandbox payment | Webhook **PAYMENT_SUCCEEDED** → **200**; order status **Paid via lomi.** |

More test cards: [Sandbox payments](https://docs.lomi.africa/start/sandbox-payments).

## FAQ

### Which API base URL is used?

| Test mode | API base |
|-----------|----------|
| Yes | `https://sandbox.api.lomi.africa` |
| No | `https://api.lomi.africa` |

### Why is lomi. missing at checkout?

- Module not installed or not active
- Secret key empty for the current mode (test/live)
- Shop or cart currency is not XOF, USD, or EUR
- Currency not allowed for lomi. in **Payment → Preferences**
- Cart missing address or shipping step incomplete

### Why is my order not created / not paid?

- Webhook secret mismatch → **401** (check **Advanced parameters → Logs**)
- Webhook not subscribed to **`PAYMENT_SUCCEEDED`**
- Checkout session not **`completed`** on lomi. API yet
- Customer abandoned checkout before payment
- Webhook URL not reachable from the internet

### Webhook returns 200 but nothing happens

Test events (`test.webhook`) return **200** with no body by design, they do not create orders. Place a real sandbox payment to trigger **`PAYMENT_SUCCEEDED`**.

### Are refunds supported from PrestaShop admin?

Process refunds from the [lomi. dashboard](https://dashboard.lomi.africa). Automatic PrestaShop refunds are not included in this release.

### Upgrading from another payment module

Uninstall the old gateway if it conflicts, then install lomi. and re-enter API keys. Existing orders from the previous module are not migrated.

## Troubleshooting

| Symptom | Likely cause | Action |
|---------|--------------|--------|
| Webhook **401** | Wrong `whsec_…` or test/live mismatch | Copy secret from the webhook endpoint matching **Test mode** |
| Webhook **405** | GET request (browser) | Webhooks must be **POST**: use dashboard test or lomi. delivery |
| **200** on test webhook, no order | Normal for test events | Complete a sandbox payment |
| “No carrier available” | PrestaShop shipping | Configure a carrier and delivery zone |
| Payment OK on lomi., order missing | No webhook | Fix webhook URL and secret first |

Check logs: **Advanced parameters → Logs**: filter by module `lomi` or search for `lomi.`.

## French guide

Merchant documentation in French: **[docs/GUIDE.fr.md](docs/GUIDE.fr.md)**.

## Links

- [lomi. dashboard](https://dashboard.lomi.africa)
- [API & checkout docs](https://docs.lomi.africa)
- [Sandbox payments](https://docs.lomi.africa/start/sandbox-payments)

## License

MIT, see repository license file if present.
