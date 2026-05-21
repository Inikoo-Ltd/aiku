<?php

/*
 * Author: andiferdiawan (https://github.com/andiferdiawan)
 * Created: Wednesday, 21 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\Comms\Wati;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WatiContact;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use App\Services\Wati\WatiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;

class SyncWatiContacts extends OrgAction
{
    public function handle(Shop $shop): void
    {
        $client     = WatiClient::make();
        $pageNumber = 1;
        $pageSize   = 100;

        do {
            $response     = $client->getContacts(pageSize: $pageSize, pageNumber: $pageNumber);
            $contactList  = $response['contact_list'] ?? [];

            foreach ($contactList as $contact) {
                $customerId = $this->findCustomerId($shop, $contact['wa_id'] ?? '');
                $waId       = $contact['wa_id'] ?? null;
                $watiId     = $contact['id'];

                $attributes = [
                    'wati_id'         => $watiId,
                    'customer_id'     => $customerId,
                    'wa_id'           => $waId,
                    'phone'           => $contact['phone'] ?? null,
                    'name'            => $contact['name'] ?? null,
                    'contact_status'  => $contact['contact_status'] ?? 'valid',
                    'source'          => $contact['source'] ?? null,
                    'opted_in'        => $contact['opted_in'] ?? false,
                    'allow_broadcast' => $contact['allow_broadcast'] ?? true,
                    'allow_sms'       => $contact['allow_sms'] ?? true,
                    'teams'           => $contact['teams'] ?? [],
                    'segments'        => $contact['segments'] ?? [],
                    'custom_params'   => $contact['custom_params'] ?? [],
                    'wati_created_at' => isset($contact['created']) ? Carbon::parse($contact['created']) : null,
                    'wati_updated_at' => isset($contact['last_updated']) ? Carbon::parse($contact['last_updated']) : null,
                    'synced_at'       => now(),
                ];

                $existing = WatiContact::where('shop_id', $shop->id)
                    ->where(function ($q) use ($watiId, $waId): void {
                        $q->where('wati_id', $watiId);
                        if ($waId) {
                            $q->orWhere(fn ($q2) => $q2->whereNull('wati_id')->where('wa_id', $waId));
                        }
                    })
                    ->orderByRaw('wati_id IS NULL ASC')
                    ->first();

                if ($existing) {
                    $existing->update($attributes);
                } else {
                    WatiContact::create(array_merge(['shop_id' => $shop->id], $attributes));
                }
            }

            $pageNumber++;
        } while (count($contactList) === $pageSize);
    }

    private function findCustomerId(Shop $shop, string $waId): ?int
    {
        if (blank($waId)) {
            return null;
        }

        $normalizedWaId = preg_replace('/\D/', '', $waId);

        $customer = Customer::where('shop_id', $shop->id)
            ->whereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ?", ["%{$normalizedWaId}%"])
            ->first();

        return $customer?->id;
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): void
    {
        $this->initialisationFromShop($shop, $request);
        $this->handle($shop);
    }
}
