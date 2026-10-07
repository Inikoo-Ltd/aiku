<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Dashboard;

use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * Logs what a customer put in their basket from a section of their dashboard, so each section's adds and
 * the orders that followed can be counted (ReportRetinaDashboardBasketAdds). A failed log never fails the
 * add itself, the basket is already changed by then.
 */
class StoreRetinaDashboardBasketAdd
{
    use AsObject;

    public const array SECTIONS = [
        'order_again',
        'favourites',
        'repeat_order',
        'suggestions_ai_suggestions',
        'suggestions_bought_together',
        'suggestions_shop_best_sellers',
    ];

    /**
     * @param array<int, float|int> $quantityByProductId
     */
    public function handle(Customer $customer, string $section, int $orderId, array $quantityByProductId): void
    {
        $quantityByProductId = array_filter($quantityByProductId, fn ($quantity) => (float) $quantity > 0);

        if (!$quantityByProductId) {
            return;
        }

        $now = now();

        try {
            DB::table('retina_dashboard_basket_adds')->insert(array_map(fn (int $productId) => [
                'shop_id'     => $customer->shop_id,
                'customer_id' => $customer->id,
                'section'     => $section,
                'product_id'  => $productId,
                'order_id'    => $orderId,
                'quantity'    => $quantityByProductId[$productId],
                'created_at'  => $now,
            ], array_keys($quantityByProductId)));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
