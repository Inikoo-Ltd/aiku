<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Dispatching\CarrierRateCard\GetCarrierQuotes;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Models\Dispatching\CarrierRateCard;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Carrier rates are confidential offers, so only staff who see procurement or accounting, or the organisation admins, get them (HELP-4585).
 */
#[Description('Compares what each carrier would charge us to send one parcel, from the carriers\' rate cards loaded in Aiku (the PDFs they send each year). Give the destination country (ISO code) and, for Spain and Portugal, the postcode (it decides provincial, regional, peninsular, islands, Ceuta, Melilla), the weight in kg and, if known, the box size in cm (it can make the parcel cost more by volumetric weight). It returns every carrier service that delivers there, cheapest first: zone, charged weight, base price, the percentage surcharge included (fuel, energy) and the total in euros without VAT; plus the services that do not deliver there or are over their weight limit. Surcharges a card does not list (remote areas, oversize, customs, cash on delivery) are not included; each card\'s notes say what is missing. Without a destination it lists the rate cards loaded and their dates.')]
class CarrierQuoteTool extends Tool
{
    use WithMcpPermissions;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation' => ['required', 'string'],
            'country'      => ['sometimes', 'string', 'size:2'],
            'postcode'     => ['required_if:country,ES,es,PT,pt', 'nullable', 'string', 'max:12'],
            'weight_kg'    => ['required_with:country', 'numeric', 'gt:0', 'max:1000'],
            'length_cm'    => ['sometimes', 'numeric', 'gt:0', 'required_with:width_cm,height_cm'],
            'width_cm'     => ['sometimes', 'numeric', 'gt:0', 'required_with:length_cm,height_cm'],
            'height_cm'    => ['sometimes', 'numeric', 'gt:0', 'required_with:length_cm,width_cm'],
            'date'         => ['sometimes', 'date'],
        ]);

        $identifier   = strtolower((string) $request->string('organisation'));
        $organisation = Organisation::where('group_id', $request->user()->group_id)
            ->where(fn ($query) => $query->whereRaw('lower(slug) = ?', [$identifier])->orWhereRaw('lower(code) = ?', [$identifier]))
            ->first();
        if (!$organisation || !$this->canSeeRates($request, $organisation)) {
            return $this->notFoundError('organisation', (string) $request->string('organisation'), $this->accessibleOrganisations($request), $request);
        }

        if (!$request->has('country')) {
            return Response::json([
                'rate_cards' => CarrierRateCard::where('organisation_id', $organisation->id)->orderBy('carrier')->orderBy('service')->orderBy('valid_from')->get()
                    ->map(fn (CarrierRateCard $card) => [
                        'carrier'    => $card->carrier,
                        'service'    => $card->service,
                        'valid_from' => $card->valid_from->toDateString(),
                        'valid_to'   => $card->valid_to?->toDateString(),
                        'zones'      => array_column($card->zones, 'name'),
                        'notes'      => $card->notes,
                    ])->all(),
            ]);
        }

        $dimensions = $request->has('length_cm') ? [(float) $request->get('length_cm'), (float) $request->get('width_cm'), (float) $request->get('height_cm')] : null;

        return Response::json(GetCarrierQuotes::run(
            $organisation,
            (string) $request->string('country'),
            $request->get('postcode'),
            (float) $request->get('weight_kg'),
            $dimensions,
            $request->get('date')
        ));
    }

    private function canSeeRates(Request $request, Organisation $organisation): bool
    {
        foreach ([OrganisationPermissionsEnum::ORG_ADMIN, OrganisationPermissionsEnum::PROCUREMENT_VIEW, OrganisationPermissionsEnum::ACCOUNTING_VIEW] as $permission) {
            if ($this->userCan($request, OrganisationPermissionsEnum::getPermissionName($permission->value, $organisation))) {
                return true;
            }
        }

        return false;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Organisation slug or code whose carrier rates to use')->required(),
            'country'      => $schema->string()->description('Destination country, ISO 3166 two letters (ES, PT, DE, FR, GB...). Omit to list the rate cards loaded'),
            'postcode'     => $schema->string()->description('Destination postcode; needed in Spain and Portugal to tell provincial, regional, peninsular, islands, Ceuta and Melilla apart'),
            'weight_kg'    => $schema->number()->description('Real weight of the parcel in kg'),
            'length_cm'    => $schema->number()->description('Box length in cm, with width and height, for volumetric weight'),
            'width_cm'     => $schema->number()->description('Box width in cm'),
            'height_cm'    => $schema->number()->description('Box height in cm'),
            'date'         => $schema->string()->description('Price on this date (YYYY-MM-DD), default today'),
        ];
    }
}
