<?php

/*
 * Author: Andi Ferdiawan
 * Created: Tue, 20 May 2026 Bali Time, Bali Indonesia
 */

namespace App\Actions\Billables\ShippingZone;

use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\ShippingZonesResource;
use App\Models\Billables\ShippingZone;
use App\Models\Billables\ShippingZoneSchema;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ReorderShippingZones extends OrgAction
{
    public function handle(ShippingZoneSchema $shippingZoneSchema, array $modelData): ShippingZoneSchema
    {
        $positions = Arr::get($modelData, 'positions', []);

        foreach ($positions as $item) {
            ShippingZone::where('id', $item['id'])
                ->where('shipping_zone_schema_id', $shippingZoneSchema->id)
                ->update(['position' => $item['position']]);
        }

        return $shippingZoneSchema->refresh();
    }

    public function rules(): array
    {
        return [
            'positions'            => ['required', 'array'],
            'positions.*.id'       => ['required', 'integer', 'exists:shipping_zones,id'],
            'positions.*.position' => ['required', 'integer', 'min:1'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ShippingZoneSchema $shippingZoneSchema, ActionRequest $request): ShippingZoneSchema
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shippingZoneSchema, $this->validatedData);
    }

    public function jsonResponse(ShippingZoneSchema $shippingZoneSchema): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('Shipping zones reordered successfully'),
            'data'    => ShippingZonesResource::collection(
                $shippingZoneSchema->shippingZones()->orderBy('position')->get()
            ),
        ]);
    }

    public function htmlResponse(ShippingZoneSchema $shippingZoneSchema): RedirectResponse
    {

        return Redirect::route($shippingZoneSchema->is_current ? 'grp.org.shops.show.billables.shipping.current.show' : 'grp.org.shops.show.billables.shipping.discount.show', [
            'organisation'       => $shippingZoneSchema->organisation->slug,
            'shop'               => $shippingZoneSchema->shop->slug,
            'shippingZoneSchema' => $shippingZoneSchema->slug,
        ]);
    }

    public function action(ShippingZoneSchema $shippingZoneSchema, array $modelData): ShippingZoneSchema
    {
        $this->asAction = true;
        $this->initialisationFromShop($shippingZoneSchema->shop, $modelData);

        return $this->handle($shippingZoneSchema, $this->validatedData);
    }
}
