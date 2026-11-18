<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\Api\Group\Inventory\WithMobileOrgStocks;
use App\Actions\Api\Group\WithMobileModuleAccess;
use App\Actions\GoodsIn\StockDelivery\ReceiveStockDelivery;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

trait WithMobileGoodsIn
{
    use WithMobileModuleAccess;
    use WithMobileOrgStocks;

    public static function canBookInStockDeliveries(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id && ReceiveStockDelivery::userCanBookIn($user, $warehouse->organisation, 'incoming.%d.edit');
    }

    public static function canSuperviseStockDeliveries(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id && ReceiveStockDelivery::userCanBookIn($user, $warehouse->organisation, 'supervisor-incoming.%d');
    }

    public static function canHandleReturns(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id && $user->authTo(["incoming.$warehouse->id.edit", "returns.$warehouse->id"]);
    }

    protected function ensureWarehouseAccess(User $user, Warehouse $warehouse, bool $allowed, ?string $refusal = null): void
    {
        abort_unless($allowed, 403, $refusal ?? __('You do not have access to goods in on this warehouse.'));
        abort_unless($user->authorisedWarehouses()->whereKey($warehouse->id)->exists(), 404);
    }

    protected function refuseState(string $message): never
    {
        throw ValidationException::withMessages(['state' => $message]);
    }

    /**
     * @throws ValidationException
     */
    protected function ensureLocationTakesStock(?Location $location, Warehouse $warehouse, string $field): Location
    {
        $refusal = match (true) {
            !$location || $location->warehouse_id !== $warehouse->id || $location->trashed() => __('That location is not in this warehouse.'),
            $location->status !== LocationStatusEnum::OPERATIONAL                         => __('This location is not operational.'),
            !$location->allow_stocks                                                      => __('This location does not take SKOs.'),
            default                                                                       => null,
        };

        if ($refusal) {
            throw ValidationException::withMessages([$field => $refusal]);
        }

        return $location;
    }

    /**
     * @throws ValidationException
     */
    protected function locationLinkOfSko(int $locationOrgStockId, ?int $orgStockId, Warehouse $warehouse): LocationOrgStock
    {
        /** @var LocationOrgStock|null $locationOrgStock */
        $locationOrgStock = LocationOrgStock::with('location')->find($locationOrgStockId);

        if (!$locationOrgStock || $locationOrgStock->warehouse_id !== $warehouse->id) {
            throw ValidationException::withMessages(['location_org_stock_id' => __('That location is not in this warehouse.')]);
        }

        if ($locationOrgStock->org_stock_id !== $orgStockId) {
            throw ValidationException::withMessages(['location_org_stock_id' => __('That location does not hold this SKO.')]);
        }

        $this->ensureLocationTakesStock($locationOrgStock->location, $warehouse, 'location_org_stock_id');

        return $locationOrgStock;
    }

    /**
     * @return array<string, float|string>
     */
    protected function skoFields(string $key, int|float|string|null $quantity, int|float|string|null $packedIn): array
    {
        $skos = round((float) $quantity, 4);

        return [
            $key          => $skos,
            $key.'_label' => $this->skoLabel($skos, (int) $packedIn),
        ];
    }

    protected function skoLabel(float $skos, int $packedIn): string
    {
        $units = $skos * $packedIn;

        if ($packedIn <= 1 || $skos < 0 || abs($units - round($units)) > 0.001) {
            return trimDecimalZeros($skos);
        }

        $units     = (int) round($units);
        $wholePack = intdiv($units, $packedIn);
        $loose     = $units % $packedIn;

        return match (true) {
            $loose === 0     => (string) $wholePack,
            $wholePack === 0 => $loose.'/'.$packedIn,
            default          => $wholePack.' '.$loose.'/'.$packedIn,
        };
    }

    protected function isoDateTime(DateTimeInterface|string|null $value): ?string
    {
        return $value ? Carbon::parse($value)->toIso8601String() : null;
    }

    /**
     * @return array{key: string, label: string}
     */
    protected function actionOption(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label];
    }
}
