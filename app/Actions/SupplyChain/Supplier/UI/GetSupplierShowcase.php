<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 02 May 2024 19:47:57 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\SupplyChain\Supplier\UpdateSupplier;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

class GetSupplierShowcase
{
    use AsObject;
    use WithSupplierInfo;

    /**
     * @param  array{name: string, parameters: array<string, mixed>}|null  $editRoute
     */
    public function handle(Supplier $supplier, ?array $editRoute = null): array
    {
        return [
            'leadTimes'           => $this->leadTimes($supplier, $editRoute),
            'relationshipContact' => $this->relationshipContact($supplier),
            'contactCard' => [
                'created_at' => $supplier->created_at,
                'is_archived' => !$supplier->status,
                'archived_at' => $supplier->status ? null : $supplier->archived_at,
                'company'    => $supplier->company_name,
                'nickname'   => $supplier->nickname(),
                'update_route' => $editRoute ? [
                    'name'       => 'grp.models.supplier.update',
                    'parameters' => ['supplier' => $supplier->id],
                ] : null,
                'contact'    => $supplier->contact_name,
                'website'    => $supplier->contact_website,
                'location'   => $supplier->location,
                'email'      => $supplier->email,
                'extra_emails' => $supplier->data['extra_emails'] ?? [],
                'phone'      => $supplier->phone,
                'currency'   => $supplier->currency,
                'address'    => AddressResource::make($supplier->address)->getArray(),
                'photo'      => $supplier->imageSources(320, 320),
                'photo_full' => $supplier->image?->fullResolutionUrls(),
                'supplierInfo' => $this->supplierInfo($supplier),
            ],
        ];
    }

    /**
     * @return array{supplier_id: int, user_id: int|null, name: string|null, can_edit: bool}
     */
    private function relationshipContact(Supplier $supplier): array
    {
        $user = $supplier->relationshipContact;

        return [
            'supplier_id' => $supplier->id,
            'user_id'     => $user?->id,
            'name'        => $user ? ($user->contact_name ?: $user->username) : null,
            'can_edit'    => request()->user() !== null && UpdateSupplier::canBeEditedBy($supplier, request()->user()),
        ];
    }

    /**
     * @param  array{name: string, parameters: array<string, mixed>}|null  $editRoute
     * @return array<string, mixed>
     */
    private function leadTimes(Supplier $supplier, ?array $editRoute): array
    {
        $today = Carbon::today()->toDateString();

        $upcomingHolidays = collect($supplier->factory_holidays ?? [])
            ->filter(fn (array $holiday) => Arr::get($holiday, 'to', Arr::get($holiday, 'from')) >= $today)
            ->sortBy('from')
            ->values()
            ->all();

        return [
            'delivery_time'            => Arr::get($supplier->data, 'delivery_time'),
            'production_waiting_time'  => Arr::get($supplier->data, 'production_waiting_time'),
            'pre_order_lead_time'      => Arr::get($supplier->settings, 'pre_order_lead_time'),
            'pre_order_lead_time_unit' => Arr::get($supplier->settings, 'pre_order_lead_time_unit', 'days'),
            'factory_holidays'         => $upcomingHolidays,
            'edit_routes'              => $editRoute ? [
                'settings'   => $this->editSectionRoute($editRoute, 'settings'),
                'factory'    => $this->editSectionRoute($editRoute, 'factory'),
                'pre_orders' => $this->editSectionRoute($editRoute, 'pre_orders'),
            ] : null,
        ];
    }

    /**
     * @param  array{name: string, parameters: array<string, mixed>}  $editRoute
     * @return array{name: string, parameters: array<string, mixed>}
     */
    private function editSectionRoute(array $editRoute, string $section): array
    {
        return [
            'name'       => $editRoute['name'],
            'parameters' => [...$editRoute['parameters'], 'section' => $section],
        ];
    }
}
