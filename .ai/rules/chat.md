---
paths:
  - 'app/Actions/Chat/**'
---

# Chat

## AI coverage: one outcome per customer message
chat_ai_outcomes holds one row per (session, trigger_message_id), enforced by partial unique indexes; write it only through StoreChatAiOutcome, which keeps the stronger outcome by ChatAiOutcomeEnum case order (draft first). GetChatAiCoverage counts each chat once, by the customer's latest message in the window: the recorded outcome, else a staff reply after it, else is_spam, else is_rubbish (as notice), else not worked. A newer customer message is new work, so an outcome on an earlier one no longer covers the chat. Team windows (today/overnight/team hours) come from config('chat.ai_teams').

## Verified chat facts: two chats to trust, staff removal sticks
Shop facts the CS assistant's reviewers check live in chat_knowledge_entries with source_type verified, written only through RememberVerifiedChatFact (MCP remember_fact). A fact is a candidate until checked while working two different chats, then active for TRUSTED_DAYS; a fact repeating the customer's own words is refused, so one planted message cannot become a rule. Discarding a draft or rating it wrong_fact disputes the facts in its facts_used; staff Remove on /chat/ai/verified-facts is final for the tool. HydrateChatKnowledge must never delete verified rows (it only prunes webpage, shop_settings, docs).

## One WhatsApp thread per contact per shop: three lookups stay in step
StoreIncomingWhatsappMessage::findSession, HandleWhatsappCallEvent::resolveSession and StoreMetaChatSession find a contact's thread the same way: withTrashed, never one whose metadata->merged_into is set (MergeMetaChatSessions::MERGED_INTO_KEY), live first, and a binned thread is restored rather than a second one started beside it (that is how prod got its only same-shop duplicates). Each shop writes from its own WhatsApp number and keeps its own customers, so the same phone in two shops is two real conversations: never merge them, they meet in the conversation history (GetCustomerChatHistory::forConversation) instead.
