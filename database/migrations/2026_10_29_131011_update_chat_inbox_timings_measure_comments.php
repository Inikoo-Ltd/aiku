<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement("COMMENT ON COLUMN chat_inbox_timings.measure IS 'inbox_open, inbox_page (the page''s share of inbox_open: request, download, first render), conversation_open, conversation_fetch (the messages request alone, from sent to answered), reply_sent or freeze'");
        DB::statement("COMMENT ON TABLE chat_inbox_timings IS 'Customer Inbox speed measured in the staff browsers (INI-145), one row per measurement: inbox_open from the click (or page start) until the conversation list is painted, inbox_page the part of it until the page is on screen, conversation_open from opening a chat until its messages are painted, conversation_fetch the messages request alone, reply_sent from Send until the server accepted it, freeze for each time the page could not respond for 200 ms or more. Report the 90th percentile, e.g. percentile_cont(0.9) WITHIN GROUP (ORDER BY duration_ms)'");
        DB::statement("COMMENT ON COLUMN chat_inbox_timings.is_full_load IS 'inbox_open and inbox_page only: true when the browser loaded the whole app, false when moving to the inbox from another page'");
    }

    public function down(): void
    {
        DB::statement("COMMENT ON TABLE chat_inbox_timings IS 'Customer Inbox speed measured in the staff browsers (INI-145), one row per measurement: inbox_open from the click (or page start) until the conversation list is painted, conversation_open from opening a chat until its messages are painted, reply_sent from Send until the server accepted it, freeze for each time the page could not respond for 200 ms or more. Report the 90th percentile, e.g. percentile_cont(0.9) WITHIN GROUP (ORDER BY duration_ms)'");
        DB::statement("COMMENT ON COLUMN chat_inbox_timings.measure IS 'inbox_open, conversation_open, reply_sent or freeze'");
        DB::statement("COMMENT ON COLUMN chat_inbox_timings.is_full_load IS 'inbox_open only: true when the browser loaded the whole app, false when moving to the inbox from another page'");
    }
};
