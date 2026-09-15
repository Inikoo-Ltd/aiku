<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Who the ad reached, recorded at the moment of the click.
     *
     * The state has to be frozen here rather than read back later. Ask today whether the person who
     * clicked in July is an active customer and everybody who has bought since reads as one, the
     * acquisition count collapses to nothing, and the report concludes that paid advertising never
     * wins anyone new. What matters is what they were when we paid to reach them.
     *
     * `session_id` is the join to website_visitors, chosen over the visitor_hash both tables could
     * compute because that hash is salted with the application key: it stops being reproducible the
     * moment a database is restored anywhere with a different key, which is every developer machine.
     * Session id is plaintext on both sides and already indexed there.
     */
    public function up(): void
    {
        Schema::table('traffic_source_clicks', function (Blueprint $table) {
            $table->string('session_id')->nullable()->after('click_id');
            $table->unsignedBigInteger('web_user_id')->nullable()->after('session_id');
            $table->unsignedBigInteger('customer_id')->nullable()->after('web_user_id');
            $table->string('customer_state', 32)->nullable()->after('customer_id');

            $table->index(['session_id']);
            $table->index(['shop_id', 'customer_state', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('traffic_source_clicks', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
            $table->dropIndex(['shop_id', 'customer_state', 'created_at']);
            $table->dropColumn(['session_id', 'web_user_id', 'customer_id', 'customer_state']);
        });
    }
};
