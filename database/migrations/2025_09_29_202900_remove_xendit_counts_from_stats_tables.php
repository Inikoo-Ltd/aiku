<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Sept 2025 20:30:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Junie (AI) for Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Sept 2025 20:29:00 Malaysia Time
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // org_payment_service_provider_stats
        if (Schema::hasColumn('org_payment_service_provider_stats', 'number_payment_accounts_type_xendit')) {
            Schema::table('org_payment_service_provider_stats', function (Blueprint $table) {
                $table->dropColumn('number_payment_accounts_type_xendit');
            });
        }

        // organisation_accounting_stats
        if (Schema::hasColumn('organisation_accounting_stats', 'number_payment_accounts_type_xendit')) {
            Schema::table('organisation_accounting_stats', function (Blueprint $table) {
                $table->dropColumn('number_payment_accounts_type_xendit');
            });
        }

        // payment_service_provider_stats
        if (Schema::hasColumn('payment_service_provider_stats', 'number_payment_accounts_type_xendit')) {
            Schema::table('payment_service_provider_stats', function (Blueprint $table) {
                $table->dropColumn('number_payment_accounts_type_xendit');
            });
        }

        // shop_accounting_stats
        if (Schema::hasColumn('shop_accounting_stats', 'number_payment_accounts_type_xendit')) {
            Schema::table('shop_accounting_stats', function (Blueprint $table) {
                $table->dropColumn('number_payment_accounts_type_xendit');
            });
        }

        // group_accounting_stats
        if (Schema::hasColumn('group_accounting_stats', 'number_payment_accounts_type_xendit')) {
            Schema::table('group_accounting_stats', function (Blueprint $table) {
                $table->dropColumn('number_payment_accounts_type_xendit');
            });
        }
    }

    public function down(): void
    {
        // org_payment_service_provider_stats
        if (!Schema::hasColumn('org_payment_service_provider_stats', 'number_payment_accounts_type_xendit')) {
            Schema::table('org_payment_service_provider_stats', function (Blueprint $table) {
                if (Schema::hasColumn('org_payment_service_provider_stats', 'number_payment_accounts_type_cash_on_delivery')) {
                    $table->integer('number_payment_accounts_type_xendit')->default(0)->after('number_payment_accounts_type_cash_on_delivery');
                } else {
                    $table->integer('number_payment_accounts_type_xendit')->default(0)->after('number_payment_accounts');
                }
            });
        }

        // organisation_accounting_stats
        if (!Schema::hasColumn('organisation_accounting_stats', 'number_payment_accounts_type_xendit')) {
            Schema::table('organisation_accounting_stats', function (Blueprint $table) {
                if (Schema::hasColumn('organisation_accounting_stats', 'number_payment_accounts_type_cash_on_delivery')) {
                    $table->integer('number_payment_accounts_type_xendit')->default(0)->after('number_payment_accounts_type_cash_on_delivery');
                } else {
                    $table->integer('number_payment_accounts_type_xendit')->default(0)->after('number_payment_accounts');
                }
            });
        }

        // payment_service_provider_stats
        if (!Schema::hasColumn('payment_service_provider_stats', 'number_payment_accounts_type_xendit')) {
            Schema::table('payment_service_provider_stats', function (Blueprint $table) {
                if (Schema::hasColumn('payment_service_provider_stats', 'number_payment_accounts_type_cash_on_delivery')) {
                    $table->integer('number_payment_accounts_type_xendit')->default(0)->after('number_payment_accounts_type_cash_on_delivery');
                } else {
                    $table->integer('number_payment_accounts_type_xendit')->default(0)->after('number_payment_accounts');
                }
            });
        }

        // shop_accounting_stats
        if (!Schema::hasColumn('shop_accounting_stats', 'number_payment_accounts_type_xendit')) {
            Schema::table('shop_accounting_stats', function (Blueprint $table) {
                if (Schema::hasColumn('shop_accounting_stats', 'number_payment_accounts_type_cash_on_delivery')) {
                    $table->integer('number_payment_accounts_type_xendit')->default(0)->after('number_payment_accounts_type_cash_on_delivery');
                } else {
                    $table->integer('number_payment_accounts_type_xendit')->default(0)->after('number_payment_accounts');
                }
            });
        }

        // group_accounting_stats
        if (!Schema::hasColumn('group_accounting_stats', 'number_payment_accounts_type_xendit')) {
            Schema::table('group_accounting_stats', function (Blueprint $table) {
                if (Schema::hasColumn('group_accounting_stats', 'number_payment_accounts_type_cash_on_delivery')) {
                    $table->integer('number_payment_accounts_type_xendit')->default(0)->after('number_payment_accounts_type_cash_on_delivery');
                } else {
                    $table->integer('number_payment_accounts_type_xendit')->default(0)->after('number_payment_accounts');
                }
            });
        }
    }
};
