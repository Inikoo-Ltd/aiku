<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierMessage;

use App\Actions\OrgAction;
use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Enums\Procurement\SupplierMessage\SupplierMessageChannelEnum;
use App\Models\Procurement\SupplierMessage;
use Illuminate\Support\Facades\Http;
use App\Models\SysAdmin\Organisation;
use App\Services\Gmail\GmailClient;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class DownloadSupplierMessageAttachment extends OrgAction
{
    use WithWhatsappCredentials;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function handle(SupplierMessage $supplierMessage, int $index): Response
    {
        $attachment = $supplierMessage->attachments[$index] ?? abort(404);

        if ($supplierMessage->channel === SupplierMessageChannelEnum::WHATSAPP) {
            $content = $this->whatsappMedia($supplierMessage, $attachment['media_id']);
        } else {
            $client  = GmailClient::forProcurement($supplierMessage->organisation) ?? abort(409, __('The procurement mailbox is not connected.'));
            $content = $client->getAttachment($supplierMessage->gmail_message_id, $attachment['attachment_id']);
        }

        return response($content, 200, [
            'Content-Type'        => $attachment['mime_type'],
            'Content-Disposition' => 'inline; filename="'.addcslashes($attachment['name'], '"\\').'"',
        ]);
    }

    /**
     * Meta hands out a download address per request that lasts five minutes, so it is asked for
     * every time rather than kept.
     */
    private function whatsappMedia(SupplierMessage $supplierMessage, string $mediaId): string
    {
        $token = $this->procurementWhatsappCredentials($supplierMessage->organisation)['access_token'];

        $url = Http::withToken($token)->get($this->whatsappEndpoint($mediaId))->json('url') ?? abort(404);

        return Http::withToken($token)->get($url)->throw()->body();
    }

    public function asController(Organisation $organisation, SupplierMessage $supplierMessage, int $index, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        abort_unless($supplierMessage->organisation_id === $organisation->id, 404);

        return $this->handle($supplierMessage, $index);
    }
}
