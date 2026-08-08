# New product full path — design

How a brand-new manufactured product goes from idea to sellable, compliant,
producible. This is the orchestration layer over pieces that mostly exist;
the gaps are marked. Status: DESIGN — nothing here is built yet except where
noted.

## The path

```
1. Identity        trade unit (new or existing)
2. Artefact        the factory's definition of the thing        [BUILT]
3. Raw materials   choose existing / create from supplier       [partially BUILT]
4. Recipe          tasks in order + raw materials per step      [tasks BUILT, materials GAP]
5. Pay & batch     piece rate, targets, recommended batch size  [pay BUILT, batch size GAP]
6. Compliance      tests, barcode, tariff code, label           [GAP]
7. Go live         producible on the floor + sellable online    [floor BUILT, product shortcut GAP]
```

### 1. Identity — trade unit

Every real product is a trade unit first: images, dimensions, ingredients,
barcode, tariff code all live there, and it is the bridge to selling
(trade unit → product → website) and to stock (trade unit → stock →
org stock). The path starts by creating the trade unit or picking an
existing one.

### 2. Artefact [BUILT]

Create the artefact linked to the trade unit and to the org stock that will
hold the produced units. Both pickers exist on the artefact form. If the
org stock doesn't exist yet, it is created from the trade unit (existing
goods flow).

### 3. Raw materials [partially BUILT]

Two routes, both ending in a raw material linked to trade unit + org stock:

- **Choose existing** — picker on the raw material form. [BUILT]
- **Create from supplier** — the material is something we buy: supplier
  product → org supplier product → stock/org stock (existing procurement
  flow), then a raw material linked to that org stock. Quantities and costs
  then flow in automatically (already live: raw materials derive quantity,
  status and cost from their org stock). The missing piece is a shortcut
  UI that runs this chain in one screen instead of three modules. [GAP]

### 4. Recipe [tasks BUILT, materials GAP]

Recipe = artefact ↔ ordered tasks ↔ raw materials. It orchestrates how an
artefact batch is produced.

- Tasks with position + units-per-artefact: recipe editor on the artefact
  page. [BUILT]
- **Per-step raw material consumption**: new pivot
  `manufacture_task_raw_materials` scoped to the artefact recipe step —
  (artefact_manufacture_task_id, raw_material_id, quantity_per_unit,
  waste_percentage). This is what lets a job order reserve/deduct inputs
  and cost a batch. [GAP — next model addition]

### 5. Pay & batch [pay BUILT, batch size GAP]

- Piece-work salary per task: task_work_cost + lower/upper targets +
  reward terms already on ManufactureTask, snapshotted into every session.
  [BUILT] (bonus formula blocked on management answering what the targets
  mean.)
- **Recommended batch size**: how many units one production run should
  make (mixing vats, oven trays, sanity). Proposed: `recommended_batch_size`
  on the artefact; job order creation pre-fills item quantity with it and
  warns when deviating. [GAP — one column + form field]

### 6. Compliance [GAP — the important part]

A product cannot be released to production or sale until its compliance
checklist is green. Proposed model: `artefact_compliance_items` —
(artefact_id, type, reference, document/attachment, valid_until, status).
Types, extensible per market:

- **Safety tests / certificates** — e.g. CPSR for cosmetics in the EU,
  stability/challenge tests, allergen declarations. Documents attached,
  with expiry dates that alert before lapsing.
- **Barcode** — EAN/GS1 assigned on the trade unit (barcode infra exists
  in goods domain); checklist item = "barcode assigned".
- **Tariff / HS code** — on the trade unit for customs; checklist item =
  "tariff code set". Needed before cross-border selling.
- **Label** — ingredients (INCI for cosmetics), warnings, net quantity,
  responsible-person address, per language of the market sold into.
- **Local-law extras** per org (UK vs SK vs ES may differ) — the type list
  is data, not code.

Gate: artefact gets a `compliance_status` (derived: green when all required
items valid, amber when something expires soon, red when missing/expired).
Releasing a job order for a red artefact requires an explicit supervisor
override; creating the website product requires green. Which items are
*required* per product category is configuration that management/QA must
own — added to the management questions.

### 7. Go live

- Producible: release job orders to the floor. [BUILT]
- Sellable: one-click "create product from this trade unit" into a chosen
  shop, gated on compliance green. [GAP — shortcut over existing product
  creation]

## Suggested build order

1. `manufacture_task_raw_materials` pivot + recipe editor extension (unlocks
   costing and future deduction; pure aiku, no dependencies).
2. `recommended_batch_size` on artefact + job order pre-fill (trivial).
3. Compliance checklist model + artefact gate (needs management to define
   required items per category — ask, but the model can be built with the
   type list configurable).
4. Create-from-supplier raw material shortcut UI.
5. Sell-online shortcut (compliance-gated).

## Open questions for management (add to questions doc)

- Which compliance documents/tests are required per product family, per
  market? Who owns keeping them current?
- Who assigns barcodes and tariff codes today, and from what range?
- What are the real recommended batch sizes per product family?

---
*Living design doc; update as pieces land.*
