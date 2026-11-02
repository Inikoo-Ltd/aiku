---
paths:
  - 'app/Actions/Comms/Mailshot/**'
---

# Mailshot

## A mailshot can own two emails; always go through $mailshot->email
Mailshots migrated from Aurora have two emails with parent = the mailshot: the one aiku created (lowest id, the one the workshop edits) and the Aurora template copy (has source_id, never edited). Mailshot::email() is oldestOfMany() so it always picks the workshop one; without an order Postgres returns them in physical order, which changes after each save and made edits "disappear" on reload. Do not read the email by mailshots.email_id: it is null for aiku-made mailshots, points at the Aurora copy for duplicates, and at another parent's email for ~1,300 rows.
