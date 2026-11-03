---
paths:
  - 'app/Actions/Production/ManufactureArtisanDay/**'
  - 'app/Actions/Production/ManufactureTaskSession/GetManufacturePayroll.php'
---

# Manufacture Artisan Day

## Artisan days are recalculated from sessions whenever they are viewed
manufacture_artisan_days rows are rebuilt by HydrateManufactureArtisanDays for the period (max 92 days) whenever Daily hours, Performance or Payroll is opened. Never write their figures anywhere else. A supervisor's decision (resolution) only counts while resolved_minutes == recorded_minutes, so editing a session's times reopens the day. A day belongs to the shift it started in (night shifts go to the evening they began), and expected time = shift length minus the day's unpaid_break_minutes (work_schedule_days, default 30, HELP-4281). Timed WorkScheduleBreaks are not counted: they only mark when somebody is away (chat availability). The profile break is never deducted from pay (HR Paid time and production hours take off clocked breaks only); a day that would otherwise match the shift but has no ManufactureBreak between its first job and its last is flagged no_break, and a supervisor answers it with `checked` (HELP-4285).

## Days and payroll are on clocked time (HELP-4462)
The day is checked, and payroll is paid, on the time clocked: the timesheet working_duration (closed clock in/out pairs, so clocked breaks are already out), read through HydrateManufactureArtisanDays::clockings for the employees who used the tablet in the period. Over/under, the supervisor's answer (resolved_minutes) and not authorised overtime follow clocked_minutes; recorded_minutes stays the tablet time beside it. Payroll pays each day's clocked hours: the tablet hours split into bands as before, clocked time not on the tablet at band 0, and tablet time beyond the clocked time unpaid, taken off the jobs that ended last and flagged on the day (tablet_over_clocked_minutes, its own warning, cleared by correcting the job times or the clocking). Hourly artisans get their clocked hours at band 0 with no speed bands, but machine maintenance (band 2) and development (D/DG) keep their own rate for every pay type. The Guru bonus is on clocked hours. Workers come from the period's sessions plus HydrateManufactureArtisanDays::rosterEmployeeIds (a closed session in the production in the 8 weeks up to the period end, minus those hidden from the artisan roster), so hiding someone from the roster on To produce also stops their clocked-only days being paid from factory payroll. A day not clocked pays nothing.
