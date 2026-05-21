<?php

/*
 * Author: andiferdiawan (https://github.com/andiferdiawan)
 * Created: Wednesday, 21 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\Comms\Wati;

use App\Actions\OrgAction;
use App\Jobs\Wati\AddCustomersToWatiJob;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class BulkAddCustomersToWati extends OrgAction
{
    public function handle(Shop $shop, array $customerIds): int
    {
        $chunks = array_chunk($customerIds, 50);

        foreach ($chunks as $chunk) {
            AddCustomersToWatiJob::dispatch($shop, $chunk);
        }

        return count($customerIds);
    }

    public function rules(): array
    {
        return [
            'customer_ids'   => ['required', 'array', 'min:1', 'max:500'],
            'customer_ids.*' => ['required', 'integer', 'exists:customers,id'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): int
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request->array('customer_ids'));
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
