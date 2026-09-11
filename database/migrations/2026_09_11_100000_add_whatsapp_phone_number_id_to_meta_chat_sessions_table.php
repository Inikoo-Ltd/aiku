<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * A shop may answer on a second WhatsApp number reserved for support. Both numbers
     * belong to the same shop and the same channel, so without recording which one a
     * thread arrived on, a customer who has written to sales would have their support
     * message filed into that open sales conversation.
     *
     * Left null for every existing row: nothing is backfilled because a thread stored
     * before the support number existed can only have come in on the sales number, and
     * null reads as sales everywhere.
     */
    public function up(): void
    {
        Schema::table('meta_chat_sessions', function (Blueprint $table) {
            $table->string('whatsapp_phone_number_id', 50)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('meta_chat_sessions', function (Blueprint $table) {
            $table->dropColumn('whatsapp_phone_number_id');
        });
    }
};
