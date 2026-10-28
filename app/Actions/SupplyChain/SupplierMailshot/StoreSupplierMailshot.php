<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierMailshot;

use App\Actions\Comms\Email\StoreEmail;
use App\Actions\Comms\Mailshot\StoreMailshot;
use App\Actions\Comms\Outbox\StoreOutbox;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Enums\Comms\Email\EmailBuilderEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateBuilderEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Models\Comms\Mailshot;
use App\Models\Comms\OrgPostRoom;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * A one-off email to the suppliers of some countries, sent as a shop (its name, address and website for
 * unsubscribing). It lives in an outbox of its own, so it is never listed or counted with the shop's newsletters or
 * marketing, and it is written in the Aiku email editor starting from a newsletter layout.
 */
class StoreSupplierMailshot extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    /**
     * @param  array{shop_id: int, countries: list<int>, subject: string}  $modelData
     */
    public function handle(Shop $shop, array $modelData): Mailshot
    {
        $mailshot = StoreMailshot::make()->action($this->outbox($shop), [
            'subject'           => $modelData['subject'],
            'type'              => MailshotTypeEnum::SUPPLIER->value,
            'recipients_recipe' => ['countries' => array_map('intval', $modelData['countries'])],
        ]);

        if (!$mailshot->email) {
            $template = EmailTemplate::where('state', EmailTemplateStateEnum::ACTIVE)
                ->where('builder', EmailTemplateBuilderEnum::BEEFREE)
                ->whereJsonContains('data->outboxes', OutboxCodeEnum::NEWSLETTER->value)
                ->first();

            StoreEmail::make()->action($mailshot, $template, [
                'subject'               => $mailshot->subject,
                'snapshot_state'        => SnapshotStateEnum::LIVE,
                'snapshot_published_at' => now(),
                'snapshot_recyclable'   => false,
                'snapshot_first_commit' => true,
                'builder'               => EmailBuilderEnum::BEEFREE,
            ], strict: false);
        }

        return $mailshot->refresh();
    }

    private function outbox(Shop $shop): Outbox
    {
        $code = OutboxCodeEnum::SUPPLIER_MAILING;

        $outbox = Outbox::where('shop_id', $shop->id)->where('code', $code)->first();
        if ($outbox) {
            return $outbox;
        }

        /** @var OrgPostRoom $orgPostRoom */
        $orgPostRoom = PostRoom::where('code', $code->postRoomCode()->value)->firstOrFail()
            ->orgPostRooms()->where('organisation_id', $shop->organisation_id)->firstOrFail();

        return StoreOutbox::make()->action($orgPostRoom, $shop, [
            'name'  => $code->label(),
            'code'  => $code,
            'type'  => $code->type(),
            'state' => OutboxStateEnum::ACTIVE,
        ]);
    }

    public function rules(): array
    {
        return [
            'shop_id'     => ['required', Rule::exists('shops', 'id')->where('group_id', $this->group->id)],
            'countries'   => ['required', 'array', 'min:1'],
            'countries.*' => ['integer', 'exists:countries,id'],
            'subject'     => ['required', 'string', 'max:255'],
        ];
    }

    public function asController(ActionRequest $request): Mailshot
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(Shop::findOrFail($this->validatedData['shop_id']), $this->validatedData);
    }

    public function htmlResponse(Mailshot $mailshot): RedirectResponse
    {
        return redirect()->route('grp.supply-chain.supplier_mailshots.workshop', $mailshot->slug);
    }
}
