<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierMailshot;

use App\Actions\Comms\Mailshot\SendMailShot;
use App\Actions\Comms\Mailshot\SetMailshotAsReady;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Sends an email to suppliers once its text is published in the editor.
 */
class SendSupplierMailshot extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    public function handle(Mailshot $mailshot): Mailshot
    {
        if (!$mailshot->email?->liveSnapshot?->compiled_layout) {
            throw ValidationException::withMessages(['send' => __('Publish the email in the editor first (Ctrl+S), then send it.')]);
        }

        if (in_array($mailshot->state, [MailshotStateEnum::IN_PROCESS, MailshotStateEnum::READY])) {
            SetMailshotAsReady::make()->action($mailshot, []);
            SendMailShot::run($mailshot->refresh());
        }

        return $mailshot;
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        abort_if($mailshot->type !== MailshotTypeEnum::SUPPLIER || $mailshot->group_id !== group()->id, 404);
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($mailshot);
    }

    public function htmlResponse(Mailshot $mailshot): RedirectResponse
    {
        return redirect()->route('grp.supply-chain.supplier_mailshots.show', $mailshot->slug);
    }
}
