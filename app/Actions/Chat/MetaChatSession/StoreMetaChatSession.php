<?php

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
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
        $metaChatSession = $this->handle($request->validated());

        // TODO: Udpate this to assign the chat to an agent based on the shop's routing rules, not just the agent who started the chat.
        if ($agent = $request->user()?->chatAgent) {
            AssignMetaChatToAgent::run($metaChatSession, $agent, 'Assigned to agent who started the chat');
        }

        return $metaChatSession;
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

        $metaChatSession = DB::transaction(function () use ($metaChannel, $customer, $modelData, $phoneNumber, $name) {
            $existing = MetaChatSession::where('meta_channel_id', $metaChannel->id)
                ->where('shop_id', $modelData['shop_id'])
                ->where('phone_number', $phoneNumber)
                ->latest('id')
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

        if ($metaChatSession->status === ChatSessionStatusEnum::CLOSED) {
            $metaChatSession = ReopenMetaChatSession::make()->reopenToWaiting($metaChatSession, ChatActorTypeEnum::AGENT);
        }

        return $metaChatSession;
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
        $activeAssignment = $metaChatSession->assignments()
            ->with('chatAgent.user')
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->first();

        return [
            'ulid'             => $metaChatSession->ulid,
            'status'           => $metaChatSession->status->value,
            'priority'         => $metaChatSession->priority,
            'contact_name'     => $metaChatSession->guest_identifier,
            'guest_identifier' => $metaChatSession->guest_identifier,
            'phone_number'     => $metaChatSession->phone_number,
            'customer_id'      => $metaChatSession->customer_id,
            'shop'             => $metaChatSession->shop ? [
                'id'   => $metaChatSession->shop->id,
                'name' => $metaChatSession->shop->name,
            ] : null,

            // The agent who started the chat is assigned straight away, so the thread
            // must open ready to type instead of behind an "Assign to me" step.
            'assigned_agent' => $activeAssignment ? [
                'id'      => $activeAssignment->chatAgent?->id,
                'user_id' => $activeAssignment->chatAgent?->user_id,
                'name'    => $activeAssignment->chatAgent?->user?->contact_name,
            ] : null,
        ];
    }
}
