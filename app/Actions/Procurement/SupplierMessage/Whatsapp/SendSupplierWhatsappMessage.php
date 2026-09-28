<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierMessage\Whatsapp;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Actions\OrgAction;
use App\Actions\Procurement\SupplierMessage\AssignSupplierMessage;
use App\Actions\Procurement\SupplierMessage\RouteSupplierMessage;
use App\Enums\Procurement\SupplierMessage\SupplierMessageChannelEnum;
use App\Enums\Procurement\SupplierMessage\SupplierMessageDirectionEnum;
use App\Enums\Procurement\SupplierMessage\SupplierMessageRoutedByEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\SupplierMessage;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SendSupplierWhatsappMessage extends OrgAction
{
    use WithWhatsappCredentials;

    /**
     * Free text is only allowed within 24 hours of the supplier's last message; after that Meta
     * accepts nothing but an approved template, which is why procurement settings name one for
     * messages and one for purchase orders. Staff never have to know which of the two went.
     *
     * @param  array{content: string, filename: string}|null  $document
     */
    public function handle(Organisation $organisation, ?User $user, string $phone, string $text, OrgSupplier|OrgAgent|OrgPartner|null $counterpart = null, ?array $document = null, ?PurchaseOrder $purchaseOrder = null): SupplierMessage
    {
        $credentials = $this->procurementWhatsappCredentials($organisation);
        $phone       = RouteSupplierMessage::phoneDigits($phone);

        if ($credentials['phone_number_id'] === '' || $credentials['access_token'] === '') {
            throw ValidationException::withMessages(['body' => __('Connect the procurement WhatsApp number in Procurement settings first.')]);
        }

        $mediaId = $document ? $this->uploadDocument($credentials, $document) : null;

        $payload = self::isWindowOpen($organisation, $phone)
            ? $this->freeFormPayload($text, $mediaId, $document)
            : $this->templatePayload($organisation, $text, $mediaId, $document, $purchaseOrder);

        $response = Http::withToken($credentials['access_token'])
            ->post($this->whatsappEndpoint($credentials['phone_number_id'].'/messages'), [
                'messaging_product' => 'whatsapp',
                'to'                => $phone,
                ...$payload,
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages(['body' => __('WhatsApp refused the message: :error', ['error' => $response->json('error.message', $response->body())])]);
        }

        $counterpart ??= RouteSupplierMessage::make()->byPhone($organisation, $phone)[0];

        return SupplierMessage::create([
            ...SupplierMessage::counterpartAttributes($counterpart),
            'group_id'            => $organisation->group_id,
            'organisation_id'     => $organisation->id,
            'purchase_order_id'   => $purchaseOrder?->id,
            'channel'             => SupplierMessageChannelEnum::WHATSAPP,
            'whatsapp_message_id' => $response->json('messages.0.id'),
            'phone_number'        => $phone,
            'delivery_state'      => 'sent',
            'user_id'             => $user?->id,
            'direction'           => SupplierMessageDirectionEnum::OUTBOUND,
            'routed_by'           => $counterpart ? ($purchaseOrder ? SupplierMessageRoutedByEnum::PURCHASE_ORDER : SupplierMessageRoutedByEnum::MANUAL) : null,
            'from_name'           => $user?->contact_name ?? $organisation->name,
            'to'                  => [['name' => null, 'address' => '+'.$phone]],
            'snippet'             => mb_substr($text, 0, 200),
            'body_text'           => $text,
            'attachments'         => $mediaId ? [['media_id' => $mediaId, 'name' => $document['filename'], 'mime_type' => 'application/pdf', 'size' => strlen($document['content'])]] : [],
            'sent_at'             => now(),
        ]);
    }

    public static function isWindowOpen(Organisation $organisation, string $phone): bool
    {
        return SupplierMessage::where('organisation_id', $organisation->id)
            ->where('channel', SupplierMessageChannelEnum::WHATSAPP)
            ->where('direction', SupplierMessageDirectionEnum::INBOUND)
            ->where('phone_number', RouteSupplierMessage::phoneDigits($phone))
            ->where('sent_at', '>=', now()->subHours(24))
            ->exists();
    }

    public static function isConnected(Organisation $organisation): bool
    {
        return filled(Arr::get($organisation->settings, 'procurement.whatsapp.phone_number_id'));
    }

    private function freeFormPayload(string $text, ?string $mediaId, ?array $document): array
    {
        if ($mediaId) {
            return ['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => $document['filename'], 'caption' => $text]];
        }

        return ['type' => 'text', 'text' => ['body' => $text, 'preview_url' => false]];
    }

    private function templatePayload(Organisation $organisation, string $text, ?string $mediaId, ?array $document, ?PurchaseOrder $purchaseOrder): array
    {
        $settings = Arr::get($organisation->settings, 'procurement.whatsapp', []);
        $name     = $mediaId ? Arr::get($settings, 'purchase_order_template') : Arr::get($settings, 'message_template');

        if (blank($name)) {
            throw ValidationException::withMessages(['body' => __('The supplier has not written in the last 24 hours, so WhatsApp only accepts an approved template. Set one in Procurement settings.')]);
        }

        $components = [[
            'type'       => 'body',
            'parameters' => [['type' => 'text', 'text' => $purchaseOrder ? $purchaseOrder->reference : mb_substr(preg_replace('/\s+/', ' ', $text), 0, 1000)]],
        ]];

        if ($mediaId) {
            array_unshift($components, ['type' => 'header', 'parameters' => [['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => $document['filename']]]]]);
        }

        return ['type' => 'template', 'template' => [
            'name'       => $name,
            'language'   => ['code' => Arr::get($settings, 'template_language', 'en')],
            'components' => $components,
        ]];
    }

    /**
     * @param  array{phone_number_id: string, access_token: string}  $credentials
     * @param  array{content: string, filename: string}  $document
     */
    private function uploadDocument(array $credentials, array $document): string
    {
        $response = Http::withToken($credentials['access_token'])
            ->attach('file', $document['content'], $document['filename'], ['Content-Type' => 'application/pdf'])
            ->post($this->whatsappEndpoint($credentials['phone_number_id'].'/media'), [
                'messaging_product' => 'whatsapp',
                'type'              => 'application/pdf',
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages(['body' => __('WhatsApp refused the file: :error', ['error' => $response->json('error.message', $response->body())])]);
        }

        return (string) $response->json('id');
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        return [
            'phone'       => ['required', 'string', 'max:32'],
            'body'        => ['required', 'string', 'max:4000'],
            'counterpart' => ['sometimes', 'nullable', 'string', 'regex:/^(supplier|agent|partner):\d+$/'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($organisation, $request);

        $counterpart = filled(Arr::get($this->validatedData, 'counterpart'))
            ? AssignSupplierMessage::findCounterpart($organisation, $this->validatedData['counterpart'])
            : null;

        $message = $this->handle($organisation, $request->user(), $this->validatedData['phone'], $this->validatedData['body'], $counterpart);

        return redirect()->route('grp.org.procurement.supplier_messages.show', [$organisation->slug, $message->id])
            ->with('notification', ['status' => 'success', 'title' => __('WhatsApp sent')]);
    }
}
