<?php

/*
 * Author: andiferdiawan (https://github.com/andiferdiawan)
 * Created: Thursday, 22 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\Comms\Wati;

use App\Actions\OrgAction;
use App\Jobs\Wati\AddCustomersToWatiJob;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class BulkAddAllCustomersToWati extends OrgAction
{
    public function handle(Shop $shop): int
    {
        $customerIds = Customer::where('shop_id', $shop->id)
            ->whereDoesntHave('watiContact')
            ->pluck('id')
            ->all();

        foreach (array_chunk($customerIds, 50) as $chunk) {
            AddCustomersToWatiJob::dispatch($shop, $chunk);
        }

        return count($customerIds);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): int
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function jsonResponse(int $count): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => "Queued {$count} customer(s) to be added to Wati.",
            'count'   => $count,
        ]);
    }
}
