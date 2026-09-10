<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jul 2026 22:44:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Reports\Intrastat;

use App\Actions\OrgAction;
use App\Enums\Accounting\Intrastat\IntrastatDeliveryTermsEnum;
use App\Enums\Accounting\Intrastat\IntrastatNatureOfTransactionEnum;
use App\Enums\Accounting\Intrastat\IntrastatTransportModeEnum;
use App\Helpers\IntrastatVatNumber;
use App\Models\Accounting\IntrastatExportTimeSeriesRecord;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;
use ZipArchive;

class ExportIntrastatAeat extends OrgAction
{
    public const int ROWS_PER_FILE = 9999;

    private const string PROVINCE_OF_ORIGIN = '29';
    private const string STATISTICAL_PROCEDURE = '1';
    private const string SEPARATOR = ';';
    private const string LINE_ENDING = "\r\n";

    /** @var array<string, string>|null CN 2026 codes keyed by eight-digit code, value is the supplementary unit or '-' */
    private static ?array $combinedNomenclature = null;

    public function authorize(ActionRequest $request): bool
    {
        return in_array(
            $this->organisation->id,
            $request->user()->authorisedOrganisations()->pluck('id')->toArray()
        );
    }

    /**
     * @return array{lines: list<string>, log: list<string>, errors: list<string>}
     */
    public function handle(Organisation $organisation, array $filters): array
    {
        return $this->build($this->getRecords($organisation, $filters));
    }

    /**
     * @return array{lines: list<string>, log: list<string>, errors: list<string>}
     */
    public function build(Collection $records): array
    {
        $lines  = [];
        $log    = [];
        $errors = [];

        foreach ($records as $record) {
            $series      = $record->intrastatExportTimeSeries;
            $destination = $series->country?->code ?? '';
            $origin      = $series->originCountry?->code ?? '';
            $commodity   = $this->commodityCode($series->tariff_code);
            $vat         = $series->partner_tax_number ?? IntrastatVatNumber::UNKNOWN;
            $weightKg    = (float) ($record->weight ?? 0) / 1000;
            $quantity    = (float) ($record->quantity ?? 0);
            $unit        = $this->supplementaryUnit($commodity);
            $value       = (float) ($record->value_org_currency ?? 0);
            $source      = "record {$record->id} {$record->from} {$series->tariff_code} {$destination}";

            $fields = [
                $destination,
                self::PROVINCE_OF_ORIGIN,
                ($record->delivery_terms ?? IntrastatDeliveryTermsEnum::DAP)->value,
                ($record->nature_of_transaction ?? IntrastatNatureOfTransactionEnum::OUTRIGHT_PURCHASE)->value,
                ($record->mode_of_transport ?? IntrastatTransportModeEnum::ROAD)->value,
                '',
                $commodity,
                $origin,
                self::STATISTICAL_PROCEDURE,
                $this->decimal($weightKg, 3),
                $this->supplementaryUnits($unit, $quantity, (float) ($record->weight ?? 0)),
                $this->amount($value),
                $this->amount($value),
                $vat,
            ];

            $rowErrors = $this->validateRow($fields, $destination, $origin, $commodity, $unit, $weightKg, $quantity, $value);

            foreach ($rowErrors as $rowError) {
                $errors[] = "$source: $rowError";
            }

            if ($rowErrors === []) {
                $lines[] = implode(self::SEPARATOR, $fields);
            }

            $originalVats = collect($record->partner_tax_numbers ?? [])->pluck('number')->implode(' ');
            $log[]        = implode("\t", [$source, $originalVats, $vat, $vat === IntrastatVatNumber::UNKNOWN ? 'QV: VAT charged, category not intra-EU, or VAT failed validation' : 'retained intra-EU VAT', $series->tariff_code, $commodity]);
        }

        return ['lines' => $lines, 'log' => $log, 'errors' => $errors];
    }

    /**
     * @return list<string>
     */
    protected function validateRow(array $fields, string $destination, string $origin, string $commodity, ?string $unit, float $weightKg, float $quantity, float $value): array
    {
        $errors = [];

        if (count($fields) !== 14) {
            $errors[] = 'field count is not 14';
        }
        if (!preg_match('/^[A-Z]{2}$/', $destination)) {
            $errors[] = 'destination country code missing';
        }
        if (!preg_match('/^[A-Z]{2}$/', $origin)) {
            $errors[] = 'country of origin missing on the product';
        }
        if (!preg_match('/^\d{8}$/', $commodity)) {
            $errors[] = "commodity code '$commodity' is not eight digits";
        } elseif ($unit === null) {
            $errors[] = "commodity code '$commodity' is not in the 2026 Combined Nomenclature";
        }
        if ($weightKg <= 0) {
            $errors[] = 'net mass is zero';
        }
        if (in_array($unit, ['p/st', 'pa'], true) && $quantity <= 0) {
            $errors[] = 'supplementary units is zero';
        }
        if ($value <= 0) {
            $errors[] = 'invoiced amount is zero';
        }
        if (str_contains(implode('', $fields), self::SEPARATOR)) {
            $errors[] = 'a field contains the separator';
        }

        return $errors;
    }

    protected function commodityCode(?string $tariffCode): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $tariffCode);

        return strlen($digits) === 10 ? substr($digits, 0, 8) : $digits;
    }

    protected function supplementaryUnit(string $commodity): ?string
    {
        self::$combinedNomenclature ??= json_decode(file_get_contents(database_path('intrastat/cn2026.json')), true);

        return self::$combinedNomenclature[$commodity] ?? null;
    }

    protected function supplementaryUnits(?string $unit, float $quantity, float $weightGrams): string
    {
        return match ($unit) {
            'p/st', 'pa' => $this->decimal($quantity, 3),
            'g'          => $this->decimal($weightGrams, 3),
            default      => '',
        };
    }

    /**
     * @return list<string>
     */
    public function splitFiles(array $lines): array
    {
        return array_map(
            fn (array $chunk) => implode(self::LINE_ENDING, $chunk).self::LINE_ENDING,
            array_chunk($lines, self::ROWS_PER_FILE)
        );
    }

    protected function getRecords(Organisation $organisation, array $filters): Collection
    {
        $query = IntrastatExportTimeSeriesRecord::where('intrastat_export_time_series_records.organisation_id', $organisation->id)
            ->where('intrastat_export_time_series_records.frequency', 'D')
            ->join('intrastat_export_time_series', 'intrastat_export_time_series_records.intrastat_export_time_series_id', '=', 'intrastat_export_time_series.id')
            ->with(['intrastatExportTimeSeries.country', 'intrastatExportTimeSeries.originCountry', 'intrastatExportTimeSeries.taxCategory']);

        if (!empty($filters['between']['date'])) {
            [$start, $end] = explode('-', $filters['between']['date']);

            $start = Carbon::createFromFormat('Ymd', $start)->format('Y-m-d');
            $end   = Carbon::createFromFormat('Ymd', $end)->format('Y-m-d');

            $query->whereBetween('intrastat_export_time_series_records.from', [$start, $end]);
        }

        if (!empty($filters['elements']['vat_status'])) {
            $vatStatuses = is_array($filters['elements']['vat_status'])
                ? $filters['elements']['vat_status']
                : explode(',', $filters['elements']['vat_status']);

            if (count($vatStatuses) === 1) {
                if (in_array('with_vat', $vatStatuses)) {
                    $query->whereHas('intrastatExportTimeSeries.taxCategory', function ($q) {
                        $q->where('rate', '>', 0.0);
                    });
                } elseif (in_array('without_vat', $vatStatuses)) {
                    $query->where(function ($q) {
                        $q->whereHas('intrastatExportTimeSeries.taxCategory', function ($subQuery) {
                            $subQuery->where('rate', '=', 0.0);
                        })->orWhereNull('intrastat_export_time_series.tax_category_id');
                    });
                }
            }
        }

        return $query->select('intrastat_export_time_series_records.*')
            ->orderBy('intrastat_export_time_series_records.from')
            ->get();
    }

    protected function decimal(float $value, int $decimals): string
    {
        $formatted = number_format($value, $decimals, ',', '');

        if (str_contains($formatted, ',')) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted;
    }

    protected function amount(float $value): string
    {
        return number_format($value, 2, ',', '');
    }

    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        $filters = [
            'between'  => $request->input('between', []),
            'elements' => $request->input('elements', []),
        ];

        $result = $this->handle($organisation, $filters);

        if ($result['errors'] !== []) {
            return response(
                "Export stopped, fix these records first:\n\n".implode("\n", $result['errors']),
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $stamp    = Carbon::now()->format('Y-m-d_His');
        $baseName = 'intrastat_aeat_'.$organisation->slug.'_'.$stamp;
        $zipPath  = tempnam(sys_get_temp_dir(), 'aeat');
        $zip      = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);

        foreach ($this->splitFiles($result['lines']) as $index => $content) {
            $zip->addFromString(sprintf('%s_%02d.csv', $baseName, $index + 1), $content);
        }

        $zip->addFromString($baseName.'_log.tsv', implode("\n", $result['log']));
        $zip->close();

        $content = file_get_contents($zipPath);
        unlink($zipPath);

        return response($content, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$baseName.'.zip"',
        ]);
    }
}
