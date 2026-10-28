<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Enums\UI\SupplyChain\SupplierPortalAccessTabsEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierPortalContact;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * One email allowed to log in to the supplier portal by itself: who it is, its log ins and every visit.
 */
class ShowSupplierPortalContact extends OrgAction
{
    use WithSupplyChainAuthorisation;

    private Supplier $supplier;

    public function asController(Supplier $supplier, SupplierPortalContact $supplierPortalContact, ActionRequest $request): SupplierPortalContact
    {
        abort_if($supplierPortalContact->supplier_id !== $supplier->id, 404);
        $this->supplier = $supplier;
        $this->initialisationFromGroup($supplier->group, $request)->withTab(SupplierPortalAccessTabsEnum::values());

        return $supplierPortalContact;
    }

    public function htmlResponse(SupplierPortalContact $contact): Response
    {
        $name = $contact->profile?->name ?? $contact->name;

        return ShowSupplierDirectLink::make()->page($this->supplier, $contact, $this->tab, __('Self log-in'), 'fal fa-envelope', $contact->email, [
            ['label' => __('Status'), 'type' => 'state', 'value' => $contact->revoked_at ? 'stopped' : 'active'],
            ['label' => __('Name'), 'type' => 'text', 'value' => $name],
            ['label' => __('Allowed'), 'type' => 'date', 'value' => $contact->created_at, 'note' => $contact->authorisedBy?->contact_name],
            ['label' => __('Stopped'), 'type' => 'date', 'value' => $contact->revoked_at, 'hidden' => !$contact->revoked_at],
            ['label' => __('Log ins'), 'type' => 'number', 'value' => $contact->number_links_sent],
            ['label' => __('Last log in'), 'type' => 'date', 'value' => $contact->last_link_sent_at],
            ...ShowSupplierDirectLink::make()->visitFacts($contact),
        ]);
    }
}
