<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Mailshot\UI\HasUIMailshots;
use App\Actions\Comms\OrgPostRoom\Hydrators\OrgPostRoomHydrateRuns;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateMailshots;
use App\Actions\Comms\PostRoom\Hydrators\PostRoomHydrateRuns;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\HasCatalogueAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreMailshot extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use HasUIMailshots;
    use HasCatalogueAuthorisation;
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(Outbox $outbox, array $modelData): Mailshot
    {
        data_set($modelData, 'date', now(), overwrite: false);
        data_set($modelData, 'group_id', $outbox->group_id);
        data_set($modelData, 'organisation_id', $outbox->organisation_id);
        data_set($modelData, 'shop_id', $outbox->shop_id);


        $mailshot = DB::transaction(function () use ($outbox, $modelData) {
            /** @var Mailshot $mailshot */
            $mailshot = $outbox->mailshots()->create($modelData);
            $mailshot->stats()->create();

            return $mailshot;
        });


        Bus::chain([
            OutboxHydrateMailshots::makeJob($outbox),
            OrgPostRoomHydrateRuns::makeJob($outbox->orgPostRoom),
            PostRoomHydrateRuns::makeJob($outbox->postRoom)

        ])->dispatch()->delay($this->hydratorsDelay);


        return $mailshot;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        //todo
        return false;
    }


    public function rules(): array
    {
        $rules = [
            'subject'           => ['required', 'string', 'max:255'],
            'type'              => ['sometimes', 'required', Rule::enum(MailshotTypeEnum::class)],
            'state'             => ['sometimes', Rule::enum(MailshotStateEnum::class)],
            'recipients_recipe' => ['present', 'array']

        ];

        if (!$this->strict) {
            $rules['date']             = ['nullable', 'date'];
            $rules['ready_at']         = ['nullable', 'date'];
            $rules['scheduled_at']     = ['nullable', 'date'];
            $rules['start_sending_at'] = ['nullable', 'date'];
            $rules['sent_at']          = ['nullable', 'date'];
            $rules['stopped_at']       = ['nullable', 'date'];
            $rules['source_alt_id']    = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['source_alt2_id']   = ['sometimes', 'nullable', 'string', 'max:255'];


            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }


    /**
     * @throws \Throwable
     */
    public function action(Outbox $outbox, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Mailshot
    {
        if (!$audit) {
            Mailshot::disableAuditing();
        }

        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;


        $this->initialisationFromShop($outbox->shop, $modelData);

        return $this->handle($outbox, $this->validatedData);
    }


    /**
     * @throws \Throwable
     */
    public function asController(Outbox $outbox, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($outbox->shop, $request);

        return $this->handle($outbox, $this->validatedData);
    }


    public function htmlResponse(Mailshot $mailshot): \Symfony\Component\HttpFoundation\Response
    {
        return Inertia::location(route('grp.org.shops.show.marketing.mailshots.index', [
            'organisation' => $mailshot->shop->organisation->slug,
            'shop'         => $mailshot->shop->slug
        ]));
    }
}
