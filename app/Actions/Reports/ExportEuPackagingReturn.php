<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reports;

use App\Actions\OrgAction;
use App\Actions\Reports\UI\IndexPackagingReport;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The organisation's EU scheme return as a csv: kg per material of the scheme, split into sales and shipment packaging.
 */
class ExportEuPackagingReturn extends OrgAction
{
    public const array COLUMNS = ['period_from', 'period_to', 'material', 'subcategory', 'sales_packaging_kg', 'shipment_packaging_kg', 'total_kg'];

    /**
     * @return list<list<string|float|null>>
     */
    public function handle(array $return): array
    {
        return array_map(fn (array $row) => [
            $return['from'], $return['to'], $row['scheme_material'], $row['scheme_subcategory'], $row['sales_kg'], $row['shipment_kg'], $row['kg'],
        ], array_values(array_filter($return['rows'], fn (array $row) => $row['kg'] > 0)));
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('org-reports.'.$this->organisation->id);
    }

    public function asController(Organisation $organisation, ActionRequest $request): StreamedResponse
    {
        $this->initialisation($organisation, $request);

        [$from, $to] = IndexPackagingReport::make()->period($request);
        $return      = GetEuPackagingReturn::run($organisation, $from, $to);
        $rows        = $this->handle($return);

        return response()->streamDownload(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, self::COLUMNS);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        }, "packaging-return-{$organisation->slug}-{$return['from']}-{$return['to']}.csv", ['Content-Type' => 'text/csv']);
    }
}
