---
paths:
  - config/blueprint/job_positions.php
---

# Blueprint

## New group job position: seed categories before positions
A group-scoped position added here needs `group:seed-job-position-categories` run BEFORE `group:seed-job-positions` (and `group:seed-permissions` for a new role/permission). Otherwise the position is created with group_job_position_id null (warning "Attempt to read property id on null", SeedJobPositionsScopeGroup line ~63), and re-seeding positions does not fix it because the update branch never sets the category. Also bump the group role and job position counts in tests/Feature/SysAdminTest.php.
