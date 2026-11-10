---
paths:
  - 'app/Actions/Production/Artisan/**'
---

# Artisan

## Hiding an artisan from the roster is undated: ToggleArtisanInRoster guards locked weeks
HELP-4629: production.data.hidden_artisan_ids takes a person's tablet sessions and clocked hours out of days and payroll for every week (HydrateManufactureArtisanDays::artisanSessions), locked ones included, with no adjustment record. So the hide/show routes refuse anyone with ToggleArtisanInRoster::ARTISAN_HOURS (1 h) or more of closed tablet time before ManufacturePayrollAdjustment::lockedBefore unless the user can change a locked week (accounting.{org}.edit); the refusal shows inline on To produce > By artisan. The action() entry is unguarded (tests, scripts). If hiding ever needs to apply from a date, make the list dated rather than loosening the guard.
