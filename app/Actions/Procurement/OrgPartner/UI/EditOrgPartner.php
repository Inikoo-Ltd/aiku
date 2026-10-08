<?php

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\OrgAction;
use App\Models\Inventory\Location;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOrgPartner extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("org-supervisor.{$this->organisation->id}.procurement");
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): OrgPartner
    {
        $this->initialisation($organisation, $request);
        abort_unless($organisation->is_manufacturing_hub && $orgPartner->organisation_id === $organisation->id, 404);

        return $orgPartner;
    }

    public function htmlResponse(OrgPartner $orgPartner, ActionRequest $request): Response
    {
        $otherBayIds = OrgPartner::where('organisation_id', $orgPartner->organisation_id)
            ->where('id', '!=', $orgPartner->id)
            ->get()
            ->flatMap(fn (OrgPartner $other) => $other->bayIds())
            ->all();

        $bayOptions = Location::where('organisation_id', $orgPartner->organisation_id)
            ->where('is_goods_out', true)
            ->when($orgPartner->goods_out_location_id, fn ($query, $id) => $query->where('id', '!=', $id))
            ->whereNotIn('id', $otherBayIds)
            ->orderBy('code')
            ->get(['id', 'code'])
            ->mapWithKeys(fn (Location $location) => [$location->id => ['id' => $location->id, 'label' => $location->code]]);

        return Inertia::render('EditModel', [
            'title'       => __('partner'),
            'breadcrumbs' => ShowOrgPartner::make()->getBreadcrumbs(
                $orgPartner,
                $request->route()->originalParameters(),
                '('.__('Editing').')'
            ),
            'pageHead'    => [
                'title'   => $orgPartner->partner->name,
                'icon'    => ['title' => __('Partner'), 'icon' => 'fal fa-users-class'],
                'actions' => [
                    [
                        'type'  => 'button',
                        'style' => 'exitEdit',
                        'route' => [
                            'name'       => 'grp.org.procurement.org_partners.show',
                            'parameters' => $request->route()->originalParameters(),
                        ],
                    ],
                ],
            ],
            'formData'    => [
                'blueprint' => [
                    [
                        'label'  => __('Cosmetics'),
                        'icon'   => 'fal fa-sparkles',
                        'fields' => [
                            'split_cosmetics' => [
                                'type'        => 'toggle',
                                'label'       => __('Split cosmetics into their own orders'),
                                'information' => __('Cosmetic and non-cosmetic SKOs for this partner get separate orders, delivery notes and invoices.'),
                                'value'       => $orgPartner->split_cosmetics,
                            ],
                            'cosmetic_goods_out_location_id' => [
                                'type'        => 'select',
                                'label'       => __('Cosmetic goods-out bay'),
                                'information' => __('Where this partner\'s cosmetic SKOs are gathered. Leave empty to use the partner\'s normal goods-out bay.'),
                                'placeholder' => __('Select a bay'),
                                'options'     => $bayOptions,
                                'value'       => $orgPartner->cosmetic_goods_out_location_id,
                            ],
                        ],
                    ],
                    [
                        'label'  => __('Shipments'),
                        'icon'   => 'fal fa-truck',
                        'fields' => [
                            'next_shipment_on' => [
                                'type'        => 'date',
                                'label'       => __('Next shipment'),
                                'information' => __('The morning before this day, whatever sits in the partner\'s bay becomes an order and goes to the warehouse to be packed. Leave empty to raise orders only by hand.'),
                                'value'       => $orgPartner->next_shipment_on?->toDateString(),
                            ],
                            'shipment_every_days' => [
                                'type'        => 'input_number',
                                'label'       => __('Ship every (days)'),
                                'information' => __('After each shipment the next one moves this many days later. Leave empty for a one-off date.'),
                                'value'       => $orgPartner->shipment_every_days,
                            ],
                        ],
                    ],
                ],
                'args'      => [
                    'updateRoute' => [
                        'name'       => 'grp.org.procurement.org_partners.show.cosmetic_settings.update',
                        'parameters' => $request->route()->originalParameters(),
                    ],
                ],
            ],
        ]);
    }
}
