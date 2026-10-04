# BeyondSure Wellness

> **Direct Selling & Wellness Commerce Platform with Automated Multi-Stream Compensation Engine**

BeyondSure Wellness is a production-grade web application built on **Laravel 12 (PHP 8.3)**, **Tailwind CSS**, and **Bootstrap 5**. It provides a complete digital ecosystem for direct selling wellness products, managing a binary network tree, automating multi-tier compensation settlements, enforcing real-time corporate solvency, and delivering enterprise audit reporting.

---

## Table of Contents
1. [Core Architecture & Business Logic](#core-architecture--business-logic)
2. [Key Features](#key-features)
   - [Member Experience](#member-experience)
   - [Admin Control Center](#admin-control-center)
3. [Compensation & Solvency Model](#compensation--solvency-model)
4. [Technology Stack](#technology-stack)
5. [Getting Started & Installation](#getting-started--installation)
6. [Default Credentials](#default-credentials)
7. [Enterprise Reports & Exports](#enterprise-reports--exports)
8. [Automated Testing & Code Quality](#automated-testing--code-quality)
9. [License](#license)

---

## Core Architecture & Business Logic

BeyondSure Wellness separates sales revenue from commissionable volume using a mathematically verified **Business Volume (BV)** split:

$$\text{Revenue} = \text{Product Procurement / Bundle Cost (40\%)} + \text{Compensation Pool BV (40\%)} + \text{Company Retained Margin (20\%) }$$

* **Product-Wise Individual GST**: Every catalog item can have its own GST percentage ($0\%$, $5\%$, $12\%$, $18\%$, or $28\%$) or inherit the company default. When plans are bundled, GST is allocated accurately per component.
* **Connected Binary Placement Tree**: New members are automatically placed into the binary tree using breadth-first search (`placeUnderSponsor`). Direct registrants without a referral code automatically fall under the **Admin / Company Root Account** downline.
* **4-Stream Synchronized Compensation Engine**:
  1. **Buyer Self Income ($10\%$)**: Paid directly back to the purchasing member on order settlement.
  2. **Direct Sponsor Income ($20\%$)**: Paid to the immediate sponsor on order settlement.
  3. **Level-Wise Matching Cascade ($55\%$ across customizable levels)**: Distributed to upline ancestors in the binary placement tree whenever left and right leg volumes match.
  4. **Leadership Rank Pool ($3\%$)**: Distributed across active Silver, Gold, Platinum, and Diamond rank qualifiers based on points share.
* **Global Solvency Circuit Breaker**:
  Total matching payouts across the entire network are strictly bound by the remaining cycle Pool BV budget:
  $$\text{Available Pool Budget} = \text{Cycle Pool BV} - \text{Self Paid} - \text{Sponsor Paid}$$
  Guarantees that the company never runs a deficit or overpays regardless of accumulated carry-forward BV bursts.

---

## Key Features

### Member Experience
* **Live Financial Dashboard**: Real-time breakdown of earnings (Self, Sponsor, Matching, Rank Pool), personal order count, wallet balance, and downline totals.
* **Visual Downline Tree**: Interactive binary tree representation showing left and right leg placements, direct recruits, total downline count, and active status.
* **Wallet & Ledger Passbook**: Transparent transaction audit trail detailing every rupee earned, TDS deductions, and withdrawal statuses.
* **Store & Plan Upgrades**: Product bundles and membership packages with itemized product contents, BV value, and GST invoices.
* **Bank & UPI Profile**: Secure submission and verification of bank account (IFSC, Account Number) and UPI ID for payouts.
* **Phone Verification**: OTP-based authentication system with throttled rate-limiting.

### Admin Control Center
* **Executive Solvency Dashboard**: Real-time health metrics, active distributors, pending approvals, and payout trends.
* **Catalog & Plans Management**:
  - Products catalog with individual GST rates, cost price, and retail price.
  - Plan bundle builder with live cost-cap target validation.
* **Genealogy & Member Explorer**: Search any distributor, inspect upline sponsor chains, view tree depth, and browse level-wise downlines.
* **Payment Verifications**: Manual bank transfer / UPI slip approval workflow with automated order status updates.
* **Cycle Settlements**: One-click cycle processing that matches leg volume, applies rank pool distributions, enforces solvency scaling, and credits member wallets.
* **Withdrawal Dispatch Batches**: Group approved payout requests into dispatch batches, export bank transfer sheets, and release payments in bulk.
* **Dynamic Compensation Configurator**:
  - Add or remove levels ($1$ to $25$) dynamically via the UI (`+ Add Level`).
  - Configure individual percentage per level with live solvency check warnings.
  - Set weekly and monthly personal earnings caps.

---

## Compensation & Solvency Model

### Default Compensation Split (% of BV)
| Stream | Default % | Trigger & Rule |
| :--- | :---: | :--- |
| **Buyer Self Income** | $10\%$ | Immediately credited on settled order |
| **Sponsor Income (L1)** | $20\%$ | Paid to direct sponsor on settled order |
| **Level 1 Matching** | $15\%$ | Matching member / direct placement upline |
| **Level 2 Matching** | $12\%$ | Generation 2 placement ancestor |
| **Level 3 Matching** | $10\%$ | Generation 3 placement ancestor |
| **Level 4 Matching** | $8\%$ | Generation 4 placement ancestor |
| **Level 5 Matching** | $5\%$ | Generation 5 placement ancestor |
| **Level 6 Matching** | $3\%$ | Generation 6 placement ancestor |
| **Level 7 Matching** | $2\%$ | Generation 7 placement ancestor |
| **Leadership Rank Pool** | $3\%$ | Distributed to Silver, Gold, Platinum, Diamond |
| **Reserve Buffer** | $12\%$ | Retained company liquidity |
| **Total Pool BV** | **$100\%$** | **Fully Self-Funded Solvency** |

> **Level Depth Rules**: Levels are counted **upward from the purchasing distributor**. If an 8th level is needed, administrators can simply click **"+ Add Level"** in `Settings`, configure Level 8's percentage, and the engine will immediately cascade through 8 ancestors.

---

## Technology Stack

- **Backend Framework**: [Laravel 12](https://laravel.com)
- **Language**: PHP 8.3+
- **Database**: MySQL 8.0+ (Production) / SQLite `:memory:` (Testing)
- **Styling & UI**: Tailwind CSS (Member Portal) + Bootstrap 5 (Admin Control Center) + Bootstrap Icons
- **Asset Bundling**: [Vite](https://vitejs.dev)
- **Testing**: [PHPUnit 11](https://phpunit.de)
- **Code Formatter**: [Laravel Pint](https://laravel.com/docs/pint)

---

## Getting Started & Installation

### 1. Prerequisites
Ensure your local development environment has:
- PHP >= 8.3 with `pdo_mysql`, `sqlite3`, `bcmath`, `mbstring`, `curl`, `xml`, `openssl`
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) (v18+) & NPM
- MySQL Server (e.g., via Laragon, Valet, or Docker)

### 2. Clone Repository
```bash
git clone <repository-url> beyondsure-wellness
cd beyondsure-wellness
```

### 3. Install PHP & Node Dependencies
```bash
composer install
npm install
```

### 4. Configure Environment
```bash
cp .env.example .env
php artisan key:generate
```
Edit `.env` with your database credentials:
```env
APP_NAME="BeyondSure Wellness"
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=beyondsure
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Run Migrations & Seed Database
```bash
php artisan migrate --seed
```

### 6. Build Frontend Assets
```bash
npm run build
```
*(Or run `npm run dev` for hot-module reloading during development)*

### 7. Launch Local Server
```bash
php artisan serve
```
Visit the application at: `http://127.0.0.1:8000`

---

## Default Credentials

### Administrator Account
- **URL**: `http://127.0.0.1:8000/admin/login`
- **Email**: `admin@beyondsure.test`
- **Password**: `password`

### Company Root Distributor
- **Member Code**: `BS100001`
- **Phone**: `9820011000`
- **Name**: `Rajesh Sharma`

---

## Enterprise Reports & Exports

All admin reports include query-string filter preservation (`from`, `to`), dedicated table pagination parameters to prevent pagination collisions, and complete unpaginated streaming CSV exports:

| Report Section | Route | Capabilities & Data Tables |
| :--- | :--- | :--- |
| **Business Overview** | `/admin/reports/overview` | Executive revenue KPIs, target vs. actual split, 6-month growth trend (`trend_page`). |
| **Sales & Products** | `/admin/reports/sales` | Plan revenue & margin (`plans_page`), Top Buyers (`buyers_page`), Itemized purchase logs with GST (`purchases_page`). |
| **Repurchase Report** | `/admin/reports/repurchase` | Repeat revenue, repurchase ratio %, repeat orders log (`orders_page`), Top repeat buyers (`buyers_page`), Plan breakdown (`plans_page`). |
| **Payouts & Cycles** | `/admin/reports/payouts` | Approved cycle settlements (`cycles_page`), distribution by compensation category, Top earners (`earners_page`). |
| **Level Distribution** | `/admin/reports/levels` | Executive compensation framework, simulation matrix, generation earners ledger (`earners_page`). |
| **Pending & Cash** | `/admin/reports/pending` | Payment aging buckets, payments awaiting verification (`payments_page`), withdrawals awaiting dispatch (`withdrawals_page`), unsettled orders (`unsettled_page`). |
| **Compliance & Audit** | `/admin/reports/compliance` | Monthly ledger reconciliation (`months_page`), overpaid cycle monitors (`overpaid_page`), plan cost-cap target alerts (`cost_cap_page`). |

---

## Automated Testing & Code Quality

BeyondSure Wellness maintains a comprehensive automated test suite covering compensation accuracy, zero-overpayment solvency guarantees, edge-case downline placements, form validations, and report pagination.

### Run PHPUnit Test Suite
```bash
vendor/bin/phpunit
```
*(All 47 tests run on an isolated in-memory SQLite database)*

### Run Laravel Pint Code Formatter
```bash
vendor/bin/pint --format agent
```

### Clear Application & View Caches
```bash
php artisan optimize:clear
```

---

## License

This software is proprietary and confidential to **BeyondSure Wellness**. All rights reserved.
