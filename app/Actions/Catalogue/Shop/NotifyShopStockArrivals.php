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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Tells a shop's customer service what has just become sellable, so nobody has to email a
 * "new / back in stock" list round the shops. A booked in delivery brings products back one by
 * one, so the first one opens a short wait and everything that arrives meanwhile goes out as one
 * notification per person, split into products never sold before and products back from out of
 * stock, the ones with customers waiting on a reminder first.
 */
class NotifyShopStockArrivals implements ShouldBeUnique
{
    use AsAction;

    public const int GATHER_MINUTES = 30;

    public string $jobQueue = 'default';

    public function getJobUniqueId(Shop $shop): string
    {
        return (string) $shop->id;
    }

    public function handle(Shop $shop): int
    {
        if (!$shop->is_aiku || $shop->state !== ShopStateEnum::OPEN) {
            return 0;
        }

        $until  = now();
        $marker = 'shop-stock-arrivals-sent:'.$shop->id;
        // ponytail: the marker lives in the cache; if it is lost the next notification repeats up to a day of arrivals.
        $since = Carbon::parse(Cache::get($marker, $until->copy()->subDay()));

        $arrivals = $this->arrivals($shop, $since, $until);
        Cache::forever($marker, $until->toIso8601String());

        if ($arrivals->isEmpty()) {
            return 0;
        }

        $recipients = $this->customerServiceUsers($shop);
        if ($recipients->isEmpty()) {
            return 0;
        }

        [$new, $back] = $arrivals->partition(fn (Product $product) => $product->first_in_stock_at && Carbon::parse($product->first_in_stock_at)->gte($since));

        Notification::send($recipients, new ShopStockArrivalsNotification($shop, $new->pluck('code')->all(), $back->pluck('code')->all(), (int) $back->sum('waiting_customers')));

        return $recipients->count();
    }

    /**
     * @return Collection<int, Product>
     */
    private function arrivals(Shop $shop, Carbon $since, Carbon $until): Collection
    {
        return Product::where('shop_id', $shop->id)
            ->where('is_for_sale', true)
            ->where('available_quantity', '>', 0)
            ->whereIn('state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING])
            ->where('back_in_stock_since', '>', $since)
            ->where('back_in_stock_since', '<=', $until)
            ->withCount(['backInStockReminders as waiting_customers'])
            ->orderByDesc('waiting_customers')
            ->orderBy('code')
            ->get(['id', 'code', 'first_in_stock_at']);
    }

    /**
     * Whoever works or supervises the shop's chat, which is exactly its customer service.
     *
     * @return Collection<int, User>
     */
    private function customerServiceUsers(Shop $shop): Collection
    {
        $userIds = DB::table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_type', 'User')
            ->whereIn('permissions.name', ['chat.'.$shop->id, 'chat-m.'.$shop->id])
            ->distinct()
            ->pluck('model_has_roles.model_id');

        return User::whereIn('id', $userIds)->where('status', true)->get();
    }
}
