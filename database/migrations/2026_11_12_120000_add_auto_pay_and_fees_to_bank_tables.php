<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const array PAYMENT_ACCOUNT_TYPE_STATS = [
        'group_accounting_stats',
        'organisation_accounting_stats',
        'shop_accounting_stats',
        'payment_service_provider_stats',
        'org_payment_service_provider_stats',
    ];

    public function up(): void
    {
        foreach (self::PAYMENT_ACCOUNT_TYPE_STATS as $statsTable) {
            Schema::table($statsTable, function (Blueprint $table) {
                $table->unsignedInteger('number_payment_accounts_type_bank_fee')->default(0);
            });
        }

        Schema::table('bank_connections', function (Blueprint $table) {
            $table->jsonb('settings')->default('{}');
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->decimal('fee_amount', 18, 2)->nullable();
            $table->unsignedInteger('bank_fee_payment_id')->nullable()->index();
            $table->foreign('bank_fee_payment_id')->references('id')->on('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropForeign(['bank_fee_payment_id']);
            $table->dropColumn(['fee_amount', 'bank_fee_payment_id']);
        });

        Schema::table('bank_connections', function (Blueprint $table) {
            $table->dropColumn('settings');
        });

        foreach (self::PAYMENT_ACCOUNT_TYPE_STATS as $statsTable) {
            Schema::table($statsTable, function (Blueprint $table) {
                $table->dropColumn('number_payment_accounts_type_bank_fee');
            });
        }
    }
};
