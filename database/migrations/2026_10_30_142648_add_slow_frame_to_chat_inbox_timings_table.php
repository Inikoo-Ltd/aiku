<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_inbox_timings', function (Blueprint $table) {
            $table->unsignedInteger('render_ms')->nullable()->comment('slow_frame only: style and layout at the end of the frame (null when the frame had none); scripts, including requestAnimationFrame and ResizeObserver callbacks, are in source');
            $table->string('source', 255)->nullable()->comment('slow_frame only: the scripts that ran longest in the frame, longest first, as "invoker file:position function ms", separated by " | "');
        });

        DB::statement("COMMENT ON COLUMN chat_inbox_timings.measure IS 'inbox_open, inbox_page (the page''s share of inbox_open: request, download, first render), conversation_open, conversation_fetch (the messages request alone, from sent to answered), reply_sent, freeze, or slow_frame (a frame of 200 ms or more with what ran in it; the same moments as freeze, seen whole)'");
        DB::statement("COMMENT ON TABLE chat_inbox_timings IS 'Customer Inbox speed measured in the staff browsers (INI-145), one row per measurement: inbox_open from the click (or page start) until the conversation list is painted, inbox_page the part of it until the page is on screen, conversation_open from opening a chat until its messages are painted, conversation_fetch the messages request alone, reply_sent from Send until the server accepted it, freeze for each time the page could not respond for 200 ms or more, slow_frame for each frame of 200 ms or more with its longest scripts (source) and its style and layout time (render_ms). Report the 90th percentile, e.g. percentile_cont(0.9) WITHIN GROUP (ORDER BY duration_ms)'");
    }

    public function down(): void
    {
        Schema::table('chat_inbox_timings', function (Blueprint $table) {
            $table->dropColumn(['render_ms', 'source']);
        });

        DB::statement("COMMENT ON COLUMN chat_inbox_timings.measure IS 'inbox_open, inbox_page (the page''s share of inbox_open: request, download, first render), conversation_open, conversation_fetch (the messages request alone, from sent to answered), reply_sent or freeze'");
        DB::statement("COMMENT ON TABLE chat_inbox_timings IS 'Customer Inbox speed measured in the staff browsers (INI-145), one row per measurement: inbox_open from the click (or page start) until the conversation list is painted, inbox_page the part of it until the page is on screen, conversation_open from opening a chat until its messages are painted, conversation_fetch the messages request alone, reply_sent from Send until the server accepted it, freeze for each time the page could not respond for 200 ms or more. Report the 90th percentile, e.g. percentile_cont(0.9) WITHIN GROUP (ORDER BY duration_ms)'");
    }
};
