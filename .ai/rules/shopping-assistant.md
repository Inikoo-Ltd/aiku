---
paths:
  - 'app/Actions/CRM/ShoppingAssistant/**'
---

# Shopping Assistant

## Assistant texts are wiped in one place, and wiped conversations are hidden from customers
HELP-4608 (12 Nov 2026): every wipe of what customers wrote to or were told by the AI shopping assistant goes through WipeShoppingAssistantConversations (message body/tool_calls/follow_ups, event reasons, hand-over questions, challenge quotes and topics; sets wiped_at; the nightly run also re-wipes text written after a wipe). AnonymiseCustomer and the nightly shopping-assistant:wipe-old (group setting shopping_assistant.retention_months, else config retention_months 24) both call it; add any new text column there. Every customer-facing lookup of a conversation (ask, hand-over, poll, history list) must exclude wiped_at like CLEARED. Challenge tagging (Haiku, nightly) is not a shopping_assistant_model_calls row so it never counts against a website budget. Weekly summary recipients are user ids in group settings (shopping_assistant.weekly_summary_user_ids), never names in code.

## Shop memory (customers.shop_profile) is kept until the customer clears it
Raul, 12 Nov 2026 (HELP-4606): what the assistant remembers about a shop (customers.shop_profile, written by the remember_about_shop tool) is NOT wiped by the conversation retention period (WipeShoppingAssistantConversations). It stays until the customer presses "Forget this" on the Range Advisor panel or the customer is anonymised (AnonymiseCustomer). It never holds figures (the tool refuses digits, so the reply number guard holds), is audit-excluded on Customer, and the prompt presents it as facts the customer gave, not instructions. Cheeky jokes in the personal touch are for customers.is_staff only.

## MER tickets are only confirmed problems only engineers can fix
Raul, 13 Nov 2026: Merlin (report_to_engineers → StoreShoppingAssistantEngineerTicket) raises a MER ticket straight to the engineers ("the Archmages"), skipping customer services, only when the customer tried the documented fix, Merlin gives the evidence (why_only_us) and an independent AskJev check is >= MER_CONFIDENCE sure it is our defect. Anything unsure raises NO ticket (no CUS fallback): Merlin offers a person in chat and CS raise a ticket themselves if real. No noise in MER. One open MER per customer; repeats become comments. Works on B2B and dropshipping shops; dropshipping tickets carry the sales channel counters.
