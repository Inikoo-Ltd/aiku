<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierEmail;

use App\Actions\OrgAction;
use App\Models\Procurement\SupplierEmail;
use App\Models\SysAdmin\Organisation;
use App\Services\Gmail\GmailClient;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class DownloadSupplierEmailAttachment extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function handle(SupplierEmail $supplierEmail, int $index): Response
    {
        $attachment = $supplierEmail->attachments[$index] ?? abort(404);
        $client     = GmailClient::forProcurement($supplierEmail->organisation) ?? abort(409, __('The procurement mailbox is not connected.'));

        return response($client->getAttachment($supplierEmail->gmail_message_id, $attachment['attachment_id']), 200, [
            'Content-Type'        => $attachment['mime_type'],
            'Content-Disposition' => 'inline; filename="'.addcslashes($attachment['name'], '"\\').'"',
        ]);
    }

    public function asController(Organisation $organisation, SupplierEmail $supplierEmail, int $index, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        abort_unless($supplierEmail->organisation_id === $organisation->id, 404);

        return $this->handle($supplierEmail, $index);
    }
}
