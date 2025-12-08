<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Nov 2024 09:15:48 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_transaction_has_offer_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();

            $table->unsignedBigInteger('invoice_transaction_id');
            $table->foreign('invoice_transaction_id')->references('id')->on('invoice_transactions')->cascadeOnDelete();

            $table->string('model_type')->index()->nullable();
            $table->unsignedInteger('model_id')->index()->nullable();

            $table->unsignedSmallInteger('offer_campaign_id');
            $table->foreign('offer_campaign_id')->references('id')->on('offer_campaigns');
            $table->unsignedInteger('offer_id');
            $table->foreign('offer_id')->references('id')->on('offers');
            $table->unsignedInteger('offer_component_id');
            $table->foreign('offer_component_id')->references('id')->on('offer_components');
            $table->decimal('discounted_amount', 12, 2)->default(0);
            $table->decimal('discounted_percentage', 6, 4)->default(0);
            $table->text('info')->nullable();
            $table->jsonb('data');
            $table->timestampsTz();
            $table->dateTimeTz('fetched_at')->nullable();
            $table->dateTimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable();
            $table->string('source_alt_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_transaction_has_offer_components');
    }
};
