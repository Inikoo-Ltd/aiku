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
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class AddCustomerToWati extends OrgAction
{
    public function handle(Shop $shop, Customer $customer): WatiContact
    {
        $phone = preg_replace('/\D/', '', (string) $customer->phone);

        if (empty($phone)) {
            throw ValidationException::withMessages(['phone' => 'Customer has no valid phone number.']);
        }

        $client   = WatiClient::make();
        $response = $client->addContact($phone, $customer->name, [
            ['name' => 'shop_id', 'value' => (string) $shop->id],
        ]);

        if (($response['result'] ?? true) === false) {
            throw new \RuntimeException($response['message'] ?? 'Wati API failed to add contact.');
        }

        $waId = $response['contact']['whatsappNumber'] ?? $phone;

        return WatiContact::updateOrCreate(
            ['shop_id' => $shop->id, 'wa_id' => $waId],
            [
                'wati_id'         => $response['contact']['id'] ?? null,
                'phone'           => $phone,
                'name'            => $customer->name,
                'customer_id'     => $customer->id,
                'contact_status'  => $response['contact']['contactStatus'] ?? 'unconfirmed',
                'source'          => 'internal',
                'opted_in'        => $response['contact']['optedIn'] ?? false,
                'allow_broadcast' => $response['contact']['allowBroadcast'] ?? true,
                'synced_at'       => now(),
            ]
        );
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): WatiContact
    {
        $this->initialisationFromShop($shop, $request);

        $customer = Customer::where('shop_id', $shop->id)
            ->findOrFail($request->integer('customer_id'));

        return $this->handle($shop, $customer);
    }

    public function jsonResponse(WatiContact $watiContact): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contact added to Wati successfully.',
            'contact' => [
                'id'      => $watiContact->id,
                'wa_id'   => $watiContact->wa_id,
                'name'    => $watiContact->name,
                'phone'   => $watiContact->phone,
            ],
        ]);
    }
}
