<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

use App\Actions\Comms\Mailbox\ProcessInboundEmail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Couriers write to the same mailbox as customers, about deliveries rather than orders, and
     * are answered by whoever deals with the carriers. They get a row of their own in the inbox
     * instead of sitting in the customers' queue. The conversations still open are filed now.
     */
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->boolean('is_carrier')->default(false)->index();
        });

        DB::table('chat_sessions')
            ->where('channel', 'email')
            ->whereNull('web_user_id')
            ->where('status', '!=', 'closed')
            ->whereNotNull(DB::raw("metadata->>'email_from'"))
            ->select(['id', DB::raw("metadata->>'email_from' as email_from")])
            ->orderBy('id')
            ->each(function ($session) {
                if (ProcessInboundEmail::isCarrierAddress($session->email_from)) {
                    DB::table('chat_sessions')->where('id', $session->id)->update(['is_carrier' => true]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropColumn('is_carrier');
        });
    }
};
