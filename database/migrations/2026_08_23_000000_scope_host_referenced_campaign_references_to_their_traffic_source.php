<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * A referring host is the same string in every shop, so a globally unique `reference` let the
     * first shop that ever saw chatgpt.com own that campaign row for the whole group; every other
     * shop's visitors from the same host were attached to the channel with no campaign, invisible
     * to their referrers list. Host-referenced campaigns are now unique per traffic source.
     *
     * Ad-platform campaign ids and mailshot references keep the table-wide uniqueness that the cost
     * webhook, the email click path and the mailshot performance view were built on, through a
     * partial index that leaves the host-referenced channels out.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE traffic_source_campaigns DROP CONSTRAINT IF EXISTS traffic_source_campaigns_reference_unique');
        DB::statement('
            CREATE UNIQUE INDEX traffic_source_campaigns_traffic_source_id_reference_unique
            ON traffic_source_campaigns (traffic_source_id, reference)
        ');
        DB::statement("
            CREATE UNIQUE INDEX traffic_source_campaigns_platform_reference_unique
            ON traffic_source_campaigns (reference)
            WHERE type NOT IN ({$this->hostReferencedTypes()})
        ");
    }

    /**
     * Fails, leaving the new indexes in place, once any shop holds a host another shop already has:
     * those rows cannot satisfy a table-wide unique reference again without deleting attribution.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE traffic_source_campaigns ADD CONSTRAINT traffic_source_campaigns_reference_unique UNIQUE (reference)');
        DB::statement('DROP INDEX IF EXISTS traffic_source_campaigns_platform_reference_unique');
        DB::statement('DROP INDEX IF EXISTS traffic_source_campaigns_traffic_source_id_reference_unique');
    }

    private function hostReferencedTypes(): string
    {
        return implode(', ', array_map(
            fn (string $type) => "'".$type."'",
            TrafficSourcesTypeEnum::hostReferencedValues()
        ));
    }
};
