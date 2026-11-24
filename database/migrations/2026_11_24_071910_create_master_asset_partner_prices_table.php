<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('master_asset_partner_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('master_asset_id');
            $table->foreign('master_asset_id')->references('id')->on('master_assets')->cascadeOnDelete();
            $table->unsignedSmallInteger('org_partner_id')->index();
            $table->foreign('org_partner_id')->references('id')->on('org_partners')->cascadeOnDelete();
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->decimal('price', 16)->comment('price per outer the partner pays, in currency_id');
            $table->timestampsTz();
            $table->unique(['master_asset_id', 'org_partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_asset_partner_prices');
    }
};
