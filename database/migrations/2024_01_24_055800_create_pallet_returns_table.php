<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 06:56:13 CTS, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Stubs\Migrations\HasFulfilmentDelivery;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasOrderAmountTotals;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasFulfilmentDelivery;
    use HasGroupOrganisationRelationship;
    use HasOrderAmountTotals;
    use HasSoftDeletes;

    public function up(): void
    {
        if (! Schema::hasTable('pallet_returns')) {
            Schema::create('pallet_returns', function (Blueprint $table) {
                $table->increments('id');
                $table = $this->getPalletIOFields($table);
                $table->string('type')->default(PalletTypeEnum::PALLET->value)->comment('Pallet|StoredItem')->index();
                $table->string('state')->default(PalletReturnStateEnum::IN_PROCESS->value);

                foreach (PalletReturnStateEnum::cases() as $state) {
                    $table->dateTimeTz("{$state->snake()}_at")->nullable();
                }
                $table->dateTimeTz('date')->nullable();
                $table->jsonb('data')->nullable();
                $table->text('customer_notes')->nullable();
                $table->text('public_notes')->nullable();
                $table->text('internal_notes')->nullable();

                $table->unsignedInteger('delivery_address_id')->index()->nullable();
                $table->foreign('delivery_address_id')->references('id')->on('addresses');
                $table->unsignedInteger('collection_address_id')->index()->nullable();
                $table->foreign('collection_address_id')->references('id')->on('addresses');

                $table = $this->currencyFields($table);
                $table = $this->orderTotalAmounts($table);

                $table->timestampsTz();
                $this->softDeletes($table);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('return_pallets');
    }
};
