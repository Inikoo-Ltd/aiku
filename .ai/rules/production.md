---
paths:
  - 'app/Actions/Production/**'
---

# Production

## Department artisans are a skill group; the default maker is the least busy one
Artisans assigned to an ArtefactDepartment (artisan_assignments, HELP-4463) are the people who can make its products: pickers list them first (GetArtisanDepartments::departmentFirst / department_ids on options). When nobody is chosen (and the artefact has no artisan of its own), the default maker is the department's least busy artisan: PickDepartmentArtisans (clocked in today first, then on the tablet in the last week, then fewest open job orders, then name; never someone with neither). Never use ->artisans()->first() on a department, or every card defaults to whoever was added first. The board marks these picks with maker_is_auto.

## Payroll lock: route every pay-changing edit through WithManufacturePayrollLock
A pay week (Mon–Sun) locks at the end of the Monday after it in the organisation's time. ManufacturePayrollAdjustment::lockedBefore() returns that Monday's DATE at 00:00 stored (UTC) time, because payroll puts a session in the week of its stored ended_at date: compare moments to it, never convert it to local time. Any action that changes hours, units or pay of a past session, break, artisan day or Guru dates must wrap its write in changeInPayWeek() (trait WithManufacturePayrollLock): open weeks only for supervisors/the production manager; locked weeks only for accounting.{org}.edit, with lock_reason, recorded in manufacture_payroll_adjustments (append-only, audited) with the pay delta, settled by GetManufacturePayroll in the run whose Tuesday–Monday window holds the change (settledIn()), and the accountant notified. Roles: accountant = accounting.{org}.edit WITHOUT org-supervisor; production manager = organisation admin (org-supervisor); floor supervisors = productions_operations.{id}.orchestrate. Use startOfWeek(Carbon::MONDAY): plain startOfWeek() follows the locale (en_US starts on Sunday). Clocked hours are the artisans' paid hours, so every human action that adds, edits or deletes a clocking, time tracker, timesheet or approves a clocking adjustment goes through ChangeArtisanClocking::run() (wrapped at asController for HR screens; warehouse-team screens pass a null editor so they can never touch a locked week).
