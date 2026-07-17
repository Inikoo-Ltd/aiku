<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchAccounting
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $organisationId = Arr::get($options, 'organisation_id');
        $shopId         = Arr::get($options, 'shop_id');

        $invoicesQuery = Invoice::search($query);
        $paymentsQuery = Payment::search($query);
        if ($shopId) {
            $invoicesQuery->where('shop_id', $shopId);
            $paymentsQuery->where('shop_id', $shopId);
        } elseif ($organisationId) {
            $invoicesQuery->where('organisation_id', $organisationId);
            $paymentsQuery->where('organisation_id', $organisationId);
        }

        return [
            'scope'   => 'accounting',
            'results' => [
                'invoices' => array_map(static fn (array $document) => [
                    'id'    => (int)$document['id'],
                    'code'  => $document['reference'] ?? null,
                    'name'  => $document['customer_name'] ?? null,
                    'state' => $document['type'] ?? null,
                ], $this->rawDocuments($invoicesQuery)),
                'payments' => array_map(static fn (array $document) => [
                    'id'    => (int)$document['id'],
                    'code'  => $document['reference'] ?? null,
                    'state' => $document['state'] ?? null,
                ], $this->rawDocuments($paymentsQuery)),
            ],
        ];
    }


}
