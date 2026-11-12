<?php

/*
 * Author Andi Ferdiawan
 * Created on 12-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Actions\Inventory\LocationOrgStock\AuditLocationOrgStock;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class AuditMobileLocation
{
    use AsController;
    use WithMobileLocationsAccess;

    /**
     * Counting a location is the web SKO audit (AuditLocationOrgStock) done for each SKO counted
     * in it, so every count leaves the same audit movement, reason, note and user as on the web.
     * Like the web audit it needs stock edit rights.
     *
     * @param array<int, array{location_org_stock_id: int, quantity: float|int, reason: string, note?: string|null}> $counts
     */
    public function handle(Location $location, array $counts, User $user): Location
    {
        $links = LocationOrgStock::where('location_id', $location->id)
            ->whereIn('id', Arr::pluck($counts, 'location_org_stock_id'))
            ->get()
            ->keyBy('id');

        foreach ($counts as $count) {
            AuditLocationOrgStock::make()->action(
                $links->get($count['location_org_stock_id']),
                Arr::only($count, ['quantity', 'reason', 'note']),
                $user
            );
        }

        return $location->refresh();
    }

    public function asController(Location $location, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewLocations($user, $location->warehouse);
        abort_unless(self::canEditStocks($user, $location->warehouse), 403);

        $data = Validator::make($request->all(), [
            'counts'                         => ['required', 'array', 'min:1'],
            'counts.*.location_org_stock_id' => ['required', 'integer', 'distinct', Rule::exists('location_org_stocks', 'id')->where('location_id', $location->id)],
            'counts.*.quantity'              => ['required', 'numeric', 'gte:0'],
            'counts.*.reason'                => ['required', Rule::enum(OrgStockMovementReasonEnum::class)],
            'counts.*.note'                  => ['sometimes', 'nullable', 'string'],
        ])->validate();

        $location = $this->handle($location, $data['counts'], $user);

        return response()->json(['data' => (new ShowMobileLocation())->forUser($location, $user)]);
    }
}
