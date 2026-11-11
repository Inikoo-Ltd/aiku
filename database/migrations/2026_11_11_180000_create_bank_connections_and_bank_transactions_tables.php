<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Accounting\BankConnection\BankConnectionStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bank_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->string('provider')->index();
            $table->string('name');
            $table->string('external_id')->nullable();
            $table->text('credentials')->nullable();
            $table->string('state')->default(BankConnectionStateEnum::ACTIVE->value)->index();
            $table->jsonb('sync_data')->default('{}');
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampTz('last_error_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedBigInteger('bank_connection_id');
            $table->foreign('bank_connection_id')->references('id')->on('bank_connections');
            $table->string('provider_transaction_id');
            $table->string('provider_type')->nullable()->index();
            $table->string('direction')->index();
            $table->decimal('amount', 18, 2);
            $table->unsignedSmallInteger('currency_id')->index();
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->string('status')->index();
            $table->string('account_reference')->nullable();
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_account')->nullable();
            $table->text('reference')->nullable();
            $table->text('description')->nullable();
            $table->timestampTz('booked_at')->index();
            $table->timestampTz('provider_updated_at')->nullable();
            $table->jsonb('data')->default('{}');
            $table->timestampsTz();
            $table->unique(['bank_connection_id', 'provider_transaction_id']);
            $table->index(['organisation_id', 'booked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_connections');
    }
};
