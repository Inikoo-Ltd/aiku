<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use App\Notifications\ShopStockArrivalsNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Tells customer service what has just become sellable, so nobody has to email a "new / back in
 * stock" list round the shops. Runs every hour: each person gets one notification covering every
 * shop they look after, products never sold before apart from products back from out of stock,
 * the ones with customers waiting on a reminder first. One per shop buried the bell of anybody
 * looking after forty shops.
 */
class NotifyShopStockArrivals
{
    use AsAction;

    public const string SENT_MARKER = 'shop-stock-arrivals-sent';

    public string $jobQueue = 'default';

    public function handle(): int
    {
        $until = now();
        // ponytail: the marker lives in the cache; if it is lost the next run only looks back an hour.
        $since = Carbon::parse(Cache::get(self::SENT_MARKER, $until->copy()->subHour()));

        $arrivals = $this->arrivals($since, $until);
        Cache::forever(self::SENT_MARKER, $until->toIso8601String());

        if ($arrivals->isEmpty()) {
            return 0;
        }

        $shops    = Shop::whereIn('id', $arrivals->pluck('shop_id')->unique())->get()->keyBy('id');
        $notified = 0;

        foreach ($this->customerServiceShops($shops->keys()) as $userId => $shopIds) {
            $user = User::where('id', $userId)->where('status', true)->first();
            if (!$user) {
                continue;
            }

            $theirArrivals = $arrivals->whereIn('shop_id', $shopIds);

            [$new, $back] = $theirArrivals->partition(fn (Product $product) => $product->first_in_stock_at && Carbon::parse($product->first_in_stock_at)->gte($since));

            $newCodes  = $new->pluck('code')->unique()->values()->all();
            $backCodes = $back->pluck('code')->unique()->diff($newCodes)->values()->all();

            $theirShops = $theirArrivals->groupBy('shop_id')
                ->sortByDesc(fn (Collection $products) => [$products->sum('waiting_customers'), $products->count()])
                ->keys()
                ->map(fn (int $shopId) => $shops[$shopId]);

            $user->notify(new ShopStockArrivalsNotification($theirShops->first(), $theirShops->pluck('code')->all(), $newCodes, $backCodes, (int) $back->sum('waiting_customers')));
            $notified++;
        }

        return $notified;
    }

    /**
     * @return Collection<int, Product>
     */
    private function arrivals(Carbon $since, Carbon $until): Collection
    {
        $openShopIds = Shop::where('is_aiku', true)->where('state', ShopStateEnum::OPEN)->pluck('id');

        return Product::whereIn('shop_id', $openShopIds)
            ->where('is_for_sale', true)
            ->where('available_quantity', '>', 0)
            ->whereIn('state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING])
            ->where('back_in_stock_since', '>', $since)
            ->where('back_in_stock_since', '<=', $until)
            ->withCount(['backInStockReminders as waiting_customers'])
            ->orderByDesc('waiting_customers')
            ->orderBy('code')
            ->get(['id', 'shop_id', 'code', 'first_in_stock_at']);
    }

    /**
     * Whoever works or supervises a shop's chat, which is exactly its customer service.
     *
     * @param  Collection<int, int>  $shopIds
     *
     * @return Collection<int, array<int, int>> user id to the shops they look after
     */
    private function customerServiceShops(Collection $shopIds): Collection
    {
        $permissions = $shopIds->flatMap(fn (int $shopId) => ['chat.'.$shopId, 'chat-m.'.$shopId]);

        return DB::table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_type', 'User')
            ->whereIn('permissions.name', $permissions)
            ->distinct()
            ->get(['model_has_roles.model_id', 'permissions.name'])
            ->groupBy('model_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => (int) Str::afterLast($row->name, '.'))->unique()->values()->all());
    }
}
