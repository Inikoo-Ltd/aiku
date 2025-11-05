<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Mar 2025 19:15:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {

            // Foreign key indices for parent entities

            $table->unsignedSmallInteger('post_room_id')->nullable()->index();
            $table->foreign('post_room_id')->references('id')->on('post_rooms')->nullOnDelete();
            $table->unsignedSmallInteger('org_post_room_id')->nullable()->index();
            $table->foreign('org_post_room_id')->references('id')->on('org_post_rooms')->nullOnDelete();
            $table->unsignedInteger('mailshot_id')->nullable()->index();
            $table->foreign('mailshot_id')->references('id')->on('mailshots')->nullOnDelete();

            $table->index('outbox_id');

            // Indices for commonly sorted columns
            $table->index('sent_at');
            $table->index('number_reads');
            $table->index('number_clicks');
            $table->index('number_email_tracking_events');
            $table->index('state');
            $table->index('mask_as_spam');
        });
    }


    public function down(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->dropIndex(['post_room_id']);
            $table->dropIndex(['outbox_id']);
            $table->dropIndex(['mailshot_id']);
            $table->dropIndex(['group_id']);
            $table->dropIndex(['shop_id']);

            $table->dropIndex(['sent_at']);
            $table->dropIndex(['number_reads']);
            $table->dropIndex(['number_clicks']);
            $table->dropIndex(['number_email_tracking_events']);
            $table->dropIndex(['state']);
            $table->dropIndex(['mask_as_spam']);
        });
    }
};
