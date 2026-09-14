<?php

namespace App\Actions\Chat\MetaChatSession;

use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncMetaChatSessionByPhone
{
    use AsAction;

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:50'],
        ];
    }

    public function asController(MetaChatSession $metaChatSession, ActionRequest $request): array
    {
        return $this->handle($metaChatSession, $request->validated('phone'));
    }

    public function handle(MetaChatSession $metaChatSession, string $phone): array
    {
        $shopId = $metaChatSession->shop_id;
        $normalised = $this->normalise($phone);

        $customer = $shopId
            ? Customer::where('shop_id', $shopId)
                ->where(function ($query) use ($normalised, $phone) {
                    $query->whereRaw("REGEXP_REPLACE(phone, '[^0-9+]', '', 'g') = ?", [$normalised])
                        ->orWhere('phone', $phone);
                })
                ->first()
            : null;

        if (!$customer) {
            return [
                'success' => false,
                'message' => __('No customer registered with this phone number in this shop'),
                'data'    => [
                    'phone'              => $phone,
                    'meta_session_ulid'  => $metaChatSession->ulid,
                ],
                'status' => 404,
            ];
        }

        $metaChatSession->update([
            'customer_id' => $customer->id,
        ]);

        return [
            'success' => true,
            'message' => __('WhatsApp session linked to customer by phone'),
            'data'    => [
                'meta_session_ulid' => $metaChatSession->ulid,
                'customer'          => [
                    'id'    => $customer->id,
                    'name'  => $customer->contact_name ?? $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'slug'  => $customer->slug,
                ],
            ],
            'status' => 200,
        ];
    }

    protected function normalise(?string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', (string) $phone);
    }

    public function jsonResponse(array $result): JsonResponse
    {
        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data'    => $result['data'] ?? [],
        ], $result['status'] ?? ($result['success'] ? 200 : 400));
    }
}
