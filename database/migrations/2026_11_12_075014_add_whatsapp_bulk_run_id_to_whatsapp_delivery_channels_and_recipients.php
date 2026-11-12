<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('whatsapp_delivery_channels', function (Blueprint $table) {
            $table->unsignedInteger('whatsapp_campaign_id')->nullable()->change();
            $table->unsignedInteger('whatsapp_bulk_run_id')->nullable()->index();
            $table->foreign('whatsapp_bulk_run_id')->references('id')->on('whatsapp_bulk_runs')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::table('whatsapp_recipients', function (Blueprint $table) {
            $table->unsignedInteger('whatsapp_campaign_id')->nullable()->change();
            $table->unsignedInteger('whatsapp_bulk_run_id')->nullable()->index();
            $table->foreign('whatsapp_bulk_run_id')->references('id')->on('whatsapp_bulk_runs')->onUpdate('cascade')->onDelete('cascade');
            $table->unique(['whatsapp_bulk_run_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_recipients', function (Blueprint $table) {
            $table->dropUnique(['whatsapp_bulk_run_id', 'phone']);
            $table->dropForeign(['whatsapp_bulk_run_id']);
            $table->dropColumn('whatsapp_bulk_run_id');
            $table->unsignedInteger('whatsapp_campaign_id')->nullable(false)->change();
        });

        Schema::table('whatsapp_delivery_channels', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_bulk_run_id']);
            $table->dropColumn('whatsapp_bulk_run_id');
            $table->unsignedInteger('whatsapp_campaign_id')->nullable(false)->change();
        });
    }
};
