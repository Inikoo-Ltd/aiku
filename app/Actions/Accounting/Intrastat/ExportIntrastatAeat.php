<?php

namespace App\Actions\Accounting\Intrastat;

use App\Actions\OrgAction;
use App\Models\Accounting\IntrastatExportTimeSeriesRecord;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;

class ExportIntrastatAeat extends OrgAction
{
    private const string PROVINCE_OF_ORIGIN = '29';
    private const string TERMS_OF_DELIVERY = 'DAP';
    private const string NATURE_OF_TRANSACTION = '11';
    private const string MODE_OF_TRANSPORT = '3';
    private const string COUNTRY_OF_ORIGIN = 'ES';
    private const string STATISTICAL_PROCEDURE = '1';
    private const string SEPARATOR = ';';
    private const string LINE_ENDING = "\r\n";

    public function authorize(ActionRequest $request): bool
    {
        return in_array(
            $this->organisation->id,
            $request->user()->authorisedOrganisations()->pluck('id')->toArray()
        );
    }

    public function handle(Organisation $organisation, array $filters): string
    {
        $records = $this->getRecords($organisation, $filters);

        $lines = $records->map(function (IntrastatExportTimeSeriesRecord $record): ?string {
            $commodityCode = $this->commodityCode($record->intrastatExportTimeSeries->tariff_code);

            if ($commodityCode === '') {
                return null;
            }

            $fields = [
                $record->intrastatExportTimeSeries->country?->code ?? '',
                self::PROVINCE_OF_ORIGIN,
                self::TERMS_OF_DELIVERY,
                self::NATURE_OF_TRANSACTION,
                self::MODE_OF_TRANSPORT,
                '',
                $commodityCode,
                self::COUNTRY_OF_ORIGIN,
                self::STATISTICAL_PROCEDURE,
                $this->decimal((float) ($record->weight ?? 0) / 1000, 3),
                $this->decimal((float) ($record->quantity ?? 0), 3),
                $this->amount((float) ($record->value_org_currency ?? 0)),
                $this->amount((float) ($record->value_org_currency ?? 0)),
                $this->counterpartyVatNumber($record->partner_tax_numbers),
            ];

            return implode(self::SEPARATOR, $fields);
        })->filter()->values();

        return $lines->implode(self::LINE_ENDING);
    }

    protected function getRecords(Organisation $organisation, array $filters): Collection
    {
        $query = IntrastatExportTimeSeriesRecord::where('intrastat_export_time_series_records.organisation_id', $organisation->id)
            ->where('intrastat_export_time_series_records.frequency', 'D')
            ->join('intrastat_export_time_series', 'intrastat_export_time_series_records.intrastat_export_time_series_id', '=', 'intrastat_export_time_series.id')
            ->with(['intrastatExportTimeSeries.country', 'intrastatExportTimeSeries.taxCategory']);

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

    protected function commodityCode(?string $tariffCode): string
    {
        return substr(preg_replace('/[^0-9]/', '', (string) $tariffCode), 0, 8);
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

    protected function counterpartyVatNumber(?array $partnerTaxNumbers): string
    {
        if (empty($partnerTaxNumbers)) {
            return '';
        }

        $valid = array_filter($partnerTaxNumbers, fn ($taxNumber) => !empty($taxNumber['valid']));
        $selected = $valid !== [] ? reset($valid) : reset($partnerTaxNumbers);

        return str_replace(' ', '', (string) ($selected['number'] ?? ''));
    }

    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        $filters = [
            'between'  => $request->input('between', []),
            'elements' => $request->input('elements', []),
        ];

        $content = $this->handle($organisation, $filters);

        $filename = 'intrastat_aeat_' . $organisation->slug . '_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        return response($content, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
