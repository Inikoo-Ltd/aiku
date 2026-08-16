<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Oct 2024 11:58:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\Inventory\Location;
use App\Models\Inventory\WarehouseArea;
use App\Transfers\Aurora\History\Parsers\ParseCustomerHistory;
use App\Transfers\Aurora\History\Parsers\ParseLocationHistory;
use App\Transfers\Aurora\History\Parsers\ParseProductHistory;
use App\Transfers\Aurora\History\Parsers\ParseProspectHistory;
use Illuminate\Support\Facades\DB;

class FetchAuroraHistory extends FetchAurora
{
    protected const array PARSERS = [
        'Customer' => ParseCustomerHistory::class,
        'Prospect' => ParseProspectHistory::class,
        'Product' => ParseProductHistory::class,
        'Location' => ParseLocationHistory::class,
        'Warehouse Area' => ParseLocationHistory::class,
    ];

    protected function parseModel(): void
    {
        $parser = self::PARSERS[$this->auroraModelData->{'Direct Object'}] ?? null;
        if (!$parser) {
            return;
        }

        $classification = $parser::classify($this->auroraModelData);
        if ($classification['handling'] !== 'import') {
            $this->markSkippedInAurora();

            return;
        }

        $auditable = $this->parseAuditableFromHistory();
        if (!$auditable) {
            return;
        }

        $event  = $classification['event'];
        $values = $parser::extractValues($this->auroraModelData, $event, $classification['field']);

        if ($event == 'updated' && count($values['old_values']) == 0 && count($values['new_values']) == 0) {
            $this->markSkippedInAurora();

            return;
        }

        $data = $values['data'];
        if ($uploadSourceId = $this->getUploadSourceId($data)) {
            $upload = $this->parseUpload($this->organisation->id.':'.$uploadSourceId);
            if ($upload) {
                data_set($data, 'upload_id', $upload->id);
            }
            unset($data['upload_source_id']);
        }

        $user = $this->parseUserFromHistory();

        $this->parsedData['auditable'] = $auditable;
        $this->parsedData['history']   = [
            'created_at'      => $this->auroraModelData->{'History Date'},
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'History Key'},
            'fetched_at'      => now(),
            'last_fetched_at' => now(),
            'event'           => $event,
            'tags'            => $auditable->generateTags(),
            'new_values'      => $values['new_values'],
            'old_values'      => $values['old_values'],
            'data'            => $data,
        ];

        if ($user) {
            $this->parsedData['history']['user_type'] = class_basename($user);
            $this->parsedData['history']['user_id']   = $user->id;
        }
    }

    protected function getUploadSourceId(array $data): ?int
    {
        if (isset($data['upload_source_id'])) {
            return (int) $data['upload_source_id'];
        }

        if (preg_match('/change_view\(\'upload\/(\d+)/', (string) $this->auroraModelData->{'History Abstract'}, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    protected function markSkippedInAurora(): void
    {
        DB::connection('aurora')
            ->table('History Dimension')
            ->where('History Key', $this->auroraModelData->{'History Key'})
            ->update(['aiku_id' => 0]);
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('History Dimension')
            ->where('History Key', $id)->first();
    }

    protected function parseAuditableFromHistory(): Customer|Location|Product|WarehouseArea|Prospect|null
    {
        return match ($this->auroraModelData->{'Direct Object'}) {
            'Customer' => $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'}),
            'Location' => $this->parseLocation($this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'}, $this->organisationSource),
            'Product' => $this->parseProduct($this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'}),
            'Warehouse Area' => $this->parseWarehouseArea($this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'}),
            'Prospect' => $this->parseProspect($this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'}),
            default => null,
        };
    }
}
