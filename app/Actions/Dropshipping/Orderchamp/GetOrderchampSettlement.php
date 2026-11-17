<?php

namespace App\Actions\Dropshipping\Orderchamp;

use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Orderchamp pays each of its companies' weekly settlements in one transfer referenced with the settlement number
 * ("Orderchamp NL payment: STL233240-1"). The API finds a settlement only by id, so the newest ones are read until
 * the number turns up. Each of its invoices is what Orderchamp pays for one order: the order total less commission
 * plus Orderchamp's share of the shipping.
 */
class GetOrderchampSettlement
{
    use AsAction;

    private const int PAGES = 15;

    /**
     * @return array{number: string, amount: float, currency: string|null, orders: array<string, float>}|null
     */
    public function handle(Shop $shop, string $settlementNumber): ?array
    {
        $after = null;
        for ($page = 0; $page < self::PAGES; $page++) {
            $data = $shop->orderchampQuery('{ settlements(first: 15, sort: ID_DESC'.($after ? ', after: "'.$after.'"' : '').') { pageInfo { hasNextPage endCursor } edges { node { number amount currency invoicesCount invoices(first: 100) { edges { node { totalPrice order { number } } } } } } } }');

            foreach (Arr::get($data, 'settlements.edges', []) as $edge) {
                $settlement = $edge['node'];
                if (strcasecmp($settlement['number'], $settlementNumber) !== 0) {
                    continue;
                }

                $orders = [];
                foreach (Arr::get($settlement, 'invoices.edges', []) as $invoiceEdge) {
                    $orderNumber = Arr::get($invoiceEdge, 'node.order.number');
                    if ($orderNumber) {
                        $orders[$orderNumber] = round(($orders[$orderNumber] ?? 0) + (float)Arr::get($invoiceEdge, 'node.totalPrice'), 2);
                    }
                }

                if (count(Arr::get($settlement, 'invoices.edges', [])) !== (int)$settlement['invoicesCount']) {
                    throw new RuntimeException('Orderchamp settlement '.$settlement['number'].' has more invoices than one page reads');
                }

                return [
                    'number'   => $settlement['number'],
                    'amount'   => round((float)$settlement['amount'], 2),
                    'currency' => $settlement['currency'],
                    'orders'   => $orders,
                ];
            }

            if (!Arr::get($data, 'settlements.pageInfo.hasNextPage')) {
                return null;
            }
            $after = Arr::get($data, 'settlements.pageInfo.endCursor');
        }

        return null;
    }
}
