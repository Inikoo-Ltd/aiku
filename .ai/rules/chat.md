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

## Draft status stays as staff see it; change_class is the measure
SettleChatAiDraft::statusFor and its thresholds (used/edited/superseded) feed the adoption stats, coverage and the draft-box counts: never retune them to answer a new question. How much staff changed a draft lives in chat_ai_drafts.change_class (MeasureChatAiDraft: as_is, cosmetic, gaps_filled, small, big, rewritten, own_reply, discarded), with words_kept, lag_seconds and open_to_reply_seconds. A draft whose [[gaps]] staff filled counts as "used" in status but gaps_filled here, and only as_is/cosmetic could ever go out without a person. open_to_reply_seconds is time from the message being opened to the reply, not time spent working: never divide payroll by it. chat:measure-drafts runs nightly and is also the backfill.

## Claim outcomes are an estimate by order and timing, one case per chat and order
chat_ai_drafts.claim_check (MeasureChatAiClaim, chat:measure-claims nightly, also the backfill) labels damaged/missing/wrong item drafts by what was done on the order within 7 days: refund invoice, compensation/add_funds_other credit (never pay_return, which every refund-to-balance writes too), replacement delivery note, follow-on in the next order. Values are net in the group currency so bands (config chat.claim_value_bands) compare across shops. Never value a proposal with PlanChatClaim after the fact: once settled it sees nothing left to claim. No order found = no_order, kept out of the rates. GetChatAiClaims counts one case per conversation and order, taking its best-matching draft. Jev's reply verdict is the honesty check, since refunds are not linked to chats.

## Chats handed over by the AI shopping assistant stay out of chat figures
INI-245 (12 Nov 2026): a chat the assistant handed over carries chat_sessions.shopping_assistant_conversation_id. GetChatAiCoverage and the default GetChatReports figures (fromAssistant false) exclude it, because it would read as an unanswered chat overnight and as CS-assistant work; the chat reports page shows only those chats with from_assistant=1, and the AI Assistant report counts hand-overs from shopping_assistant_hand_overs. The assistant transcript is read from the assistant's own tables by GetChatAssistantTranscript and never copied into chat_messages, because GetChatReports counts an ai message as the first reply. These chats stay in the normal inbox, unclaimed queue and alerts (WithUnclaimedChatSessions and AlertUnclaimedChatSessions are untouched on purpose).

## Knowledge notes reach a shop, an organisation or the group
Raul, 14 Nov 2026 (INI-257). A staff note (chat_knowledge_entries, is_manual) reaches one shop (shop_id set), every shop of an organisation (shop_id null, organisation_id set) or the whole group (both null); forShop reads all three.
- Shop notes: anyone who acts on the shop's chat.
- Organisation notes: org admins or group admins only. Group notes: group admins only (UpdateShopChatKnowledgeNote::canManageReach). Editing needs rights on both the old and the new reach.
- A proposed note (waiting for a second yes) cannot be widened.
- rules() is skipped for DELETE and the status route: laravel-actions applies rules() to every route of the action, so asking for title/body there refused every Remove and Use it.
