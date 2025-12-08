<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:41:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Fulfilment\RentalAgreementClause\RentalAgreementClauseTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_agreement_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('rental_agreement_id')->index();
            $table->foreign('rental_agreement_id')->references('id')->on('rental_agreements');
            $table->unsignedSmallInteger('number_rental_agreement_snapshots')->default(0);

            $table->unsignedSmallInteger('number_rental_agreement_clauses')->default(0);
            foreach (RentalAgreementClauseTypeEnum::cases() as $case) {
                $table->unsignedInteger('number_rental_agreement_clauses_type_'.$case->snake())->default(0);
            }

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_agreement_stats');
    }
};
