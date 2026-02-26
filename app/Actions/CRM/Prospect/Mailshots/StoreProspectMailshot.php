<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Feb 2026 14:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\Mailshots;

use App\Actions\Comms\Mailshot\StoreMailshot;
use App\Actions\OrgAction;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreProspectMailshot extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): Mailshot
    {
        // Get or create the invite outbox for this shop
        $outbox = Outbox::where('shop_id', $shop->id)
            ->where('code', OutboxCodeEnum::INVITE)
            ->first();

        if (!$outbox) {
            // Create outbox if it doesn't exist
            $outbox = Outbox::create([
                'group_id'       => $shop->group_id,
                'organisation_id' => $shop->organisation_id,
                'shop_id'        => $shop->id,
                'code'           => OutboxCodeEnum::INVITE,
                'name'           => 'Invite',
                'state'          => OutboxCodeEnum::INVITE->defaultState(),
                'type'           => OutboxCodeEnum::INVITE->type(),
            ]);
        }

        // Set default type as INVITE for prospect mailshots
        data_set($modelData, 'type', MailshotTypeEnum::INVITE->value, overwrite: false);

        return StoreMailshot::make()->action(
            outbox: $outbox,
            modelData: $modelData,
            hydratorsDelay: $this->hydratorsDelay,
            strict: $this->strict,
            audit: true
        );
    }

    public function rules(): array
    {
        return [
            'subject'           => ['required', 'string', 'max:255'],
            'state'             => ['sometimes', Rule::enum(MailshotStateEnum::class)],
            'recipients_recipe' => ['sometimes', 'array'],
            'date'              => ['nullable', 'date'],
            'ready_at'          => ['nullable', 'date'],
            'scheduled_at'      => ['nullable', 'date'],
            'start_sending_at'  => ['nullable', 'date'],
            'sent_at'           => ['nullable', 'date'],
            'stopped_at'        => ['nullable', 'date'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(Mailshot $mailshot): Response
    {
        // grp.org.shops.show.crm.prospects.mailshots.create
        return Inertia::location(route('grp.org.shops.show.crm.prospects.mailshots.show', [
            'organisation' => $mailshot->shop->organisation->slug,
            'shop'         => $mailshot->shop->slug,
            'mailshot'     => $mailshot->slug
        ]));
    }
}
