<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Oct 2023 16:11:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class SetMailshotAsReady
{
    use WithActionUpdate;

    public bool $isAction = false;

    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        $updateData = array_merge([
            'state' => MailshotStateEnum::READY,
        ], $modelData);

        if ($mailshot->state == MailshotStateEnum::IN_PROCESS) {
            $updateData['ready_at'] = now();
        }

        $mailshot->update($updateData);

        return $mailshot;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->isAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$request->route('mailshot')->shop_id}.prospects.edit");
    }

    public function rules(): array
    {
        return [
            'publisher_id' => ['sometimes', 'exists:organisation_users,id'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge(
            [
                'publisher_id' => $request->user()->id,
            ]
        );
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): Mailshot
    {

        $request->validate();
        return $this->handle($mailshot, $request->validated());
    }

    public function action(Mailshot $mailshot, $modelData): Mailshot
    {
        $this->isAction = true;
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($mailshot, $validatedData);
    }

    public function htmlResponse(Mailshot $mailshot, ActionRequest $request): RedirectResponse
    {


        return Redirect::route('org.crm.shop.prospects.mailshots.show', [
            $mailshot->parent->slug,
            $mailshot->slug
        ]);
    }
}
