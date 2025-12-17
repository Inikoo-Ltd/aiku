<?php

namespace App\Actions\Accounting\Intrastat;

use App\Actions\OrgAction;
use App\Models\Accounting\IntrastatMetrics;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;
use SimpleXMLElement;

class ExportIntrastatXml extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return in_array(
            $this->organisation->id,
            $request->user()->authorisedOrganisations()->pluck('id')->toArray()
        );
    }

    public function handle(Organisation $organisation, array $filters): string
    {
        $query = IntrastatMetrics::where('organisation_id', $organisation->id)
            ->with(['country', 'taxCategory']);

        if (!empty($filters['between']['date'])) {
            $raw = $filters['between']['date'];
            [$start, $end] = explode('-', $raw);

            $start = Carbon::createFromFormat('Ymd', $start)->format('Y-m-d');
            $end   = Carbon::createFromFormat('Ymd', $end)->format('Y-m-d');

            $query->whereBetween('date', [$start, $end]);
        }

        if (!empty($filters['elements']['vat_status'])) {
            $vatStatuses = is_array($filters['elements']['vat_status'])
                ? $filters['elements']['vat_status']
                : explode(',', $filters['elements']['vat_status']);

            if (count($vatStatuses) === 1) {
                if (in_array('with_vat', $vatStatuses)) {
                    $query->whereHas('taxCategory', function ($q) {
                        $q->where('rate', '>', 0.0);
                    });
                } elseif (in_array('without_vat', $vatStatuses)) {
                    $query->where(function ($q) {
                        $q->whereHas('taxCategory', function ($subQuery) {
                            $subQuery->where('rate', '=', 0.0);
                        })
                        ->orWhereNull('tax_category_id');
                    });
                }
            }
        }

        $metrics = $query->orderBy('date')->get();

        if (!empty($filters['between']['date'])) {
            [$start, $end] = explode('-', $filters['between']['date']);
            $period = Carbon::createFromFormat('Ymd', $start)->format('Y-m')
                . '_' .
                Carbon::createFromFormat('Ymd', $end)->format('Y-m');
        } else {
            $period = Carbon::now()->format('Y-m');
        }

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><INTRASTAT></INTRASTAT>');
        $xml->addAttribute('organisation', $organisation->name);
        $xml->addAttribute('period', $period);
        $xml->addAttribute('generated_at', Carbon::now()->toIso8601String());

        foreach ($metrics as $metric) {
            $item = $xml->addChild('Item');
            $item->addChild('TariffCode', htmlspecialchars($metric->tariff_code));
            $item->addChild('DestinationCountry', $metric->country->code);

            $qty = $item->addChild('Quantity', number_format($metric->quantity, 2, '.', ''));
            $qty->addAttribute('unit', 'pieces');

            $value = $item->addChild('Value', number_format($metric->value_org_currency, 2, '.', ''));
            $value->addAttribute('currency', $organisation->currency->code);

            $weight = $item->addChild('Weight', number_format($metric->weight / 1000, 2, '.', ''));
            $weight->addAttribute('unit', 'kg');

            if ($metric->taxCategory) {
                $item->addChild('TaxCategory', htmlspecialchars($metric->taxCategory->name));
            }

            $item->addChild('Date', $metric->date->format('Y-m-d'));
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        $filters = [
            'between'  => $request->input('between', []),
            'elements' => $request->input('elements', [])
        ];

        $xml = $this->handle($organisation, $filters);

        $filename = 'intrastat_' . $organisation->slug . '_' . Carbon::now()->format('Y-m-d_His') . '.xml';

        return response($xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
