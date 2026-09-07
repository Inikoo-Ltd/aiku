<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\CRM\TrafficSourceCampaign\TrafficSourceCampaignTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class () extends Migration {
    /**
     * The table shipped with `number_campaigns_type_*` columns: a count of campaigns by type, on a
     * single campaign's own stats row. That is a parent-level shape copy-pasted onto a child, it has
     * never been written to, and it cannot answer anything about the campaign it belongs to.
     *
     * Replaced with the same figures traffic_source_stats carries, so a campaign can report its own
     * customers, revenue and spend, and per-campaign ROAS becomes a subtraction instead of a scan.
     * Counts are fractional because attribution shares are.
     */
    public function up(): void
    {
        Schema::table('traffic_source_campaign_stats', function (Blueprint $table) {
            foreach (TrafficSourceCampaignTypeEnum::cases() as $type) {
                $column = 'number_campaigns_type_'.Str::snake(str_replace('-', '_', $type->value));

                if (Schema::hasColumn('traffic_source_campaign_stats', $column)) {
                    $table->dropColumn($column);
                }
            }

            $table->decimal('number_customers', 12, 2)->default(0);
            $table->decimal('number_customer_purchases', 12, 2)->default(0);
            $table->decimal('total_customer_revenue', 16, 2)->default(0);
            $table->decimal('org_total_customer_revenue', 16, 2)->default(0);
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->decimal('org_total_cost', 16, 2)->default(0);

            $table->unique('traffic_source_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::table('traffic_source_campaign_stats', function (Blueprint $table) {
            $table->dropUnique(['traffic_source_campaign_id']);

            $table->dropColumn([
                'number_customers',
                'number_customer_purchases',
                'total_customer_revenue',
                'org_total_customer_revenue',
                'total_cost',
                'org_total_cost',
            ]);

            foreach (TrafficSourceCampaignTypeEnum::cases() as $type) {
                $table->unsignedInteger('number_campaigns_type_'.Str::snake(str_replace('-', '_', $type->value)))->default(0);
            }
        });
    }
};
