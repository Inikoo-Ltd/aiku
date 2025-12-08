<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 15 Oct 2025 14:48:34 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('platform_portfolio_logs', function (Blueprint $table) {
            $table->id();

            /** @var Blueprint $table */
            $table = $this->groupOrgRelationship($table);

            $table->unsignedBigInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');

            $table->unsignedBigInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');

            $table->unsignedBigInteger('customer_sales_channel_id')->index();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();

            $table->unsignedBigInteger('portfolio_id')->index();
            $table->foreign('portfolio_id')->references('id')->on('portfolios');

            $table->unsignedBigInteger('platform_id')->index();
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
            $table->string('platform_type');

            $table->string('type');
            $table->string('status');
            $table->longText('response')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_portfolio_logs');
    }
};
