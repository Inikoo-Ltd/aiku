<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 18:40:59 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\Portfolio\PortfolioTypeEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {

            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index()->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unsignedInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->string('reference')->index()->nullable()->comment('This is the reference that the customer uses to identify the product');
            $table->string('type')->default(PortfolioTypeEnum::MANUAL->value);
            $table->boolean('status')->index()->default(true);
            $table->dateTimeTz('last_added_at')->nullable();
            $table->dateTimeTz('last_removed_at')->nullable()->nullable;
            $table->jsonb('data');
            $table->jsonb('settings');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
            $table->unique(['customer_id', 'product_id']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
