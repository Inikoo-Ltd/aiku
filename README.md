<p align="center">
  <img src="public/art/logo-sketch.svg" alt="aiku" width="140">
</p>

<h1 align="center">aiku</h1>

<h3 align="center">The open source operating system for commerce.</h3>

<p align="center"><a href="https://aiku.io">aiku.io</a> · <a href="https://aiku.io/blog">Engineering notes</a></p>

<p align="center">
  One platform to run wholesale, retail, dropshipping, marketplaces, 3PL fulfilment and your own storefronts —<br>
  across many companies, warehouses, countries and currencies.
</p>

<p align="center">
  <a href="https://github.com/Inikoo-Ltd/aiku/actions/workflows/testing.yml"><img src="https://github.com/Inikoo-Ltd/aiku/actions/workflows/testing.yml/badge.svg?branch=main" alt="Tests"></a>
  <a href="https://codecov.io/gh/Inikoo-Ltd/aiku"><img src="https://codecov.io/gh/Inikoo-Ltd/aiku/branch/main/graph/badge.svg?token=12HMR5XCOW" alt="codecov"></a>
  <a href="https://sonarcloud.io/summary/new_code?id=Inikoo-Ltd_aiku"><img src="https://sonarcloud.io/api/project_badges/measure?project=Inikoo-Ltd_aiku&metric=alert_status" alt="Quality gate"></a>
  <a href="https://app.codacy.com/gh/Inikoo-Ltd/aiku/dashboard"><img src="https://app.codacy.com/project/badge/Grade/0b5334200ff14749b5fce8288e545abb" alt="Codacy"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-AGPL--3.0-blue.svg" alt="AGPL-3.0"></a>
</p>

<p align="center">
  <img src="public/art/readme/draw-dashboard.svg" alt="Group dashboard (sketch)" width="900">
</p>

---

## Why aiku

Most businesses glue together an ERP, a WMS, a shop platform, a marketplace connector, a mailing tool and a CRM — then spend their lives reconciling them. aiku is all of those, built as **one system with one source of truth**: a product is defined once and sold everywhere, stock moves once and every channel knows, a customer is one record whether they bought on your website, on Amazon or through your sales team.

It is not a demo. aiku runs real multi-country wholesale and retail operations in production every day, and it's released under the AGPL so you can run it too.

## Everything, in one place

### 🏢 Multi-entity by design
One **group**, many **organisations**, **shops**, **warehouses** and **brands**. Share masters, stock and staff across entities; keep pricing, tax, invoicing and websites separate per market. Multi-currency with live exchange rates, multi-language everywhere.

### 🧩 Masters & catalogue
Define products once at group level — master products, families, departments, trade units, barcodes, ingredients and GPSR data — and publish them to as many shop catalogues as you like, each with its own pricing, RRPs and availability.

### 🛒 Ordering & billing
B2B and B2C orders, baskets, pricing rules, offers and discounts, customer credit, VAT handling, invoices, credit notes, refunds, proformas. Orders flow straight into the warehouse without re-keying.

<p align="center"><img src="public/art/readme/draw-orders.svg" alt="Orders (sketch)" width="800"></p>

### 🏭 Warehouse & dispatch
Locations, stock levels, stock valuation, lost & found. Delivery notes, picking routes and picking sessions, packing benches, boxes, trolleys, label printers — and a handheld app for the floor. Carrier APIs built in: **DPD** (GB, SK), **GLS** (ES, SK incl. cash on delivery), **APC**, **CTT**, **Packeta**, **ITD**.

<p align="center"><img src="public/art/readme/draw-warehouse.svg" alt="Warehouse (sketch)" width="800"></p>

### 📦 Procurement & goods in
Suppliers and agents, purchase orders, stock deliveries, supplier products, landed cost.

### 🏗️ 3PL fulfilment
Run a fulfilment business for third parties: pallet storage, pallet deliveries and returns, stored items, recurring billing and fulfilment invoicing, with a customer portal for your 3PL clients.

### 🔗 Dropshipping & marketplaces
Let your customers sell your catalogue on their own channels — or sell it yourself — with two-way sync of products, orders and shipments:

<p align="center">
  <img src="public/assets/channel_logo/shopify.svg" height="36" alt="Shopify">&nbsp;&nbsp;&nbsp;
  <img src="public/assets/channel_logo/woocommerce.svg" height="36" alt="WooCommerce">&nbsp;&nbsp;&nbsp;
  <img src="public/assets/channel_logo/magento.svg" height="36" alt="Magento">&nbsp;&nbsp;&nbsp;
  <img src="public/assets/channel_logo/tiktok.svg" height="36" alt="TikTok Shop">&nbsp;&nbsp;&nbsp;
  <img src="public/assets/channel_logo/amazon.svg" height="36" alt="Amazon">&nbsp;&nbsp;&nbsp;
  <img src="public/assets/channel_logo/ebay.svg" height="36" alt="eBay">&nbsp;&nbsp;&nbsp;
  <img src="public/assets/channel_logo/allegro.svg" height="36" alt="Allegro">
</p>

Plus **Faire** wholesale marketplace integration and a manual channel for everything else.

<p align="center"><img src="public/art/readme/draw-channels.svg" alt="Channels (sketch)" width="800"></p>

### 🌐 Websites & storefronts
A built-in CMS and storefront engine: drag-and-drop web blocks, banners, announcements, menus, merchandised search, SEO, GDPR pages, unsubscribe handling — server-side rendered and cached for speed. Launch a new shop website without touching code.

### 👥 Customer portal
Self-service for B2B, dropshipping and fulfilment customers: orders, invoices, pallets, channel connections, saved cards, top-ups, API keys.

### 💬 CRM, marketing & comms
Customers, prospects, web users, reviews, polls, back-in-stock reminders. Transactional and marketing email with a visual editor (BeeFree), mailshots, outboxes, SES delivery tracking. Live customer chat and internal staff chat. Marketing attribution by traffic source.


### 💳 Accounting & payments
Payments, payment accounts and gateways — Checkout.com, PayPal, Braintree, Hokodo, Pastpay, bank transfer, cash on delivery — invoice categories, top-ups, financial reports.

### 🧑‍💼 Human resources & production
Employees, clocking machines, timesheets, leave, job positions and roles. A manufacture module for artefacts, raw materials and job orders.

### 📊 Analytics, search & AI
Time-series metrics and dashboards, margins, exports. Group-wide instant search (Typesense / Meilisearch) and semantic search (pgvector). A first-party **MCP server** with permission-scoped tools so your AI assistants can answer questions about the business — safely.

## Yes, it's real

<p align="center">
  <img src="public/art/readme/thumb-group-dashboard.jpg" width="150" alt="">
  <img src="public/art/readme/thumb-orders.jpg" width="150" alt="">
  <img src="public/art/readme/thumb-invoices.jpg" width="150" alt="">
  <img src="public/art/readme/thumb-families.jpg" width="150" alt="">
  <img src="public/art/readme/thumb-customers.jpg" width="150" alt="">
  <img src="public/art/readme/thumb-employees.jpg" width="150" alt="">
  <img src="public/art/readme/thumb-marketing.jpg" width="150" alt="">
  <img src="public/art/readme/thumb-delivery-notes.jpg" width="150" alt="">
</p>
<p align="center"><sub>Screens from a demo group seeded with random data — regenerate with <code>devops/devel/readme_screenshots.sh</code>.</sub></p>

## The apps

| App | For | Built with |
|---|---|---|
| **Staff back office** | Operations, sales, warehouse, accounting, HR | Laravel · Inertia · Vue 3 |
| **Customer portal** | B2B, dropshipping and fulfilment customers | Laravel · Inertia · Vue 3 |
| **Storefronts / CMS** | Public shop websites | Laravel · Inertia · Vue 3 (SSR) |
| **Shopify embedded app** | Dropshipping channel setup | Laravel · Inertia · Vue 3 |
| **Warehouse handheld** | Picking, packing, scanning | React Native |
| **Staff mobile** | Clocking, tasks, chat | React Native |

## Under the hood

**PHP 8.4 · Laravel 13 · Octane · Horizon · Passport · Pennant · Scout · laravel/mcp · PostgreSQL 18 + pgvector · Redis · Typesense · Meilisearch · Vue 3 · Inertia v2 · Tailwind · Vite · TypeScript · React Native · Pest 4**

## Getting started

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm run dev
```

```bash
php artisan test --compact
```

## Contributing

Pull requests are welcome. See [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) and [SECURITY.md](SECURITY.md).

## License

GNU AGPLv3 — see [LICENSE](LICENSE).

Copyright (C) Raul A Perusquia Flores
