<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateMailshots;
use App\Actions\Comms\Mailshot\UI\HasUIMailshots;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateMailshots;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMailshots;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateMailshots;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;

class StoreMailshot extends OrgAction
{
    use HasUIMailshots;
    use WithCatalogueAuthorisation;
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

        GroupHydrateMailshots::dispatch($outbox->group)->delay($this->hydratorsDelay);
        OrganisationHydrateMailshots::dispatch($outbox->organisation)->delay($this->hydratorsDelay);
        OutboxHydrateMailshots::dispatch($outbox)->delay($this->hydratorsDelay);
        ShopHydrateMailshots::dispatch($outbox->shop)->delay($this->hydratorsDelay);


        return $mailshot;
    }


    public function rules(): array
    {
        $rules = [
            'subject'           => ['required', 'string', 'max:255'],
            'type'              => ['required', Rule::enum(MailshotTypeEnum::class)],
            'state'             => ['sometimes', Rule::enum(MailshotStateEnum::class)],
            'recipients_recipe' => ['sometimes', 'array']

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


    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->has('type')) {
            $outbox = $request->route('outbox');
            $type   = match ($outbox->code) {
                OutboxCodeEnum::NEWSLETTER => MailshotTypeEnum::NEWSLETTER->value,
                OutboxCodeEnum::MARKETING => MailshotTypeEnum::MARKETING->value,
                default => null
            };
            $this->set('type', $type);
        }
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
