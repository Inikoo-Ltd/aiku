<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Enums\CRM\TrafficSource\GoogleAdsCampaignStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Gives a campaign a life of its own in Aiku rather than only a mirror of one at Google.
     *
     * A campaign is now written here first, added to over as many sittings as it takes, and sent to
     * Google only when somebody says so. Until then it has no Google id at all, which is why the
     * reference has to be allowed to be empty: it is the id of a thing that does not exist yet.
     *
     * The three timestamps are what the campaign page draws its timeline from. They are separate
     * columns rather than a history table for the same reason a mailshot's are: there are three of
     * them, they happen once each, and a page wants them all at once.
     */
    public function up(): void
    {
        Schema::table('traffic_source_campaigns', function (Blueprint $table) {
            $table->string('state')->nullable()->index();
            $table->timestampTz('in_process_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('serving_at')->nullable();

            /* What Google said when it last refused to create this campaign, kept so the reason is on
               screen beside the thing it names rather than gone by the time anybody looks. */
            $table->text('last_error')->nullable();
        });

        /* A reporting view reads this column, and Postgres will not alter a column a view depends on.
           The definition is read back from the database rather than copied in here, so this survives
           whatever the view has been amended into since it was first written. */
        $view = 'marketing_mailshot_performance';
        $definition = DB::selectOne('SELECT pg_get_viewdef(?::regclass, true) AS def', [$view])?->def;

        if ($definition) {
            DB::statement("DROP VIEW {$view}");
        }

        Schema::table('traffic_source_campaigns', function (Blueprint $table) {
            $table->string('reference')->nullable()->change();
        });

        if ($definition) {
            DB::statement("CREATE VIEW {$view} AS {$definition}");
        }

        /* Everything already here was read from Google, so it is published by definition. Whether it
           is serving is Google's own primary status, the same field the listing colours. */
        DB::statement(
            "UPDATE traffic_source_campaigns
                SET state = CASE
                        WHEN COALESCE(data->>'primary_status', data->>'status') IN ('ELIGIBLE', 'ENABLED', 'LIMITED')
                            THEN ?
                        ELSE ?
                    END,
                    published_at = created_at,
                    serving_at = CASE
                        WHEN COALESCE(data->>'primary_status', data->>'status') IN ('ELIGIBLE', 'ENABLED', 'LIMITED')
                            THEN created_at
                    END",
            [GoogleAdsCampaignStateEnum::PUBLISHED_SERVING->value, GoogleAdsCampaignStateEnum::PUBLISHED_PAUSED->value]
        );
    }

    public function down(): void
    {
        Schema::table('traffic_source_campaigns', function (Blueprint $table) {
            $table->dropColumn(['state', 'in_process_at', 'published_at', 'serving_at', 'last_error']);
        });
    }
};
