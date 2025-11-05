<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Feb 2023 14:18:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('payment_service_provider_id')->index();
            $table->foreign('payment_service_provider_id')->references('id')->on('payment_service_providers');
            $table->unsignedSmallInteger('org_payment_service_provider_id')->index();
            $table->foreign('org_payment_service_provider_id')->references('id')->on('org_payment_service_providers');
            $table->unsignedSmallInteger('payment_account_id')->index();
            $table->foreign('payment_account_id')->references('id')->on('payment_accounts');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->string('type')->index();
            $table->string('reference')->index()->collation('und_ns')->nullable();
            $table->string('status')->index()->default(PaymentStatusEnum::IN_PROCESS->value);
            $table->string('state')->index()->default(PaymentStateEnum::IN_PROCESS->value);
            $table->string('subsequent_status')->index()->nullable();
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->decimal('amount', 12);
            $table->decimal('grp_amount', 12);
            $table->decimal('org_amount', 12);
            $table->jsonb('data');
            $table->dateTimeTz('date')->index()->comment('Most relevant date at current state');
            $table->dateTimeTz('completed_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->timestampstz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->boolean('with_refund')->default(false);
            $table->string('source_id')->index()->nullable();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
