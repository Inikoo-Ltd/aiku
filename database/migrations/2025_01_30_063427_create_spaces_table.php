<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Jan 2025 14:53:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Fulfilment\Space\SpaceStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->string('slug')->nullable()->unique()->collation('und_ns');
            $table->string('reference')->nullable()->index()->collation('und_ci');
            $table->unsignedInteger('fulfilment_id')->index();
            $table->foreign('fulfilment_id')->references('id')->on('fulfilments')->onDelete('cascade');
            $table->unsignedInteger('fulfilment_customer_id')->index();
            $table->foreign('fulfilment_customer_id')->references('id')->on('fulfilment_customers')->onDelete('cascade');
            $table->unsignedSmallInteger('rental_id')->nullable()->index();
            $table->foreign('rental_id')->references('id')->on('rentals')->onDelete('cascade');
            $table->unsignedSmallInteger('rental_agreement_clause_id')->nullable()->index();
            $table->foreign('rental_agreement_clause_id')->references('id')->on('rental_agreement_clauses')->setNullOnDelete();
            $table->string('state')->index()->default(SpaceStateEnum::RESERVED->value);
            $table->unsignedSmallInteger('current_recurring_bill_id')->nullable()->index();
            $table->foreign('current_recurring_bill_id')->references('id')->on('recurring_bills')->setNullOnDelete();
            $table->dateTimeTz('start_at')->nullable();
            $table->dateTimeTz('end_at')->nullable();
            $table->boolean('exclude_weekend')->default(false);
            $table->jsonb('data');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
