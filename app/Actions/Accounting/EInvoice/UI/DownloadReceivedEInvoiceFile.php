<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingAuthorisation;
use App\Models\Accounting\ReceivedEInvoice;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class DownloadReceivedEInvoiceFile extends OrgAction
{
    use WithAccountingAuthorisation;

    public function handle(ReceivedEInvoice $receivedEInvoice, string $format): Response
    {
        $content = $format === 'pdf' ? $receivedEInvoice->pdfContent() : $receivedEInvoice->xmlContent();
        abort_if($content === null, 404);

        $name = Str::slug(($receivedEInvoice->sender_name ?: 'e-invoice').'-'.($receivedEInvoice->document_number ?: $receivedEInvoice->provider_transaction_id)).'.'.$format;

        return response($content, 200, [
            'Content-Type'           => $format === 'pdf' ? 'application/pdf' : 'application/xml',
            'Content-Disposition'    => ($format === 'pdf' ? 'inline' : 'attachment')."; filename=\"$name\"",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function xml(Organisation $organisation, ReceivedEInvoice $receivedEInvoice, ActionRequest $request): Response
    {
        return $this->download($organisation, $receivedEInvoice, $request, 'xml');
    }

    public function pdf(Organisation $organisation, ReceivedEInvoice $receivedEInvoice, ActionRequest $request): Response
    {
        return $this->download($organisation, $receivedEInvoice, $request, 'pdf');
    }

    private function download(Organisation $organisation, ReceivedEInvoice $receivedEInvoice, ActionRequest $request, string $format): Response
    {
        $this->initialisation($organisation, $request);
        abort_unless($receivedEInvoice->organisation_id === $organisation->id, 404);

        return $this->handle($receivedEInvoice, $format);
    }
}
