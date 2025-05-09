<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 20:01:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Accounting\MitSavedCard\MitSavedCardStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('mit_saved_cards', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');

            $table->unsignedInteger('payment_account_shop_id')->index();
            $table->foreign('payment_account_shop_id')->references('id')->on('customers');

            $table->string('token')->nullable();
            $table->string('last_four_digits')->nullable()->index();
            $table->string('card_type')->nullable()->comment('Visa, Mastercard, etc');
            $table->date('expires_at')->nullable()->index()->comment('Card expiration date');
            $table->string('label')->nullable()->comment('User defined label');


            $table->string('state')->nullable()->index()->default(MitSavedCardStateEnum::IN_PROCESS->value);

            $table->smallInteger('priority')->default(1)->index();
            $table->jsonb('data');
            $table->ulid()->index();
            $table->datetimeTz('processed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('mit_saved_cards');
    }
};
