# AW Aromatics — Production Reward System

## Specification for transfer to Aiku

**Source file:** `Updated New Production Sheet Aug26.xlsx` (Google Drive `14TOhU75QnByLUVtOmn8OZRKZ8uPT8weQ`) **Owner:** [adam@ancientwisdom.biz](mailto:adam@ancientwisdom.biz) · **Last modified:** 11 Aug 2026 · **Period in file:** 3 Aug – 6 Sep 2026 (5 weeks, Week 1 populated only) **Prepared:** 11 Aug 2026 · **Currency:** GBP throughout

Every figure marked **\[F\]** is a fact read directly from the workbook. **\[C\]** is calculated by me from workbook data and reconciles to the sheet. **\[A\]** is an assumption. **\[E\]** is an estimate.

---

## Part 0 — Executive conclusion

**Three things your developer needs to understand before writing a line of code.**

**1\. This is not a piecework system.** Nobody is paid per unit. Pay is `hours worked × an hourly rate`, where the hourly rate is selected by comparing the operator's achieved units-per-hour against four published targets for that job. Piece rates exist in the workbook only as the *seed number that generates the targets* — they are never used to pay anyone. Anyone who builds this as "£ per unit × units" will build the wrong system.

**2\. The tier ladder is cost-neutral by construction, and that is the design's best feature.** The targets rise in exactly the same proportion as the pay rates, so labour cost per unit is the same whether an operator hits tier 0 or tier 3\. Median tier-3 cost per unit is **0.995×** tier-0 cost per unit across 364 families **\[C\]**. The bonus is self-funding. Preserve this property explicitly in Aiku — put it in a unit test — because it is currently an emergent accident of two formulas and it will be broken the first time somebody hand-types a target.

**3\. Aiku already has the data model.** `manufacture_tasks` carries `task_lower_target`, `task_upper_target`, `task_work_cost`, `operative_reward_terms`, `operative_reward_allowance_type`, `operative_reward_amount`; `manufacture_task_sessions` carries `employee_id`, `started_at`, `ended_at`, `quantity_made`, `quantity_rejected` plus a snapshot of the reward terms **\[F\]**. This is a close fit. The one structural gap is that Aiku holds **two** targets and the spreadsheet uses **four bands**. That decision — extend Aiku to N bands, or reduce the scheme to two — is the single biggest design question in this project and it should be settled before development starts.

**The commercial prize is not the bonus mechanism. It is the measurement gap.** In Week 1, **9.9% of all paid production hours (38.0 of 383.3) were booked as "Non Working Hours" with no output recorded** **\[C\]** — roughly **£25,100 a year** of paid time that the system cannot see **\[E\]**. A further **29.4 hours per week of paid time produced below even the lowest published target**, worth about **£19,400 a year** **\[E\]**. Automating the sheet without closing those two gaps converts a manual blind spot into an automated one.

---

## Part A — How the current system works (as-is)

### A1. Vocabulary

| Sheet term | Meaning | Unit |
| :---- | :---- | :---- |
| **Family** (Product Family) | A *job*, not a product. "ABB1 \= Bath Potion Bath Bombs – Single Mix – Making". Making and packing the same product are separate families. | code |
| **Product** | A specific SKU/batch code (e.g. `EO-28 - Tactile`). 3,314 products map to 410 families. | code |
| **Piece Rate (Old)** | Legacy £-per-unit rate. **Used only to generate targets. Never used to pay.** | £/unit |
| **Tier / Band 0,1,2,3** | Performance band achieved on a job. 0 \= below standard, 3 \= highest. | integer |
| **Target Rate** | Units per paid hour required to reach each band. | units/hour |
| **£ RATE** | The hourly rate the band pays. | £/hour |
| **PAY** | `hours × £ RATE` for that job row. | £ |
| **Bonus** | `PAY − (NLW × hours)`. Reporting only — not paid separately. | £ |
| **D / DG** | Development / Guru-Development rate bands, used for non-standard work. | band |
| **Guru** | A per-person flag adding a flat £1.00/hour to all hours. | boolean |

### A2. Reference data — three tables

**(i) Band rates** — `Personnel!A4:G7` **\[F\]**

| Band | 0 | 1 | 2 | 3 | D | DG |
| :---- | :---- | :---- | :---- | :---- | :---- | :---- |
| £/hour | **12.71** | 13.00 | 14.00 | 15.00 | 13.30 | 15.00 |
| Uplift over NLW | £0.00 | £0.29 | £1.29 | £2.29 | £0.59 | £2.29 |
| Uplift % | 0.0% | \+2.3% | \+10.1% | \+18.0% | \+4.6% | \+18.0% |

`NLW = £12.71` is held once in `Personnel!B2` and referenced everywhere **\[F\]**. Band 0 pays **exactly** the National Living Wage — there is no downside band. Confirm £12.71 against the current statutory rate before go-live; I could not verify it against gov.uk in this session.

**(ii) Target-generation constants** — `Product Families!C1:H1` **\[F\]**

`C1 = 13` (the "target rate", £/hour, used to convert a piece rate into a target) `D1:G1 = 12.71 / 13 / 14 / 15` (live band rates, pulled from Personnel by XLOOKUP) `H1 = 16` — drives the "Recommended £/unit" column off a £16/hour figure that **appears nowhere else in the workbook and is not paid to anyone** **\[F\]**.

**(iii) The job master** — `Product Families`, 410 rows: `Family | Description | Piece Rate (Old) | Target 0 | Target 1 | Target 2 | Target 3`.

### A3. The calculation chain

STEP 1  Generate targets from the legacy piece rate          \[Product Families\]

        target\_0 \= ROUNDDOWN( 13.00 / piece\_rate )

        target\_1 \= ROUNDUP( target\_0 × 13.00 / 12.71 )

        target\_2 \= ROUNDUP( target\_0 × 14.00 / 12.71 )

        target\_3 \= ROUNDUP( target\_0 × 15.00 / 12.71 )

STEP 2  Inherit targets onto every product                   \[Product List\]

        product.target\_n \= XLOOKUP(product.family, Product Families)

STEP 3  Operator books a job row                             \[Week n \- Making\]

        Name, Product, Batch Code, Start Time, Breaks, Finish Time, Amount Made

STEP 4  Derive hours

        total\_time \= finish − start − breaks          (hh:mm)

        hours      \= ROUND(total\_time × 24, 6\)        (decimal hours)

STEP 5  Derive achieved rate

        made\_per\_hour \= ROUND( amount\_made / hours , 0 )      ← rounded to whole units

STEP 6  Assign the band                                       ← THE CORE RULE

        if product \== "Development"        → band D

        elif LEFT(product,3) \== "ZZD"      → band DG

        elif LEFT(product,3) \== "AAD"      → band D

        elif product \== "Maintenance"      → band 2   (forced, no measurement)

        elif made\_per\_hour \>= target\_3     → band 3

        elif made\_per\_hour \<  target\_1     → band 0

        elif made\_per\_hour \<  target\_2     → band 1

        else                               → band 2

STEP 7  Pay the row

        hourly\_rate \= lookup(band)

        pay         \= ROUND(hours × hourly\_rate, 2\)

        bonus       \= ROUND(pay − NLW × hours, 2\)      (reporting only)

STEP 8  Aggregate to the week                                \[Personnel / Payroll\]

        hours     \= Σ hours across all rows for the person

        basic     \= ROUNDUP(hours × NLW, 2\)

        guru      \= hours × £1.00   if person.guru \= Yes  else 0

        total\_pay \= basic \+ Σ band uplifts \+ guru \+ holidays \+ SSP

        eff\_rate  \= total\_pay / hours

I re-implemented STEP 6 independently against all 239 Week-1 job rows: **239 of 239 band assignments reproduce exactly, zero mismatches** **\[C\]**. The rule above is complete and correct.

**Note the band boundaries carefully** — they are half-open and the tier-0 target is never tested:

made\_per\_hour  \<  target\_1              → band 0

target\_1  ≤  made\_per\_hour  \<  target\_2 → band 1

target\_2  ≤  made\_per\_hour  \<  target\_3 → band 2

target\_3  ≤  made\_per\_hour              → band 3

`target_0` is printed on the operator's sheet and displayed in the "Target Rates" block but **is not used in any calculation** **\[F\]**. An operator achieving 99% of target and one achieving 10% of target are treated identically.

### A4. Worked examples — all three verified against the live sheet

**Example 1 — top band.** Beata Przybyla, `EO-28 - Tactile`, 08:00→13:26, 30 min break, 900 made. `hours = 4.933333` · `made_per_hour = ROUND(900/4.933333,0) = 182` · targets `152/156/168/180` · `182 ≥ 180` → **band 3** → £15.00/hr → `pay = £74.00` · `bonus = 74.00 − 12.71×4.933333 = £11.30` **\[F\]**

**Example 2 — band 0, no bonus.** Georgeta Videanu, `ACLB-05 - Pouring (Single)`, 12:30→14:30, no break, 380 made. `hours = 2.0` · `made_per_hour = 190` · targets `216/221/238/255` · `190 < 221` → **band 0** → £12.71/hr → `pay = £25.42` · `bonus = £0.00` **\[F\]** Note she was 26 units/hour *below the tier-0 target* and 31 below tier 1 — the system records both cases identically.

**Example 3 — middle band.** Zofia Baka, `AWFO-29 - Tactile`, 2.5 h, 40 made. `made_per_hour = 16` · targets `14/15/16/17` · `16 < 17`, `16 ≥ 15`, `16 ≥ 16` → **band 2** → £14.00/hr → `pay = £35.00` · `bonus = £3.23` **\[F\]** With targets one unit apart, a single unit of output moves the operator two bands. This is the small-integer problem — see D5.

### A5. The cost-neutrality property — the algebra to preserve

cost\_per\_unit(band n) \= rate\_n / target\_n

                      \= rate\_n / ( target\_0 × rate\_n / 12.71 )

                      \= 12.71 / target\_0                       ← independent of n

                      \= 12.71 × piece\_rate / 13.00

                      \= 0.9777 × piece\_rate

Measured across 364 families with complete data **\[C\]**:

|  | tier 0 | tier 1 | tier 2 | tier 3 |
| :---- | :---- | :---- | :---- | :---- |
| Median labour cost per unit | £0.1955 | £0.1940 | £0.1944 | £0.1948 |
| Median cost vs tier 0 | 1.000 | 0.992 | 0.994 | **0.995** |

**What this means commercially, stated plainly:** the bonus does **not** reduce labour cost per unit. It is roughly free, not profitable in itself. AW Aromatics gains from higher output through **overhead absorption, capacity release and avoided overtime/agency cost** — not through a cheaper unit. If management believes the tier system lowers unit labour cost, that belief is not supported by the data.

There is a small structural gain hidden in step 1: targets are generated at **£13.00/hour** but band 0 pays **£12.71**, so an operator who exactly hits the tier-0 target costs 2.2% less per unit than the legacy piece rate implied **\[C\]**.

### A6. Special cases the developer must handle

| Trigger | Behaviour | Risk |
| :---- | :---- | :---- |
| `product = "Non Working Hours"` | Paid at NLW, band 0, no output. 47 of 239 Week-1 rows **\[F\]** | Unmeasured time — see D1 |
| `product = "Development"` | Band D, £13.30/hr | — |
| `LEFT(product,3) = "ZZD"` | Band DG, £15.00/hr | Magic string on a product code |
| `LEFT(product,3) = "AAD"` | Band D, £13.30/hr | Magic string on a product code |
| `product = "Maintenance"` | **Band 2 forced**, £14.00/hr, no measurement | Highest un-earned rate in the scheme; no cap, no approval |
| `person.guru = "Yes"` | \+£1.00/hour on all hours | Not visible on the job row; only in Personnel/Payroll |
| Family has no targets | Band 0 silently | 22 family codes referenced by products don't exist in the master **\[F\]** |
| Holidays / SSP | Added in `Payroll` only | SSP is hard-coded into one person's formula — see D3 |

### A7. Week 1 evidence

**Reconciled totals** — 239 job rows, 11 operators, **383.25 paid hours**, **£5,280.53 pay**, **£409.34 bonus**, average effective rate **£13.78/hour** **\[C\]**. Bonus is **7.8%** of the production wage bill.

**Band mix by row count \[C\]:** band 0 \= **107 (44.8%)** · band 3 \= 96 (40.2%) · band 2 \= 15 (6.3%) · band 1 \= 13 (5.4%) · D \= 8 (3.3%)

The distribution is bimodal — people are either at the top or at the bottom, and almost nobody is in bands 1 and 2\. That is diagnostic: **the targets are not calibrated.** Where a family's targets are correct you would expect a spread; a U-shape says targets are either easy or impossible depending on the job. This is the strongest evidence in the file that rates need re-basing per family, and it is exactly what the empty `Enquiries` tab was built to do.

**Per operator \[C\]** (NWH \= hours booked as Non Working Hours):

| Operator | Hours | NWH | NWH % | Pay | Eff. £/hr | Band mix (rows) |
| :---- | :---- | :---- | :---- | :---- | :---- | :---- |
| Beata Przybyla | 37.5 | 0.9 | 2% | £561 | **14.95** | 37×b3, 4×b0 |
| Marija Morgunova | 34.9 | 0.0 | 0% | £519 | 14.86 | 5×b3, 1×b2 |
| Oana Vuta | 34.5 | 4.1 | 12% | £503 | 14.59 | 21×b3, 14×b0, 1×b1 |
| Filip Kurinec | 37.5 | 7.5 | 20% | £541 | 14.44 | 4×b3, 2×b0, 1×b2 |
| Zofia Baka | 36.8 | 0.0 | 0% | £504 | 13.69 | 9×b0, 7×b3, 6×b2, 2×b1 |
| Monika Kamionska | 36.0 | 0.2 | 1% | £492 | 13.67 | 10×b3, 14×b0, 2×b2, 6×D, 2×b1 |
| Izabela Walicht | 37.5 | 1.8 | 5% | £497 | 13.24 | 4×b3, 12×b0, 1×b2, 2×D |
| Katie Hammond | 16.0 | 0.0 | 0% | £209 | 13.09 | 1×b3, 5×b0, 3×b1 |
| Audrey Migliaccio | 37.5 | **22.3** | **59%** | £486 | 12.95 | 19×b0, 4×b3, 2×b1 |
| Georgeta Videanu | 37.5 | 0.0 | 0% | £486 | 12.95 | 15×b0, 3×b1, 4×b2, 2×b3 |
| Gheorghe Hutu | 37.5 | 1.2 | 3% | £482 | 12.86 | 13×b0, 1×b3 |

Spread from £12.86 to £14.95/hour — a **16% range** on identical contracts. Two observations management should not skip: Audrey's 59% non-working share means the scheme is not measuring her at all, and Gheorghe Hutu accounts for **13.8 of the 29.4 weekly hours lost to below-target output** **\[C\]** — that is a supervision and training question, not a rate question.

---

## Part B — Defects that must not be ported

Ranked by commercial impact. Each is a decision your developer needs from you, not a bug they can fix alone.

**D1 — 9.9% of paid production hours have no output attached.** 38.0 hours of the 383.25 in Week 1 are booked as "Non Working Hours" at NLW **\[C\]**. There is no reason code, no approval, no cap. Annualised at NLW that is **\~£25,100/year** of invisible paid time **\[E\]**. In Aiku, make a reason code mandatory (setup, changeover, cleaning, waiting for materials, machine down, training, meeting, other-with-comment) and report it. Without this, the whole scheme is measuring 90% of the factory and calling it 100%.

**D2 — Two live and contradictory wage ladders.** `Personnel`/`Payroll`/Week sheets pay **12.71 / 13 / 14 / 15**. The `Production Sheet - Auto` handed to the operator and the `Calculator` used to set rates both print **12.21 / 12.5 / 13.5 / 14.5** **\[F\]** — last year's rates, hard-typed. Staff are being shown a rate structure payroll no longer pays. Aiku must hold one rate table with effective-from dating and every screen must read it.

**D3 — SSP is welded into one employee's formula.** `Payroll!L26` ends `+sum(J9*L8)`, giving Olga Belanova £123.25 on zero recorded hours **\[F\]**. If she returns to work the payment persists; if anyone else goes sick the mechanism does not exist. There is no SSP cell for Week 5 at all. Absence, holiday and SSP must be first-class records, not formula edits.

**D4 — The wage bill does not reconcile, and one employee is silently dropped.** Four sheets give four different Week-1 totals **\[C\]**:

| Source | Week 1 total | vs Personnel |
| :---- | :---- | :---- |
| `Personnel` | £5,355.48 | — |
| `Payroll` | £6,241.33 | \+£885.85 (holidays 60.0h \= £762.60 \+ SSP £123.25) |
| `Combined Wages Tracking` | £4,757.43 | **−£598.05** |
| `Week 1 - Making` | £5,280.53 | −£74.95 (Guru bonus not on job rows) |

The −£598.05 is **exactly Beata Przybyla's weekly pay** — the highest earner in the file. `Combined Wages Tracking!A8` is `=#REF!` where it should read `=Personnel!A12`, so she vanishes from the wage-cost tracker **\[F\]**. Aiku must have one wage-cost figure derived once. Also: the five "NI \+ Pension" columns are empty, so "Total Wages Cost" is gross pay, not employment cost — understating true cost by roughly 15–18% **\[E\]**.

**D5 — Rounding destroys cost-neutrality on low-target jobs.** `ROUNDDOWN` on target 0 and `ROUNDUP` on targets 1–3 is harmless at 200 units/hour and severe at 3\. **51 of 364 families have a tier-0 target of 10 units/hour or less** **\[C\]**. Worst cases:

| Family | Piece rate | Targets 0/1/2/3 | Tier-3 cost ÷ tier-0 cost |
| :---- | :---- | :---- | :---- |
| OHSUL | £2.80 | 3 / 4 / 5 / 6 | **0.590** |
| SERFUL | £3.00 | 3 / 4 / 5 / 6 | **0.590** |
| HHCS | £3.20 | 4 / 5 / 6 / 7 | 0.674 |
| UBBEOP / UBBFOP / UBBP | £2.50 | 4 / 5 / 6 / 7 | 0.674 |
| ACSR / ACSaltB | £1.80 / £1.95 | 6 / 7 / 8 / 9 | 0.787 |

On OHSUL a tier-3 operator produces at 41% below the tier-0 unit cost — the operator does double the work for 18% more pay. Eight families run the other way (tier 3 costs **more** per unit: `FHBLP` 1.044, `HCS1`/`AHBLP`/`AHBWP` 1.033). Neither is intended. In Aiku, hold targets as decimals and round only for display, or set band boundaries as percentages of a standard rate.

**D6 — 21 targets are hand-typed, overriding the formula, and three are visibly wrong.** `YSWM`, `YSWMSI` and `YSWMS` all carry target **472 units/hour** despite piece rates of £0.0276, £0.055 and £0.16 — a 6× spread on identical targets **\[F\]**. Full list of overrides: `ABPCB, AWGSOAP, AWGSOAP1, AWBP, AWBPP, AWPO, AWRS, AWRSUL, BSSD, BSSP, CLBS, EFSSpongeUL, EFSSpongeULD, EFSSpongeP, GreenES, MMSS, ReblUL, YSWM, YSWMSI, YSWMS, YSWMP`. `ABPCB` also has `+1` fudges appended to two formulas. Aiku should permit a manual target override but require a reason, an author and a date, and flag it in reporting.

**D7 — 22 family codes referenced by products do not exist in the master.** Every job booked against them silently lands in band 0 at NLW **\[F\]**: `BFFPO`(18 products), `AWRSP`(16), `FOHC`(15), `FOKG`(15), `MO`(15), `BSGHC`(10), `WCSP`(9), `EOMULP`(8), `EOMULB`(8), `FSL`(7), `GSB`(6), `ExerS`(4), `ExerSR`(4), `Hsalt`(4), `NCWBbag`(4), `ORGBOA`(3), `MAM`(2), `AWRSULP`(1), `FSS`(1), `NWH`(1), `NsoapB`(1), plus 14 products with a blank family. Operators on these jobs cannot earn a bonus no matter how fast they work. In Aiku this must be a hard foreign key, not a lookup that returns blank. Also: **23 families have no piece rate**, 6 have no targets at all (`FHBLC, KSOAPUL, REBLNPW, SPBB, SPBBPa, VITCBBPI`), and 12 are flagged "RATE REQUIRED" or "Increase" in free text — `SPBB - MAKE` and `SPBB - PAINT` have been blank since the April 2024 archive.

**D8 — Duplicate and colliding job definitions.** `AHBLC` appears twice (rows 44 and 51); XLOOKUP will only ever find the first, so the second is dead **\[F\]**. Worse, two pairs are the same job at different rates: `ZENSSP` (£0.16, targets 81/83/90/96) vs `ZSSP` (£0.38, targets 34/35/38/41) — both "Zen Shower Steamers – Packing". An operator's band depends on which code the supervisor wrote down. Enforce uniqueness on family code and flag duplicate descriptions.

**D9 — The name dropdown offers the header row as an employee.** `Names = Personnel!$A$10:$A$32` but `A10` is the literal text `"Name"` **\[F\]**. Every operator dropdown in the workbook offers "Name" and two blanks as selectable people. Trivial in a spreadsheet, fatal in a database.

**D10 — The tier-0 target is decorative.** Printed on the operator's sheet, displayed in the Week-sheet target block, tested by nothing **\[F\]**. Either make it the underperformance trigger (recommended — see E3) or remove it from the operator's sheet, because publishing a target that carries no consequence teaches staff that targets carry no consequence.

**D11 — `Wholesale Pricing` cannot support the margin analysis it appears to offer.** Of 362 code rows, **208 (57%) have no usable single wholesale price** — 181 are "n/a", 6 "offline", 3 "-", and 18 hold a price *range as text* ("12.75 \- 19.95") **\[F\]**. Consequence: the `Wholesale Rate` column on the Week sheet resolves for only **6 of 239 job rows** — all six Gheorghe Hutu on `JBB-14`/`JBB-MX` — so `Wholesale Earnings` reports £1,893.43 for Hutu and **£0.00 for all fourteen other staff** **\[F\]**. Do not port this. In Aiku, take the selling price from the live price list and do not attempt to allocate a wholesale price across five production stages until the stages are properly costed.

**D12 — Half-finished features to decide on, not copy.** `Enquiries` — headers only, zero rows, and it is the one tab whose purpose ("Rates based on Averages over Enquiry Period") would justify a rate change. `Analysis` — hidden, one product selected, 96 rows of `#N/A`, and its high/low columns return "Audrey Migliaccio, amount 0" for every empty row, which reads as a real result. `Calculator!B1 = 'YCWM'` — a product code that exists in neither master. `Product Families!H1 = 16` — a £16/hour figure driving the entire "Recommended £/unit" column that is paid to nobody. `Personnel` row 6 is a duplicate rates row referenced by nothing. `Personnel!C3:E3` (0.0237/0.1056/0.1876) are the band uplifts as a percentage of the **old £12.21** NLW — orphan constants. In Google Sheets the same file produces **7,008 `#VALUE!` and 1,676 `#NAME?` errors** on recalculation **\[C\]**; in Excel it produces 28 `#DIV/0!` and 480 `#N/A`. The workbook does not behave the same in the two tools it is used in.

---

## Part C — Target design for Aiku (to-be)

### C1. Data model

job\_family                          ← Product Families

  code (PK, unique, enforced)

  description (NOT NULL)

  department            enum: bathroom | soaps | aromatherapy | home\_fragrance

                             | repacking\_misc | special\_customers

  operation            enum: making | packing | labelling | cutting | pouring

                             | shrinking | painting | other

  standard\_rate        numeric(10,4)  units/hour at standard performance

  status               enum: active | needs\_review | insufficient\_data

                             | discontinued | rate\_required

  complexity\_band      smallint       drives band spacing (see C3)

  target\_override      boolean

  override\_reason      text           required when target\_override \= true

  set\_by, set\_at, review\_due\_at

job\_family\_rate\_history             ← NEW. There is no history in the workbook.

  job\_family\_id, standard\_rate, effective\_from, effective\_to, set\_by, reason

product

  code (PK)

  description (NOT NULL)

  job\_family\_id (FK, NOT NULL, RESTRICT)   ← fixes D7

pay\_band                            ← Personnel\!A4:G7

  code                 0 | 1 | 2 | 3 | D | DG

  hourly\_rate          numeric(6,2)

  target\_multiplier    numeric(6,4)   1.0000 / 1.0228 / 1.1015 / 1.1802

  effective\_from, effective\_to       ← fixes D2

  requires\_approval    boolean        true for D, DG, forced bands

operator

  employee\_id (FK → Aiku employees)

  guru\_flag            boolean

  guru\_uplift          numeric(6,2)

  contract\_hours       numeric(5,2)

work\_session                        ← Week n \- Making, one row per job booking

  operator\_id, product\_id, batch\_code

  started\_at, ended\_at, break\_minutes

  quantity\_made, quantity\_rejected   ← rejected does not exist today

  activity\_type        enum: production | setup | changeover | cleaning

                             | waiting\_materials | machine\_down | training

                             | meeting | maintenance | other

  non\_productive\_reason  text        required when activity\_type \<\> production

  qc\_initials, qc\_passed

  \-- derived, stored at close for audit:

  hours, units\_per\_hour, band\_code, hourly\_rate, pay, bonus

  rate\_table\_version\_id              ← what the pay was calculated against

  approved\_by, approved\_at

Store the derived fields. Do not recompute historic pay from current rates — the moment the rate table changes, last month's payroll must not move.

### C2. Mapping onto existing Aiku tables

Based on the live schema **\[F\]**:

| Spec entity | Aiku table | Fit |
| :---- | :---- | :---- |
| `job_family` | `manufacture_tasks` — `code`, `name`, `task_work_cost`, `task_lower_target`, `task_upper_target`, `operative_reward_terms`, `operative_reward_allowance_type`, `operative_reward_amount`, `data` jsonb | **Close.** Only 2 targets vs 4 bands — the key gap. Extra bands go in `data` jsonb or a new child table. `status` boolean is too coarse for the 5-state lifecycle above. |
| `product → job_family` | `artefacts_manufacture_tasks` with `units_per_artefact`, `position` | **Better than the sheet** — supports multi-step routings, which the sheet fakes with separate "Making"/"Packing" families |
| `work_session` | `manufacture_task_sessions` — `employee_id`, `started_at`, `ended_at`, `quantity_made`, **`quantity_rejected`**, `task_work_cost`, and a per-session snapshot of the reward terms | **Very close.** Already snapshots reward terms — exactly the audit behaviour needed. Missing: break duration and the activity\_type/reason enum (D1). |
| `operator` | `employees` (`week_working_hours`, `salary` jsonb, `employment_type`, `pin`) \+ `employee_has_job_positions` | Good. `pin` supports shop-floor terminal login. |
| Attendance vs booked time | `timesheets` (`working_duration`, `breaks_duration`, `total_duration`, `number_open_time_trackers`) | **This is the D1 fix.** Reconcile booked session time against the timesheet and expose the gap. |
| Reporting | `manufacture_task_stats`, `production_stats`, `employee_stats` | Replaces `Analysis`, `Enquiries`, `Wholesale Earnings` |

**Recommendation:** extend `manufacture_tasks` with an N-band child table rather than reducing the scheme to Aiku's two targets. Four bands are already published to staff and printed on the crib sheets; cutting to two is a change to people's pay and should not be made as a side effect of a data migration.

### C3. Recommended design changes — explicitly separated from the as-is

| \# | As-is | Recommended | Why |
| :---- | :---- | :---- | :---- |
| **E1** | Targets derived from a legacy £/unit piece rate ÷ £13/hr | Hold `standard_rate` in **units/hour** directly; derive band targets by multiplier | The piece rate is a fossil. Nobody can explain what £0.0276 means, and it is the root of D5/D6. Migration is arithmetic: `standard_rate = ROUND(13.00 / piece_rate, 2)` — no change to any current target. |
| **E2** | Four bands, uplifts 0 / \+2.3% / \+10.1% / \+18.0% | Keep four bands; **widen band 1 to \~+5%** | The 2.3% gap (£0.29/hr, about £11/week) is too small to change behaviour, which is why only 5.4% of rows land there. The 0→1 step must be worth chasing. Costed: at Week-1 volumes, moving band 1 to \+5% costs \~£3/week **\[E\]** because so few rows are in it — and that is the point. |
| **E3** | Band 0 \= everything below target 1, no consequence | Split: `below_standard` (\< target\_0) triggers a **supervisor review**, not a pay penalty | 49 rows and **29.4 hours/week** are below even target 0 **\[C\]**, worth \~£19,400/yr **\[E\]**. Today that is invisible. A review flag is free, legal and fixes the biggest measurable loss. Pay stays at NLW either way. |
| **E4** | Targets rounded to integers by ROUNDDOWN/ROUNDUP | Store decimals; round for display only | Fixes D5. On OHSUL restores unit cost from 0.590× to 1.000×. |
| **E5** | "Maintenance" forces band 2 (£14/hr) with no measurement | Own activity type at band 0 or D, supervisor-approved, capped hours/week | Currently the easiest way to earn £14/hr is to write "Maintenance". No cap, no approval. |
| **E6** | `"Non Working Hours"` free text | Mandatory `activity_type` \+ reason (D1) | Turns £25k of invisible time into a managed number |
| **E7** | Quality recorded as QC initials only; rejects nowhere | `quantity_rejected` mandatory; **band 3 requires reject rate ≤ threshold** | The scheme pays 18% more for 18% more speed with **no quality gate at all**. This is the single largest behavioural risk in the design. Aiku already has the column. |
| **E8** | Rates changed by typing over a cell | `effective_from` dating \+ history \+ approval | Fixes D2 and makes retro-pay possible without rebuilding a spreadsheet |
| **E9** | Rate reviews via an empty `Enquiries` tab | Automated review: any family where achieved rate is \>120% of target 3 or \<80% of target 0 for 4+ weeks with ≥3 operators is queued for review | This is the mechanism that stops the U-shaped band distribution recurring. It is the highest-value automation in the project. |
| **E10** | Guru \= \+£1/hr on all hours, flag on the person | Keep, but make it visible on the job row and time-limited with a review date | An unreviewed permanent personal uplift drifts into an unexplained pay difference |
| **E11** | Wholesale price allocated across 5 production stages | Drop. Take selling price from the live price list | 57% of rows have no usable price (D11); the output is misleading, not incomplete |

**Cost of E1–E11 to the current wage bill:** approximately **\+£3 to \+£15/week** at Week-1 volumes **\[E\]**, driven almost entirely by E2. Everything else is measurement and control, which costs nothing to run.

### C4. Validation rules — enforce at entry, not in a report

| Rule | Action |
| :---- | :---- |
| `ended_at > started_at` | Reject |
| `break_minutes < total elapsed` | Reject |
| Overlapping sessions for one operator | Reject — the sheet cannot detect this today |
| `quantity_made > 0` when `activity_type = production` | Reject |
| `product.job_family` has active targets | Reject with "rate required" (fixes D7) |
| `units_per_hour > 3 × target_3` | Accept, flag for supervisor — likely a units/pack-size error |
| `units_per_hour < 0.25 × target_0` | Accept, flag for supervisor |
| Session \< 5 minutes | Warn — Week 1 contains a 5-minute, 1-unit row **\[F\]** |
| Σ session hours vs `timesheets.working_duration` | Show the gap on the operator's own screen, daily |
| Band D / DG / forced band | Require supervisor approval before it pays |
| Band 3 with reject rate above threshold | Downgrade to band 2, notify supervisor (E7) |

### C5. Automation — what Aiku should do that the sheet cannot

**Tier 1 — replaces the spreadsheet (build first)**

1. **Shop-floor capture at the point of work.** Operator scans/selects product, taps start, taps finish, enters quantity made and rejected. `employees.pin` already exists for terminal login. Eliminates the paper sheet, the typing, and the entire class of transcription error.  
2. **Live band feedback.** Show the operator, during the job: current units/hour, the next band's target, and the £ value of reaching it. Today they find out on payday. This alone typically shifts output more than a rate change does — and it costs nothing per week because the ladder is cost-neutral (A5).  
3. **Automatic band assignment and pay calculation** against the dated rate table, stored on the session.  
4. **Weekly payroll export** — hours by band, holidays, SSP, Guru, per person, reconciled to one figure (fixes D4).  
5. **Attendance-vs-booked reconciliation**, daily, per person, using `timesheets` (fixes D1).

**Tier 2 — the management layer**

6. **Rate review queue** (E9) — the replacement for `Enquiries`, and the thing that keeps targets honest.  
7. **Cost-neutrality guard** — a scheduled check asserting `rate_n / target_n` is within ±2% across all bands for every family. Any manual override that breaks it is flagged the same day. This is the unit test that protects the design's best property.  
8. **Family health report** — missing rates, missing descriptions (769 products have none **\[F\]**), duplicates, families unused for 90 days, targets older than 12 months.  
9. **Labour cost per unit fed into product costing** — `12.71 × piece_rate / 13` today, but from actual sessions once Aiku is live. This is the link to the costing workbook and to pricing.  
10. **New-product rate setting workflow** — time-study a first run, derive standard rate, mark `insufficient_data`, auto-review after N units or 4 weeks. Fixes "RATE REQUIRED" sitting unresolved since April 2024\.

**Tier 3 — once there is clean history**

11. Capacity planning: order book → hours required by department at standard rate, versus rostered hours. The sheet cannot do this and it is where production planning actually pays.  
12. Operator development: performance by family over time, to distinguish a training gap from a rate problem from a machine problem.

---

## Part D — Compliance note

Pay is `hours × hourly rate` with a floor of **exactly** the NLW. That structure is materially safer than genuine piecework, and I would keep it — do not let anyone convert this to true piece-rate pay in Aiku without employment-law advice, because output-based pay brings fair-piece-rate record-keeping obligations that this design avoids entirely.

But the floor being *exactly* NLW means **zero headroom**. Any working time not captured on a booked row is a National Minimum Wage shortfall. Two specific exposures:

- The workbook prints **"Daily expected time for cleaning: 15 mins"** on the operator's sheet **\[F\]**. If that cleaning is expected but not booked as paid time, it is unpaid working time — 15 min/day × 11 operators × 5 days ≈ **13.75 hours/week ≈ £8,700/year** of exposure **\[E\]**.  
- No mechanism reconciles attendance to booked hours. Week 1 booked 383.25 hours; whether that equals attended hours is unknowable from the file.

**Recommendation:** have payroll or an employment-law adviser confirm (a) that £12.71 is the correct current statutory rate for every worker's age band, (b) that cleaning, setup, changeover and briefing time are captured as paid time, and (c) the SSP handling in D3. C5 items 1 and 5 close the record-keeping gap. Flagging for professional review — I am not giving legal advice.

---

## Part E — Missing information

Only items that would change a design decision:

1. **Are the £12.71 / 13 / 14 / 15 rates current and correct** for every worker's age band as at Aug 2026?  
2. **Attendance hours per operator for Week 1** — needed to size the D1 gap properly. It may be larger than 9.9%.  
3. **Who authorised the 21 hand-typed targets (D6), and when?** Determines whether they are corrections to keep or errors to reverse.  
4. **The intended meaning of `Product Families!H1 = 16`** and the "Recommended £/unit" column. Is £16/hour a planned future band, or abandoned?  
5. **What was `Product Families` column C originally?** The old piece rates were genuinely paid at some point. Knowing when the switch to banded hourly happened tells us whether the 2024 archive rates are still a valid cross-check.  
6. **Reject / rework data** — does any record exist? E7 needs a starting threshold.  
7. **Does Aiku's `task_lower_target` / `task_upper_target` pair have downstream consumers** (existing reports, other sites) that constrain extending it to four bands?  
8. **Are the four departments** (Bathroom, Soaps, Aromatherapy, Home Fragrance) the correct organisational split in Aiku, and do they map to `job_positions.department`?

---

## Part F — Management dashboard

Six weekly KPIs. Resist adding more until these are trusted.

| KPI | Formula | Source | Owner | Target |
| :---- | :---- | :---- | :---- | :---- |
| **Measured hours %** | booked productive hours ÷ attended hours | `manufacture_task_sessions` vs `timesheets` | Production Manager | **≥ 95%** (Week 1: 90.1% **\[C\]**) |
| **Average effective hourly rate** | total production pay ÷ total production hours | sessions | Ops Director | £13.50–£14.00 (Week 1: £13.78 **\[C\]**) |
| **Below-standard hours** | hours where units/hour \< target\_0 | sessions | Production Manager | **\< 5%** (Week 1: 7.7% **\[C\]**) |
| **Band distribution** | % rows in bands 0/1/2/3 | sessions | Ops Director | No band \< 10%; bands 1+2 ≥ 25% (Week 1: 5.4% \+ 6.3% **\[C\]**) |
| **Reject rate** | rejected ÷ (made \+ rejected) | sessions | QC / Production Manager | Baseline first — no data exists |
| **Families needing rate review** | count where status ≠ active, or review overdue | `job_family` | Ops Director | **\< 10** (today: 12 flagged \+ 23 with no rate \+ 22 orphaned **\[F\]**) |

**Band distribution is the diagnostic to watch.** A healthy scheme spreads people across bands. Week 1's 45%/5%/6%/40% U-shape is the clearest single signal that targets, not people, are the problem.

---

## Part G — Recommended sequence

**Immediate (7 days) — before any development**

1. Fix `Combined Wages Tracking!A8` (`=#REF!` → `=Personnel!A12`). One employee, £598.05/week, missing from wage cost. *Owner: whoever maintains the sheet.*  
2. Reprint `Production Sheet - Auto` and the manual crib sheet with the correct 12.71/13/14/15 ladder. Staff are being shown last year's rates. *Owner: Production Manager.*  
3. Fix `Names = Personnel!$A$11:$A$32`, removing "Name" and the blank spacers from every dropdown.  
4. Decide the four-bands-vs-two question in Aiku. Everything downstream depends on it. *Owner: Ops Director \+ developer.*

**Short term (30 days)**

5. Resolve all 22 orphaned family codes and 23 missing piece rates; delete or merge the `AHBLC`, `ZENSSP`/`ZSSP`, `ZENSS`/`ZSS` duplicates.  
6. Correct the `YSWM` / `YSWMSI` / `YSWMS` 472 targets and document the other 18 overrides.  
7. Confirm the NLW rate, cleaning-time treatment and SSP handling with payroll.  
8. Build Aiku phase 1: `job_family`, `pay_band` with dating, `product` FK, session capture with mandatory activity type.

**Medium term (90 days)**

9. Shop-floor capture live with real-time band feedback; retire the paper sheet.  
10. Attendance reconciliation and the rate review queue running weekly.  
11. Cost-neutrality guard in CI, plus the six-KPI dashboard.  
12. Re-base the 51 low-target families onto decimal targets and re-time-study the worst by output volume.

---

## Financial effect — all figures estimates from one week of data

| Item | Basis | Annual effect |
| :---- | :---- | :---- |
| Non-working hours brought under management | 38.0 h/wk × £12.71 × 52; assume **half** recoverable | **\+£12,600** |
| Below-standard output closed to target\_0 | 29.4 h/wk × £12.71 × 52; assume **half** recoverable | **\+£9,700** |
| Cleaning-time NMW exposure removed | 13.75 h/wk × £12.71 × 52 | **£8,700 risk avoided** |
| Wage-cost misstatement corrected | £598.05/wk × 52 (reporting accuracy, not cash) | £31,100 visibility |
| Band 1 widened to \+5% (E2) | Week-1 band-1 volumes | **−£150 to −£800 cost** |
| Rounding fixed on 51 low-target families (D5) | Direction depends on band mix; recovers intended unit cost | Not estimable without volumes |

**Net indicative benefit: £20,000–£25,000/year plus \~£8,700 of avoided compliance risk, against a wage-bill increase under £1,000/year \[E\].** These rest on a single week of data from one populated sheet. Treat them as an order of magnitude that justifies the project, not a business case. Re-run them once four weeks of clean Aiku data exist.

---

*Prepared from `Updated New Production Sheet Aug26.xlsx` as at 11 Aug 2026\. All band-assignment logic in section A3 was independently re-implemented and reconciles to 239 of 239 Week-1 job rows. Aiku schema details in C2 read from the live database.*  
