<?php

namespace App\Actions\Accounting\Intrastat;

use App\Actions\OrgAction;
use App\Models\Accounting\IntrastatExportMetrics;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;
use SimpleXMLElement;

class ExportIntrastatXmlSlovakia extends OrgAction
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
        $query = IntrastatExportMetrics::where('organisation_id', $organisation->id)
            ->with(['country', 'taxCategory']);

        if (!empty($filters['between']['date'])) {
            $raw = $filters['between']['date'];
            [$start, $end] = explode('-', $raw);

            $start = Carbon::createFromFormat('Ymd', $start)->format('Y-m-d');
            $end   = Carbon::createFromFormat('Ymd', $end)->format('Y-m-d');

            $query->whereBetween('date', [$start, $end]);
        }

        if (!empty($filters['elements']['delivery_type'])) {
            $deliveryTypes = is_array($filters['elements']['delivery_type'])
                ? $filters['elements']['delivery_type']
                : explode(',', $filters['elements']['delivery_type']);

            if (count($deliveryTypes) === 1) {
                if (in_array('orders', $deliveryTypes)) {
                    $query->where('delivery_note_type', 'order')->where('invoices_count', '>', 0);
                } elseif (in_array('replacements', $deliveryTypes)) {
                    $query->where('delivery_note_type', 'replacement');
                }
            }
        }

        $metrics = $query->orderBy('date')->get();

        $startDate = !empty($filters['between']['date'])
            ? Carbon::createFromFormat('Ymd', explode('-', $filters['between']['date'])[0])
            : Carbon::now()->startOfMonth();

        $period = $startDate->format('Ym');
        $envelopeId = 'AA' . $startDate->format('ym');

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><INSTAT xmlns="http://www.financnasprava.sk/_arkusy/intrastat"></INSTAT>');

        $envelope = $xml->addChild('Envelope');
        $envelope->addChild('envelopeId', $envelopeId);
        $envelope->addChild('DateTime', Carbon::now()->toIso8601String());

        $declaration = $xml->addChild('Declaration');
        $declaration->addChild('declarationId', $organisation->slug . '_' . $period);
        $declaration->addChild('referencePeriod', $period);
        $declaration->addChild('PSIId', $organisation->data['intrastat_psi_id'] ?? $organisation->slug);
        $declaration->addChild('Function', 'O');
        $declaration->addChild('declarationTypeCode', 'D');
        $declaration->addChild('flowCode', 'D');
        $declaration->addChild('currencyCode', $organisation->currency->code);

        $partyType = $organisation->data['intrastat_party_type'] ?? 'PSI';
        $party = $declaration->addChild('Party');
        $party->addChild('partyType', $partyType);
        $party->addChild('partyId', $organisation->data['intrastat_party_id'] ?? $organisation->slug);
        $party->addChild('partyName', $organisation->data['intrastat_party_name'] ?? $organisation->name);

        $address = $party->addChild('Address');
        $address->addChild('streetName', $organisation->data['intrastat_street_name'] ?? $organisation->address->address_line_1 ?? '');
        $address->addChild('cityName', $organisation->data['intrastat_city_name'] ?? $organisation->address->locality ?? '');
        $address->addChild('postalCode', $organisation->data['intrastat_postal_code'] ?? $organisation->address->postal_code ?? '');
        $address->addChild('countryCode', $organisation->country->code);

        $contactPerson = $party->addChild('ContactPerson');
        $contactPerson->addChild('contactPersonName', $organisation->data['intrastat_contact_person_name'] ?? '');
        $contactPerson->addChild('phoneNumber', $organisation->data['intrastat_phone_number'] ?? '');
        $contactPerson->addChild('faxNumber', $organisation->data['intrastat_fax_number'] ?? '');
        $contactPerson->addChild('e-mail', $organisation->data['intrastat_email'] ?? '');

        $itemNumber = 1;
        foreach ($metrics as $metric) {
            $tariffCode = preg_replace('/[^0-9]/', '', $metric->tariff_code);
            if (strlen($tariffCode) < 8) {
                continue;
            }

            $tariffCode = substr($tariffCode, 0, 8);

            $weight = ceil($metric->weight / 1000);
            if ($weight <= 0) {
                $weight = 1;
            }

            $invoicedAmount = floor($metric->value_org_currency);
            if ($invoicedAmount == 0) {
                $invoicedAmount = 1;
            }

            $item = $declaration->addChild('Item');
            $item->addChild('itemNumber', $itemNumber++);

            $cn8 = $item->addChild('CN8');
            $cn8->addChild('CN8Code', $tariffCode);

            $item->addChild('MSConsDestCode', $metric->country->code);
            $item->addChild('countryOfOriginCode', $organisation->country->code);
            $item->addChild('netMass', $weight);
            $item->addChild('quantityInSU', (int) $metric->quantity);
            $item->addChild('invoicedAmount', $invoicedAmount);

            if ($metric->partner_tax_numbers && !empty($metric->partner_tax_numbers)) {
                foreach ($metric->partner_tax_numbers as $taxNumberData) {
                    if (isset($taxNumberData['valid']) && $taxNumberData['valid']) {
                        $taxNumber = preg_replace('/[^a-zA-Z0-9]/', '', $taxNumberData['number']);

                        if ($metric->country->code == 'GR' && str_starts_with($taxNumber, 'GR')) {
                            $taxNumber = 'EL' . substr($taxNumber, 2);
                        }

                        $item->addChild('partnerId', $taxNumber);
                        break;
                    }
                }
            }

            if (!isset($item->partnerId)) {
                $item->addChild('partnerId', 'QT999999999999');
            }

            $natureCode = $metric->nature_of_transaction?->value ?? '11';
            $item->addChild('NatureOfTransaction', str_replace('_', ',', $natureCode));

            $modeOfTransport = $metric->mode_of_transport?->value ?? '3';
            $item->addChild('modeOfTransportCode', $modeOfTransport);

            $deliveryTerms = $metric->delivery_terms?->value ?? 'EXW';
            $item->addChild('deliveryTermsCode', $deliveryTerms);
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

        $filename = 'intrastat_slovakia_' . $organisation->slug . '_' . Carbon::now()->format('Y-m-d_His') . '.xml';

        return response($xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
