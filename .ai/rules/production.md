---
paths:
  - 'app/Actions/Production/**'
---

# Production

## Department artisans are a skill group; the default maker is the least busy one
Artisans assigned to an ArtefactDepartment (artisan_assignments, HELP-4463) are the people who can make its products: pickers list them first (GetArtisanDepartments::departmentFirst / department_ids on options). When nobody is chosen (and the artefact has no artisan of its own), the default maker is the department's least busy artisan: PickDepartmentArtisans (clocked in today first, then on the tablet in the last week, then fewest open job orders, then name; never someone with neither). Never use ->artisans()->first() on a department, or every card defaults to whoever was added first. The board marks these picks with maker_is_auto.
