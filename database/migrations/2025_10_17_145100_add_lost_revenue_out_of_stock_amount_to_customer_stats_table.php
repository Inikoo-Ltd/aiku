<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 Oct 2025 14:52:32 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {

            $table->decimal('lost_revenue_out_of_stock_amount', 16)->default(0);
            $table->decimal('lost_revenue_out_of_stock_amount_org_currency', 16)->default(0);
            $table->decimal('lost_revenue_out_of_stock_amount_grp_currency', 16)->default(0);

            $table->decimal('lost_revenue_replacements_amount', 16)->default(0);
            $table->decimal('lost_revenue_replacements_amount_org_currency', 16)->default(0);
            $table->decimal('lost_revenue_replacements_amount_grp_currency', 16)->default(0);

            $table->decimal('lost_revenue_compensations_amount', 16)->default(0);
            $table->decimal('lost_revenue_compensations_amount_org_currency', 16)->default(0);
            $table->decimal('lost_revenue_compensations_amount_grp_currency', 16)->default(0);

            $table->decimal('lost_revenue_other_amount', 16)->default(0);
            $table->decimal('lost_revenue_other_amount_org_currency', 16)->default(0);
            $table->decimal('lost_revenue_other_amount_grp_currency', 16)->default(0);
            
        });
    }

    public function down(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->dropColumn('lost_revenue_out_of_stock_amount');
            $table->dropColumn('lost_revenue_out_of_stock_amount_org_currency');
            $table->dropColumn('lost_revenue_out_of_stock_amount_grp_currency');

            $table->dropColumn('lost_revenue_replacements_amount');
            $table->dropColumn('lost_revenue_replacements_amount_org_currency');
            $table->dropColumn('lost_revenue_replacements_amount_grp_currency');

            $table->dropColumn('lost_revenue_compensations_amount');
            $table->dropColumn('lost_revenue_compensations_amount_org_currency');
            $table->dropColumn('lost_revenue_compensations_amount_grp_currency');

            $table->dropColumn('lost_revenue_other_amount');
            $table->dropColumn('lost_revenue_other_amount_org_currency');
            $table->dropColumn('lost_revenue_other_amount_grp_currency');
        });
    }
};
