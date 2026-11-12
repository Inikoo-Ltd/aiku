---
paths:
  - 'app/Actions/CRM/Customer/**'
---

# CRM

## Anonymising a customer wipes the email reply address: reply first
`AnonymiseCustomer::anonymiseChats` sets `chat_sessions.metadata` to null on every chat session of the customer's web users, email-channel sessions included (`ProcessInboundEmail` links an email thread to the web user when the sender matches one). The reply address lives in that metadata, and `SendChatMessageByGmail` returns without sending, logging or throwing when both `email_reply_to` and `email_from` are missing. A reply sent after anonymising is stored in the chat but never emailed, with no error anywhere. Send any reply first, then anonymise. Guest email threads with no web user are not touched by the anonymiser.

## Anonymising emails the customer first, or holds the erasure for fraud or debt
AnonymiseCustomer sends the account_erased outbox (SendAccountErasedEmail) to the real address before it scrubs it, so staff never need to write back after an erasure, and the inbox losing the address is intended (HELP-4652). It does not anonymise when erasureHoldReasons() finds a fraud note (the dialog's "Suspected fraud" box) or money owed (negative balance, or an unpaid invoice from the last year). Then it closes the account instead: web users soft-deleted, comms unsubscribed, customer soft-deleted with data.deleted.cause = erasure_held, an `erasure_held` audit with the note, and identity kept with no email (GDPR art. 17(3)(e)). Never route a held customer through the scrub.
