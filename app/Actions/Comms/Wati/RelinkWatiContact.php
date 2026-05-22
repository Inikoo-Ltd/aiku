<?php

/*
 * Author: andiferdiawan (https://github.com/andiferdiawan)
 * Created: Thursday, 22 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\Comms\Wati;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WatiContact;
use App\Models\CRM\Customer;
use App\Services\Wati\WatiClient;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class RelinkWatiContact extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(WatiContact $watiContact): WatiContact
    {
        $shop       = $watiContact->shop;
        $customerId = $this->findCustomerId($shop, $watiContact->wa_id ?? '');

        $watiContact->update(['customer_id' => $customerId]);

        if ($watiContact->wati_id) {
            $this->updateWatiCustomParams($watiContact, $shop);
        }

        return $watiContact->fresh();
    }

    private function findCustomerId(Shop $shop, string $waId): ?int
    {
        if (blank($waId)) {
            return null;
        }

        $normalizedWaId = preg_replace('/\D/', '', $waId);

        $customer = Customer::where('shop_id', $shop->id)
            ->whereRaw("REGEXP_REPLACE(phone, '[^0-9]', '', 'g') LIKE ?", ["%{$normalizedWaId}%"])
            ->first();

        return $customer?->id;
    }

    private function updateWatiCustomParams(WatiContact $watiContact, Shop $shop): void
    {
        $existingParams = collect($watiContact->custom_params ?? []);
        $shopIdExists   = $existingParams->contains('name', 'shop_id');

        if (!$shopIdExists) {
            $updatedParams = $existingParams->push(['name' => 'shop_id', 'value' => (string) $shop->id])->values()->all();

            WatiClient::make()->updateContacts([
                [
                    'target'       => $watiContact->wati_id,
                    'customParams' => $updatedParams,
                ],
            ]);
        }
    }

    public function asController(WatiContact $watiContact, ActionRequest $request): WatiContact
    {
        $this->initialisation($watiContact->shop->organisation, $request);

        return $this->handle($watiContact);
    }

    public function jsonResponse(WatiContact $watiContact): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'message'     => 'Contact relinked successfully.',
            'customer_id' => $watiContact->customer_id,
        ]);
    }
}
