<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_conversion_events', function (Blueprint $table) {
            $table->unsignedInteger('order_id')->nullable()->index();
            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->onDelete('set null');
            $table->decimal('net_amount', 16)->nullable();
            $table->index(['website_id', 'event_type', 'event_date']);
        });

        DB::statement("CREATE UNIQUE INDEX website_conversion_events_checkout_unique ON website_conversion_events (order_id, website_visitor_id) WHERE event_type = 'checkout'");
        DB::statement("CREATE UNIQUE INDEX website_conversion_events_purchase_unique ON website_conversion_events (order_id) WHERE event_type = 'purchase'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS website_conversion_events_purchase_unique');
        DB::statement('DROP INDEX IF EXISTS website_conversion_events_checkout_unique');

        Schema::table('website_conversion_events', function (Blueprint $table) {
            $table->dropIndex(['website_id', 'event_type', 'event_date']);
            $table->dropForeign(['order_id']);
            $table->dropColumn(['order_id', 'net_amount']);
        });
    }
};
