<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_inbox_timings', function (Blueprint $table) {
            $table->string('page', 100)->nullable()->comment('Inertia page the staff member was on (e.g. Org/Chat/Inbox). Since 2 Nov 2026 freeze and slow_frame are recorded on every grp page; earlier rows have none and are the inbox page or the chat pane over another page, while newer chat-pane freezes carry the page under it');
        });
    }

    public function down(): void
    {
        Schema::table('chat_inbox_timings', function (Blueprint $table) {
            $table->dropColumn('page');
        });
    }
};
