<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sept 2025 19:59:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organisation_accounting_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_invoice_categories')->default(0);
            $table->unsignedSmallInteger('number_current_invoice_categories')->default(0);
            foreach (InvoiceCategoryStateEnum::cases() as $case) {
                $table->unsignedInteger('number_invoice_categories_state_'.$case->snake())->default(0);
            }
        });

        Schema::table('group_accounting_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_invoice_categories')->default(0);
            $table->unsignedSmallInteger('number_current_invoice_categories')->default(0);
            foreach (InvoiceCategoryStateEnum::cases() as $case) {
                $table->unsignedInteger('number_invoice_categories_state_'.$case->snake())->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('organisation_accounting_stats', function (Blueprint $table) {
            $table->dropColumn('number_invoice_categories');
            $table->dropColumn('number_current_invoice_categories');
            foreach (InvoiceCategoryStateEnum::cases() as $case) {
                $table->dropColumn('number_invoice_categories_state_'.$case->snake());
            }
        });

        Schema::table('group_accounting_stats', function (Blueprint $table) {
            $table->dropColumn('number_invoice_categories');
            $table->dropColumn('number_current_invoice_categories');
            foreach (InvoiceCategoryStateEnum::cases() as $case) {
                $table->dropColumn('number_invoice_categories_state_'.$case->snake());
            }
        });
    }
};
