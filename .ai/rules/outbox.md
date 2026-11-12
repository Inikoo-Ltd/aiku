---
paths:
  - 'app/Enums/Comms/Outbox/**'
---

# Outbox

## A new outbox needs group:seed_email_templates on prod after deploy
shop:seed_outboxes and fulfilment:seed_outboxes create the outbox and its ongoing run, but attach an email only when the EmailTemplate row from email-templates.json already exists. Without one there is no live snapshot and the sender silently returns null (HELP-4652: 43 active outboxes, 0 templates). After deploy run `group:seed_email_templates`, which seeds the templates and then reruns the outbox seeders, and check that every outbox has emails.live_snapshot_id.
