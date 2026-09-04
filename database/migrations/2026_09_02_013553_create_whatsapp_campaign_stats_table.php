<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_campaign_stats', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('whatsapp_campaign_id')->unique();
            $table->foreign('whatsapp_campaign_id')->references('id')->on('whatsapp_campaigns')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedInteger('number_recipients')->default(0);
            $table->unsignedInteger('number_sent')->default(0);
            $table->unsignedInteger('number_delivered')->default(0);
            $table->unsignedInteger('number_read')->default(0);
            $table->unsignedInteger('number_clicked')->default(0)->comment('WhatsApp click tracking is not implemented yet, stays 0');
            $table->unsignedInteger('number_failed')->default(0);

            $table->timestampsTz();
        });

        /* Campaigns created before this table existed would otherwise read a null stats
           relation on every page load, so each one gets a zeroed row to hydrate into. */
        DB::table('whatsapp_campaigns')->orderBy('id')->chunk(1000, function ($campaigns) {
            DB::table('whatsapp_campaign_stats')->insert(
                $campaigns->map(fn ($campaign) => [
                    'whatsapp_campaign_id' => $campaign->id,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ])->all()
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaign_stats');
    }
};
