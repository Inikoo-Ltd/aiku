<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reports;

use App\Actions\OrgAction;
use App\Actions\Reports\UI\IndexPackagingReport;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The UK return as a Report Packaging Data file: one row per activity, type, class, material and RAM rating, whole kg.
 */
class ExportUkPackagingReturn extends OrgAction
{
    public const array COLUMNS = [
        'organisation_id', 'subsidiary_id', 'organisation_size', 'submission_period', 'packaging_activity', 'packaging_type', 'packaging_class',
        'packaging_material', 'packaging_material_subtype', 'from_nation', 'to_nation', 'packaging_material_weight', 'packaging_material_units',
        'transitional_packaging_units', 'ram_rag_rating',
    ];

    /**
     * @return list<list<string|int|null>>
     */
    public function handle(Organisation $organisation, array $return): array
    {
        $rows = [];
        foreach ($return['lines'] as $line) {
            $kg = (int)round($line['kg']);
            if ($kg === 0) {
                continue;
            }
            $rows[] = [
                data_get($organisation->settings, 'epr.uk.organisation_id'), null, 'L', $return['submission_period'], $line['activity'], $line['type'], $line['class'],
                $line['material'], null, $line['from_nation'] ?? null, $line['to_nation'] ?? null, $kg, null, null, $line['ram'],
            ];
        }

        return $rows;
    }

    public function asController(Organisation $organisation, ActionRequest $request): StreamedResponse
    {
        $this->initialisation($organisation, $request);

        [$from, $to] = IndexPackagingReport::make()->period($request);
        $return      = GetUkPackagingReturn::run($organisation, $from, $to, $request->boolean('own_brand_imports'));
        $rows        = $this->handle($organisation, $return);

        return response()->streamDownload(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, self::COLUMNS);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        }, "uk-packaging-return-{$organisation->slug}-{$return['from']}-{$return['to']}.csv", ['Content-Type' => 'text/csv']);
    }
}
