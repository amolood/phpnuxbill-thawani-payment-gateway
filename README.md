# Thawani Payment Gateway for PHPNuxBill

Accept online payments through **[Thawani](https://thawani.om/)** (Oman) directly in [PHPNuxBill](https://github.com/hotspotbilling/phpnuxbill/). Customers are redirected to Thawani's secure hosted checkout, and their package is activated automatically once payment succeeds.

---

## Features

- Hosted Thawani checkout — customers pay on Thawani's secure page.
- Automatic package activation on successful payment.
- Live and Testing (UAT) modes, switchable from the settings page.
- Server-verified payments — status is always re-checked against Thawani before a package is granted (both on return and via webhook).
- Idempotent processing — a paid transaction is never double-activated.
- Correct currency handling — OMR prices are converted to baisa (1 OMR = 1000 baisa) with a minimum-amount guard.

---

## Requirements

| Requirement | Notes |
|-------------|-------|
| PHPNuxBill | Latest recommended |
| PHP | 8.0+ |
| Thawani merchant account | Get API keys from [merchant.thawani.om](https://merchant.thawani.om/) |

---

## Installation

### Option 1 — Plugin Manager (recommended)

1. PHPNuxBill admin → **Plugin Manager** (`/index.php?_route=pluginmanager`)
2. Paste the repo URL and click **Install**:
   `https://github.com/amolood/phpnuxbill-thawani-payment-gateway`

### Option 2 — Manual

Copy the contents of the `paymentgateway/` folder:

```
paymentgateway/thawani.php     →  system/paymentgateway/thawani.php
paymentgateway/ui/thawani.tpl  →  system/paymentgateway/ui/thawani.tpl
```

---

## Configuration

Go to **Payment Gateway → Thawani** and set:

| Field | Value |
|-------|-------|
| Stage | `Live` or `Testing` |
| Publishable Key | from your Thawani merchant dashboard |
| Secret Key | from your Thawani merchant dashboard |
| Live URL | `https://checkout.thawani.om/api/v1` |
| Testing URL | `https://uatcheckout.thawani.om/api/v1` |

Then add Thawani to your Mikrotik hotspot **walled garden** so unauthenticated users can reach the payment page:

```
/ip hotspot walled-garden
add dst-host=thawani.om
add dst-host=*.thawani.om
```

> 💡 **Always run one Testing transaction before going Live** to confirm the amount and flow.

---

## How it works

1. A customer orders a package and chooses Thawani → a checkout session is created and they are redirected to Thawani.
2. After paying (or cancelling), Thawani redirects them back to PHPNuxBill.
3. PHPNuxBill **re-queries Thawani** for the authoritative payment status; if `paid`, the package is activated.
4. If webhooks are enabled, Thawani also notifies the gateway server-to-server, which likewise re-verifies before activating.

### A note on amounts
PHPNuxBill stores prices in OMR. Thawani's API works in **baisa** (1 OMR = 1000 baisa), so the gateway multiplies the price by 1000 and sends an integer amount. The minimum accepted charge is **0.100 OMR**.

---

## Screenshots

**Settings page**

![Settings](2.png)

**Checkout**

![Checkout](3.png)

**Order / payment result**

![Result](4.png)

---

## License

See the repository's license terms.
