<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Apr 2024 08:07:15 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasOrderFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    use HasOrderFields;

    public function up(): void
    {
        Schema::create('recurring_bill_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('recurring_bill_id')->index();
            $table->unsignedInteger('fulfilment_id')->index();
            $table->foreign('fulfilment_id')->references('id')->on('fulfilments');
            $table->unsignedInteger('fulfilment_customer_id')->index();
            $table->foreign('fulfilment_customer_id')->references('id')->on('fulfilment_customers');
            $table->dateTimeTz('start_date');
            $table->dateTimeTz('end_date')->nullable();
            $table->nullableMorphs('item');
            $table->unsignedInteger('asset_id')->index();
            $table->foreign('asset_id')->references('id')->on('assets');
            $table->unsignedInteger('historic_asset_id')->index();
            $table->foreign('historic_asset_id')->references('id')->on('historic_assets');
            $table->unsignedInteger('rental_agreement_clause_id')->nullable()->index();
            $table->foreign('rental_agreement_clause_id')->references('id')->on('rental_agreement_clauses');
            $table->decimal('quantity', 16, 3)->default(0);
            $table = $this->orderMoneyFields($table);

            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->index();
        });

    }

    public function down(): void
    {

        Schema::dropIfExists('recurring_bill_transactions');
    }
};
