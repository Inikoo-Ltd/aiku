---
paths:
  - 'app/Mcp/Tools/CustomerService*'
---

# Tools

## AI run claims lapse when the run goes quiet, not by the clock
CS suggestion routines run on more than one claude.ai account, and every account signs in to Aiku as the same user, so chat_ai_runs.account (from start_run) is the only way to tell them apart. A chat claim is free once its run has finished, its run has made no run_id call for ChatAiRun::IDLE_MINUTES (last_activity_at, touched by the inbox and write tools), or after ChatSession::AI_CLAIM_HOURS. A run that runs out of usage dies without calling finish_run, so never lengthen these timeouts. Until INI-169 (31 Oct 2026) the brief never called claim_chats, so a claim count of zero in prod means the brief stopped claiming, not that the code is broken.

## CS order facts carry Merlin's dispatch outlook, never the queue
INI-400 (19 Nov 2026): CustomerServiceChatTool::order() returns dispatch_outlook from GetOrderDispatchOutlook (the same estimate Merlin gives the customer, INI-392), so a staff draft and Merlin say the same thing. Only kind, the from/to dates, waiting items and a fixed note reach the routine; the queue size, pace and delivery_note_id stay inside the outlook class (commercially sensitive). It does not store a shopping_assistant_dispatch_outlooks row or raise the stuck-order task; those stay Merlin's.

## CS drafts copy the shop's own staff answers and never leave [[gaps]]
Raul, 20 Nov 2026 (INI-402). CustomerServiceChatTool returns how_staff_answered: FindChatReplyExamples, the shop's own past staff replies (chat_reply_examples, refreshed nightly by chat:hydrate-reply-examples) nearest in meaning to the customer's latest message, same shop only and from the last 18 months, because shops of one organisation write in different languages and keep different habits. The draft gives the house answer in staff's words and length; the one exception written into how_to_answer is stock questions (stock and pack size, "not reserved until you order", never only "check the product page"). Drafts always write the full reply: where staff decide, the shop's usual decision, marked usual_decision in questions so the draft box says "our usual decision, check"; where a detail is missing, a question to the customer. A gap made "sent as is" impossible and cut B2B adoption from 72% to 57%. The composer still refuses a stray [[ ]] as a safety net. Collected chat pairs must be answered within HydrateChatReplyExamples::ANSWERED_WITHIN_DAYS, or win-back and voucher mails become "answers".
