---
paths:
  - 'app/Actions/Production/ManufactureOvertime/**'
---

# Manufacture Overtime

## Overtime decisions live in manufacture_overtimes, written through SetManufactureOvertime
HELP-4597 (10 Nov 2026): one row per production, employee and day (planned times optional, approved paid or TOIL, or not approved with a reason), written only through SetManufactureOvertime, which goes through changeInPayWeek. HydrateManufactureArtisanDays links the day (manufacture_overtime_id) and keeps a day row for any planned overtime. Each decision snapshots expected_minutes (shift less unpaid break when decided) and approved_minutes (clocked beyond the shift when decided after the day, else the planned window), so a later schedule change never moves a locked week. ManufactureOvertime::coveredMinutes = min(clocked − expected snapshot, approved); time beyond it is paid as normal and flags the day until saved again. An approval holds while uncovered ≤ tolerance and (saved since clocking or clocked within planned times + shift ± tolerance). GetManufacturePayroll takes the covered minutes off each paid line of the day in proportion: weekend + paid is paid at round(1.5 × band 0, 2) with no band or Guru; TOIL is not paid and owed × the toil_ratio snapshotted when approved (production settings toil_ratio). Weekday paid and not approved are paid as normal. Old ManufactureArtisanDay approved/not_approved resolutions were not backfilled and keep paying as before. TOIL balance = coveredMinutes × ratio (from linked day rows' clocked_minutes) − manufacture_toil_taken, never stored. RemindManufactureOvertimeDecisions emails managers on Monday.
