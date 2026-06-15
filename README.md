<div align="center">

# Thawani Payment Gateway for PHPNuxBill

**Accept online card payments through [Thawani](https://thawani.om/) (Oman) in your [PHPNuxBill](https://github.com/hotspotbilling/phpnuxbill/) hotspot &amp; PPPoE billing system.**

Customers are redirected to Thawani's secure hosted checkout, and their internet package is activated automatically the moment payment is confirmed.

![PHPNuxBill](https://img.shields.io/badge/PHPNuxBill-compatible-2ea44f)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4)
![Gateway](https://img.shields.io/badge/Thawani-Pay-0a7cff)
![License](https://img.shields.io/badge/license-GPL--3.0-blue)
![Version](https://img.shields.io/badge/version-2.0.0-informational)

</div>

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [How It Works](#how-it-works)
- [Amounts &amp; Currency](#amounts--currency)
- [Screenshots](#screenshots)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)
- [License](#license)

---

## Features

- 🔐 **Secure hosted checkout** — customers pay on Thawani's own page; no card data touches your server.
- ⚡ **Automatic activation** — the package is enabled instantly once payment is confirmed.
- 🔁 **Server-verified payments** — every outcome is re-checked against Thawani's API before a package is granted, on both customer return and webhook.
- 🛡️ **Idempotent** — a paid transaction is never activated twice.
- 💱 **Correct currency handling** — OMR prices are converted to baisa (1 OMR = 1000 baisa) with a minimum-amount guard.
- 🧪 **Live &amp; Testing modes** — switch between production and Thawani UAT from the settings page.

---

## Requirements

| Requirement | Details |
|-------------|---------|
| PHPNuxBill | Latest version recommended |
| PHP | 8.0 or higher |
| Thawani merchant account | API keys from the [Thawani Merchant Portal](https://merchant.thawani.om/) |

---

## Installation

### Option 1 — Plugin Manager *(recommended)*

1. In the PHPNuxBill admin panel, open **Plugin Manager**
   (`https://your-domain/index.php?_route=pluginmanager`).
2. Paste the repository URL and click **Install**:

   ```
   https://github.com/amolood/phpnuxbill-thawani-payment-gateway
   ```

### Option 2 — Manual

Copy the contents of the `paymentgateway/` folder into your installation:

| From (this repo) | To (your PHPNuxBill) |
|------------------|----------------------|
| `paymentgateway/thawani.php` | `system/paymentgateway/thawani.php` |
| `paymentgateway/ui/thawani.tpl` | `system/paymentgateway/ui/thawani.tpl` |

---

## Configuration

Open **Payment Gateway → Thawani** in the admin panel and fill in:

| Field | Value |
|-------|-------|
| **Stage** | `Live` for production, `Testing` for sandbox |
| **Publishable Key** | From your Thawani merchant dashboard |
| **Secret Key** | From your Thawani merchant dashboard |
| **Live URL** | `https://checkout.thawani.om/api/v1` |
| **Testing URL** | `https://uatcheckout.thawani.om/api/v1` |

Add Thawani to your Mikrotik hotspot **walled garden** so unauthenticated users can reach the payment page:

```rsc
/ip hotspot walled-garden
add dst-host=thawani.om
add dst-host=*.thawani.om
```

> [!IMPORTANT]
> Always complete one transaction in **Testing** mode before switching to **Live** to confirm the amount and the full payment flow.

---

## How It Works

```
Customer ──orders package──▶ PHPNuxBill ──creates session──▶ Thawani
   ▲                                                            │
   │                                              redirected to checkout
   │                                                            ▼
   └──────────── package activated ◀── re-verify status ◀── pays / cancels
```

1. The customer orders a package and selects **Thawani**; a checkout session is created and they are redirected to Thawani.
2. After paying (or cancelling), Thawani returns the customer to PHPNuxBill.
3. PHPNuxBill **re-queries Thawani** for the authoritative status — only a confirmed `paid` result activates the package.
4. If Thawani webhooks are enabled, the gateway is also notified server-to-server and re-verifies before activating, so payments are never missed.

---

## Amounts &amp; Currency

PHPNuxBill stores plan prices in **Omani Rial (OMR)**. Thawani's API works in **baisa**:

> **1 OMR = 1000 baisa**

The gateway converts each price to integer baisa before charging (e.g. `2.500 OMR → 2500 baisa`). The minimum charge accepted by Thawani is **0.100 OMR**; smaller amounts are rejected before a session is created.

---

## Screenshots

| Settings | Checkout | Result |
|:--------:|:--------:|:------:|
| ![Settings](2.png) | ![Checkout](3.png) | ![Result](4.png) |

---

## Troubleshooting

| Symptom | Likely cause &amp; fix |
|---------|----------------------|
| *"Admin has not yet setup Thawani…"* | One of the keys/URLs is empty — complete every field on the settings page. |
| Redirect to Thawani fails / blank page | Thawani not in the Mikrotik **walled garden**, or wrong API URL for the selected stage. |
| Wrong amount charged | Confirm the plan price is in OMR. The gateway converts OMR → baisa automatically; do **not** pre-multiply your prices. |
| Payment succeeds but package not active | Check the order in **Testing** mode first; ensure the customer's plan/router still exists. The gateway re-verifies with Thawani before activating. |
| *"Amount is below the minimum…"* | The plan price is under 0.100 OMR (Thawani's minimum). |

> 💡 Set a Telegram bot in PHPNuxBill **Settings** to receive gateway error notifications.

---

## Changelog

See [`changelog.txt`](changelog.txt). Current version: **2.0.0**.

---

## License

Released under the **GNU General Public License v3.0** — see [`LICENSE`](LICENSE).

---

<div align="center">

Made by [**Abdalrahman Molood**](https://github.com/amolood) · [amolood.com](https://amolood.com)

</div>
