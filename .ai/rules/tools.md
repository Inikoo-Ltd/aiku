---
paths:
  - 'app/Mcp/Tools/CustomerService*'
---

# Tools

## AI run claims lapse when the run goes quiet, not by the clock
CS suggestion routines run on more than one claude.ai account, and every account signs in to Aiku as the same user, so chat_ai_runs.account (from start_run) is the only way to tell them apart. A chat claim is free once its run has finished, its run has made no run_id call for ChatAiRun::IDLE_MINUTES (last_activity_at, touched by the inbox and write tools), or after ChatSession::AI_CLAIM_HOURS. A run that runs out of usage dies without calling finish_run, so never lengthen these timeouts. Until INI-169 (31 Oct 2026) the brief never called claim_chats, so a claim count of zero in prod means the brief stopped claiming, not that the code is broken.

## CS order facts carry Merlin's dispatch outlook, never the queue
INI-400 (19 Nov 2026): CustomerServiceChatTool::order() returns dispatch_outlook from GetOrderDispatchOutlook (the same estimate Merlin gives the customer, INI-392), so a staff draft and Merlin say the same thing. Only kind, the from/to dates, waiting items and a fixed note reach the routine; the queue size, pace and delivery_note_id stay inside the outlook class (commercially sensitive). It does not store a shopping_assistant_dispatch_outlooks row or raise the stuck-order task; those stay Merlin's.
