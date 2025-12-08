<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Apr 2024 21:20:18 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Fulfilment\RentalAgreement\RentalAgreementBillingCycleEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('rental_agreements', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('reference')->nullable()->index()->collation('und_ci');
            $table->unsignedInteger('fulfilment_customer_id');
            $table->foreign('fulfilment_customer_id')->references('id')->on('fulfilment_customers');
            $table->unsignedInteger('fulfilment_id');
            $table->foreign('fulfilment_id')->references('id')->on('fulfilments');
            $table->unsignedInteger('current_snapshot_id')->nullable()->index();

            $table->string('state')->index()->default(RentalAgreementStateEnum::DRAFT);
            $table->string('billing_cycle')->default(RentalAgreementBillingCycleEnum::WEEKLY->value);
            $table->unsignedSmallInteger('pallets_limit')->nullable()->comment('Agreed max number pallets space allocated');

            $table->jsonb('data');

            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_agreements');
    }
};
