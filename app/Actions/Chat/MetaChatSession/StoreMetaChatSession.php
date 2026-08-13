<?php

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\CRM\Customer;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreMetaChatSession
{
    use AsAction;

    public function rules(): array
    {
        return [
            'shop_id'     => ['required', 'exists:shops,id'],
            'customer_id' => ['required', 'exists:customers,id'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): MetaChatSession
    {
        return $this->handle($request->validated());
    }

    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): MetaChatSession
    {
        $metaChannel = MetaChannel::where('code', 'whatsapp')->first();

        if (!$metaChannel) {
            throw ValidationException::withMessages([
                'channel' => __('The WhatsApp channel is not configured. Run the MetaChannelSeeder.'),
            ]);
        }

        $customer = Customer::findOrFail($modelData['customer_id']);

        if (blank($customer->phone)) {
            throw ValidationException::withMessages([
                'customer_id' => __('This customer has no phone number.'),
            ]);
        }

        return DB::transaction(function () use ($metaChannel, $customer, $modelData) {
            $existing = MetaChatSession::where('meta_channel_id', $metaChannel->id)
                ->where('shop_id', $modelData['shop_id'])
                ->where('phone_number', $customer->phone)
                ->where('status', '!=', ChatSessionStatusEnum::CLOSED)
                ->first();

            if ($existing) {
                return $existing;
            }

            return MetaChatSession::create([
                'meta_channel_id'  => $metaChannel->id,
                'shop_id'          => $modelData['shop_id'],
                'phone_number'     => $customer->phone,
                'ulid'             => Str::ulid(),
                'status'           => ChatSessionStatusEnum::ACTIVE,
                'guest_identifier' => $customer->contact_name ?? $customer->name,
                'metadata'         => [
                    'customer_id' => $customer->id,
                    'name'        => $customer->contact_name ?? $customer->name,
                    'phone'       => $customer->phone,
                ],
            ]);
        });
    }

    public function jsonResponse(MetaChatSession $metaChatSession): array
    {
        return [
            'ulid'             => $metaChatSession->ulid,
            'status'           => $metaChatSession->status->value,
            'priority'         => $metaChatSession->priority->value,
            'contact_name'     => $metaChatSession->guest_identifier,
            'guest_identifier' => $metaChatSession->guest_identifier,
            'phone_number'     => $metaChatSession->phone_number,
            'shop'             => $metaChatSession->shop ? [
                'id'   => $metaChatSession->shop->id,
                'name' => $metaChatSession->shop->name,
            ] : null,
        ];
    }
}
