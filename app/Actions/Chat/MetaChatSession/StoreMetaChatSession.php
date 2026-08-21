<?php

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\CRM\Customer;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatSession;
use Illuminate\Support\Arr;
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
            'shop_id'      => ['required', 'exists:shops,id'],
            'customer_id'  => ['nullable', 'exists:customers,id'],
            'phone_number' => ['required_without:customer_id', 'nullable', 'string', 'max:50', 'regex:/^\+[1-9][\d\s\-().]{6,20}$/'],
            'name'         => ['nullable', 'string'],
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

        $customer = isset($modelData['customer_id']) ? Customer::findOrFail($modelData['customer_id']) : null;

        $phoneNumber = $this->normalisePhoneNumber($customer?->phone ?? Arr::get($modelData, 'phone_number'));

        if (blank($phoneNumber)) {
            throw ValidationException::withMessages([
                'phone_number' => __('No phone number available for this chat session.'),
            ]);
        }

        if (str_starts_with($phoneNumber, '+') && !preg_match('/^\+[1-9]\d{7,14}$/', $phoneNumber)) {
            throw ValidationException::withMessages([
                'phone_number' => __('Enter a valid phone number.'),
            ]);
        }

        $name = $customer?->contact_name ?? $customer?->name ?? Arr::get($modelData, 'name');

        return DB::transaction(function () use ($metaChannel, $customer, $modelData, $phoneNumber, $name) {
            $existing = MetaChatSession::where('meta_channel_id', $metaChannel->id)
                ->where('shop_id', $modelData['shop_id'])
                ->where('phone_number', $phoneNumber)
                ->where('status', '!=', ChatSessionStatusEnum::CLOSED)
                ->first();

            if ($existing) {
                return $existing;
            }

            return MetaChatSession::create([
                'meta_channel_id'  => $metaChannel->id,
                'shop_id'          => $modelData['shop_id'],
                'customer_id'      => $customer?->id,
                'phone_number'     => $phoneNumber,
                'ulid'             => Str::ulid(),
                'status'           => ChatSessionStatusEnum::ACTIVE,
                'guest_identifier' => $name,
                'metadata'         => [
                    'customer_id'    => $customer?->id,
                    'name'           => $name,
                    'phone'          => $phoneNumber,
                    'is_new_contact' => $customer === null,
                ],
            ]);
        });
    }

    /**
     * Strips separators from E.164 numbers so they match the form the WhatsApp
     * webhook stores. National formats are left untouched, guessing a country
     * code would be worse than keeping what was given.
     */
    protected function normalisePhoneNumber(?string $phoneNumber): ?string
    {
        $phoneNumber = trim((string) $phoneNumber);

        if (!str_starts_with($phoneNumber, '+')) {
            return $phoneNumber === '' ? null : $phoneNumber;
        }

        return '+'.preg_replace('/\D/', '', $phoneNumber);
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
