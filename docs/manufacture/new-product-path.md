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
4. Recipe          tasks in order + raw materials per step      [BUILT]
5. Pay & batch     piece rate, targets, recommended batch size  [BUILT]
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
- **Per-step raw material consumption**: `recipe_step_raw_materials`
  (artefact_manufacture_task_id, raw_material_id, quantity_per_unit).
  Editable per step with line costs and a subtotal; receiving a job order
  deducts these from the input org stocks. Waste percentage not modelled —
  consumption is assumed to equal the recipe. [BUILT]

### 5. Pay & batch [pay BUILT, batch size GAP]

- Piece-work salary per task: task_work_cost + lower/upper targets +
  reward terms already on ManufactureTask, snapshotted into every session.
  [BUILT] (bonus formula blocked on management answering what the targets
  mean.)
- **Recommended batch size**: `recommended_batch_size` on the artefact,
  imported from Aurora where it exists; job order items pre-fill from it
  and hint when the quantity deviates. [BUILT]

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

## Remaining build order

1. Compliance checklist model + artefact gate (needs management to define
   required items per category — ask, but the model can be built with the
   type list configurable).
2. Create-from-supplier raw material shortcut UI.
3. Sell-online shortcut (compliance-gated).
4. Waste percentage on recipe steps, if the floor shows recipe and reality
   diverge.

Done: recipe raw materials with costing and deduction, recommended batch
size, and the Aurora import that brings raw materials, artefacts, batch
sizes and recipe ratios in without anyone typing them.

## Open questions for management (add to questions doc)

- Which compliance documents/tests are required per product family, per
  market? Who owns keeping them current?
- Who assigns barcodes and tariff codes today, and from what range?
- What are the real recommended batch sizes per product family?

See also [pilot-checklist.md](pilot-checklist.md) for the rollout gates,
including dropping the manufacture fetchers from `allowed_fetchers` at
cutover so Aurora stops mastering artefacts.

---
*Living design doc; update as pieces land.*
