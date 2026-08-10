# Manufacture pilot checklist

Running one line on the aiku manufacture module alongside paper, then cutting
over. Ordered; each block is a gate for the next.

## 1. Before the pilot — data

- [ ] Deploy the manufacture module (migrations run with the deploy).
- [ ] Aurora DDL in place on every Aurora database (done Aug 9 2026):
      `aiku_id` on `Raw Material Dimension`, `aiku_job_order_id` on
      `Purchase Order Dimension`.
- [ ] Import, in this order (both idempotent, re-run safe):
      `php artisan fetch:raw_materials aroma sk es aw`
      `php artisan fetch:artefacts aroma sk es aw`
- [ ] Optional, historic reporting only: `php artisan fetch:job_orders aroma`
- [ ] Sanity check the counts: raw materials nearly all org-stock linked,
      artefacts carrying batch sizes, recipe rows in the thousands.

## 2. Before the pilot — setup a supervisor must do

- [ ] Pick the pilot line / product family (question 16 to management).
- [ ] For each pilot product, split the imported `PROD` recipe step into the
      real steps (mixing, filling, labelling…) with positions and
      units-per-artefact. The import puts every raw material on one `PROD`
      step because Aurora never had task data.
- [ ] Set the pay per unit (`task_work_cost`) on each paid task, plus targets
      and reward terms. **Bonus maths stays manual until management answers
      what the targets mean** (question 1) — the payroll export prints the
      reward columns raw.
- [ ] Check "artefacts without recipe" on the Crafts dashboard is zero for the
      pilot family.
- [ ] Link every pilot artefact to its org stock, or receiving into stock will
      refuse.

## 3. Before the pilot — people and devices

- [ ] Create a user per line worker with the `production-operator` role.
- [ ] Tablets on the line, one per bench or shared per line; confirm wifi
      reaches where the workers stand (question 13).
- [ ] Walk the floor screen with one worker: log in, START, DONE, quantity.
      Four taps, and their own numbers visible at the top.
- [ ] Show the supervisor: release a job order to the floor, the live board,
      the artisans page, and how to void a wrong entry.

## 4. Parallel run — two weeks

- [ ] Paper sheets keep running exactly as today. Nothing changes for payroll
      yet.
- [ ] Every week: export the payroll CSV and compare it against the paper
      tallies for the same days. They should agree worker by worker.
- [ ] Collect complaints daily; they are the real backlog.
- [ ] Watch for: missing recipes, wrong pay rates, rejects being entered
      inconsistently, workers unable to find their task.

## 5. Cutover — the day the line stops using paper

- [ ] The payroll CSV has matched the paper for a full week, twice.
- [ ] **Remove `Artefacts`, `RawMaterials` and `JobOrders` from
      `allowed_fetchers` in `config/aurora.php`, and remove the pilot's
      organisation from `AURORA_FOLLOWING_ORGANISATIONS` if it is still
      listed.** From cutover the supervisors maintain artefacts, raw
      materials and recipes in aiku; an Aurora re-fetch would overwrite
      artefact codes, names, batch sizes and stock links with Aurora's
      version. Imported recipes are safer — the fetcher only ever touches the
      `PROD` step — but the artefact fields are not.
      These fetchers are **not** scheduled, so nothing overwrites on its own;
      this removes the possibility of someone re-running the import by hand.
- [ ] Tell the office that pay now comes from the payroll export, not from
      typed-up paper.
- [ ] Keep the paper sheets archived for one pay cycle in case of a dispute.

## 6. After cutover

- [ ] Watch the first real payroll run end to end, with the supervisor
      present.
- [ ] Re-check the void trail: every correction should be visible on the
      artisans page rather than someone editing a number.
- [ ] Only then repeat from section 2 for the next line.

---
*Living checklist; update as the pilot teaches us things.*
