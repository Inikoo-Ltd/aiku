<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Sept 2024 12:26:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Billables\ShippingZoneSchema\StoreShippingZoneSchema;
use App\Actions\Billables\ShippingZoneSchema\UpdateShippingZoneSchema;
use App\Models\Billables\ShippingZoneSchema;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraShippingZoneSchemas extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:shipping_zone_schemas {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?ShippingZoneSchema
    {
        if ($shippingZoneSchemaData = $organisationSource->fetchShippingZoneSchema($organisationSourceId)) {
            if ($shippingZoneSchema = ShippingZoneSchema::where('source_id', $shippingZoneSchemaData['shipping-zone-schema']['source_id'])->first()) {
                try {
                    $shippingZoneSchema = UpdateShippingZoneSchema::make()->action(
                        shippingZoneSchema: $shippingZoneSchema,
                        modelData: $shippingZoneSchemaData['shipping-zone-schema'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $shippingZoneSchema->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $shippingZoneSchemaData['shipping-zone-schema'], 'ShippingZoneSchema', 'update');

                    return null;
                }
            } else {
                try {
                    $shippingZoneSchema = StoreShippingZoneSchema::make()->action(
                        shop: $shippingZoneSchemaData['shop'],
                        modelData: $shippingZoneSchemaData['shipping-zone-schema'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    ShippingZoneSchema::enableAuditing();
                    $this->saveMigrationHistory(
                        $shippingZoneSchema,
                        Arr::except($shippingZoneSchemaData['shipping-zone-schema'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $shippingZoneSchema->source_id);
                    DB::connection('aurora')->table('Shipping Zone Schema Dimension')
                        ->where('Shipping Zone Schema Key', $sourceData[1])
                        ->update(['aiku_id' => $shippingZoneSchema->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $shippingZoneSchemaData['shipping-zone-schema'], 'ShippingZoneSchema', 'store');

                    return null;
                }

                if ($shippingZoneSchema->is_current) {
                    $shippingZoneSchema->shop->updateQuietly(
                        [
                            'shipping_zone_schema_id' => $shippingZoneSchema->id,
                        ]
                    );
                }

                if ($shippingZoneSchema->is_current_discount) {
                    $shippingZoneSchema->shop->updateQuietly(
                        [
                            'discount_shipping_zone_schema_id' => $shippingZoneSchema->id,
                        ]
                    );
                }

                return $shippingZoneSchema;
            }
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Shipping Zone Schema Dimension')
            ->select('Shipping Zone Schema Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Shipping Zone Schema Dimension')->count();
    }
}
