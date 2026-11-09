<?php

namespace App\Enums\Catalogue\Product;

use App\Enums\EnumHelperTrait;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

enum ProductBadgeEnum: string
{
    use EnumHelperTrait;

    case NEW_IN     = 'new_in';
    case BESTSELLER = 'bestseller';

    public const int NEW_IN_DAYS = 30;

    /**
     * A product first available after this moment is NEW IN, the same rule resolve() applies to the badge.
     */
    public static function newInSince(?CarbonInterface $now = null): CarbonInterface
    {
        return ($now ?? now())->copy()->subDays(self::NEW_IN_DAYS);
    }

    /**
     * NEW IN beats BESTSELLER and ignores stock. BACK IN STOCK ranks between the two and is worked out
     * by the storefront from the stock dates sent with the product.
     */
    public static function resolve(
        CarbonInterface|string|null $firstAvailableAt,
        int|string|null $topSeller,
        ?CarbonInterface $now = null
    ): ?self {
        if ($firstAvailableAt && Carbon::parse($firstAvailableAt)->addDays(self::NEW_IN_DAYS)->gt($now ?? now())) {
            return self::NEW_IN;
        }

        if ($topSeller) {
            return self::BESTSELLER;
        }

        return null;
    }
}
