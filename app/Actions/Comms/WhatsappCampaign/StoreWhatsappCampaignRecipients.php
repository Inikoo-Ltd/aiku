<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WhatsappCampaign;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * Turns a recipient selection into whatsapp_recipients rows.
 *
 * The rows are written when the audience is chosen rather than when the campaign is sent,
 * carrying a null whatsapp_delivery_channel_id until a send batches them into a channel.
 * That null is what separates the two: a row with no channel is a selection, a row with one
 * has been handed to a delivery channel and is no longer the picker's to change.
 *
 * Nothing here trusts a list of phone numbers. Both modes re-run the audience query and
 * insert straight from it, so selecting every contact in a shop costs one INSERT ... SELECT
 * inside Postgres and no phone number is carried through the browser or through PHP.
 */
class StoreWhatsappCampaignRecipients extends OrgAction
{
    use WithActionUpdate;
    use WithMarketingEditAuthorisation;
    use WithWhatsappCampaignAudience;
    use WithWhatsappCampaignSendable;

    private WhatsappCampaign $campaign;

    /**
     * Postgres caps a statement at 65535 bind parameters and the audience subquery spends a
     * handful of its own. A hand made selection never approaches this, it is here so a client
     * posting an enormous explicit list degrades into several statements rather than failing.
     */
    private const KEY_CHUNK = 20000;

    /**
     * How many individual toggles one save may carry.
     */
    private const DELTA_CAP = 5000;

    /**
     * @param  array<string, bool>  $channels
     * @param  array<int, string>|null  $phoneKeys  null selects the whole audience
     *
     * @throws \Throwable
     */
    public function handle(WhatsappCampaign $campaign, array $channels, array $customerFilters, ?array $phoneKeys, array $unselect = []): WhatsappCampaign
    {
        $this->assertEditable($campaign);

        $audience = $this->audienceQuery($campaign, $channels, $customerFilters);

        DB::transaction(function () use ($campaign, $audience, $channels, $customerFilters, $phoneKeys, $unselect) {
            $mark = now();

            $this->insertSelected($campaign, $audience, $phoneKeys, $mark);
            $this->touchKept($campaign, $audience, $phoneKeys, $mark);
            $this->sweepUnselected($campaign, $mark);
            $this->removeUnselected($campaign, $unselect);

            $this->update($campaign, [
                'recipients_recipe' => $this->recipe($channels, $customerFilters),
                'recipients_count'  => $campaign->recipients()->count(),
            ]);
        });

        $campaign->refresh();

        $this->syncReadyState($campaign);

        return $campaign->refresh();
    }

    /**
     * Applies only what the user touched, for a campaign whose stored selection is too large
     * for the page to restate. Everything not named is left exactly as it is.
     *
     * There is no mark and sweep here, deliberately: those passes reconcile a complete
     * selection against the stored rows, and a delta is not a complete selection. Sweeping on
     * one would delete every contact the user simply did not scroll to.
     *
     * @param  array<int, string>  $select
     * @param  array<int, string>  $unselect
     *
     * @throws \Throwable
     */
    public function handleDelta(WhatsappCampaign $campaign, array $channels, array $customerFilters, array $select, array $unselect): WhatsappCampaign
    {
        $this->assertEditable($campaign);

        $audience = $this->audienceQuery($campaign, $channels, $customerFilters);

        DB::transaction(function () use ($campaign, $audience, $channels, $customerFilters, $select, $unselect) {
            $this->removeUnselected($campaign, $unselect);

            if ($select) {
                $this->insertSelected($campaign, $audience, $select, now());
            }

            $this->update($campaign, [
                'recipients_recipe' => $this->recipe($channels, $customerFilters),
                'recipients_count'  => $campaign->recipients()->count(),
            ]);
        });

        $campaign->refresh();

        $this->syncReadyState($campaign);

        return $campaign->refresh();
    }

    /**
     * ON CONFLICT DO NOTHING rather than DO UPDATE: a row already sitting on this
     * (campaign, phone) is either an unchanged selection or one a send has already claimed,
     * and neither wants overwriting. The conflict is untargeted, which is only equivalent to
     * naming (whatsapp_campaign_id, phone) while that stays the table's one unique index.
     *
     * @param  array<int, string>|null  $phoneKeys
     */
    private function insertSelected(WhatsappCampaign $campaign, Builder $audience, ?array $phoneKeys, Carbon $mark): void
    {
        foreach ($this->keyChunks($phoneKeys) as $chunk) {
            DB::table('whatsapp_recipients')->insertOrIgnoreUsing(
                ['whatsapp_campaign_id', 'recipient_type', 'recipient_id', 'recipient_name', 'phone', 'created_at', 'updated_at'],
                $this->selectedAudience($audience, $chunk)
                    ->select([
                        DB::raw($campaign->id.'::integer'),
                        DB::raw("case when audience.customer_id is not null then 'Customer' else 'MetaChatSession' end"),
                        DB::raw('coalesce(audience.customer_id, audience.meta_chat_session_id)::integer'),
                        DB::raw("nullif(btrim(audience.name), '')"),
                        'audience.recipient_key',
                        DB::raw('?'),
                        DB::raw('?'),
                    ])
                    ->addBinding([$mark, $mark], 'select')
            );
        }
    }

    /**
     * Rows that were already stored and are still selected, stamped with this save's mark so
     * the sweep can tell them from the ones that were dropped.
     *
     * @param  array<int, string>|null  $phoneKeys
     */
    private function touchKept(WhatsappCampaign $campaign, Builder $audience, ?array $phoneKeys, Carbon $mark): void
    {
        foreach ($this->keyChunks($phoneKeys) as $chunk) {
            $this->unbatchedRows($campaign)
                ->whereExists(
                    $this->selectedAudience($audience, $chunk)
                        ->whereColumn('audience.recipient_key', 'whatsapp_recipients.phone')
                        ->selectRaw('1')
                )
                ->update(['updated_at' => $mark]);
        }
    }

    /**
     * Mark and sweep rather than a delete of everything outside the selection: an explicit
     * list arrives in chunks, and a chunk by chunk delete would remove the rows a later chunk
     * was going to keep. Marking first makes the two passes independent of how the keys were
     * split, and leaves one delete whatever the mode.
     *
     * updated_at doubles as the mark. That holds because this runs in a transaction and
     * nothing else writes a row while its channel is still null, but it is the kind of
     * assumption that rots: a second writer touching these rows would have them swept.
     */
    private function sweepUnselected(WhatsappCampaign $campaign, Carbon $mark): void
    {
        $this->unbatchedRows($campaign)
            ->where('updated_at', '<', $mark)
            ->delete();
    }

    /**
     * The contacts unticked after the audience was restated. Applied after the sweep rather
     * than folded into the selection, so selecting everything and then dropping a handful is
     * one statement about the audience and one about the exceptions.
     *
     * @param  array<int, string>  $unselect
     */
    private function removeUnselected(WhatsappCampaign $campaign, array $unselect): void
    {
        foreach (array_chunk($unselect, self::KEY_CHUNK) as $chunk) {
            $this->unbatchedRows($campaign)->whereIn('phone', $chunk)->delete();
        }
    }

    private function recipe(array $channels, array $customerFilters): array
    {
        return [
            'type'             => 'hybrid',
            'channels'         => $channels,
            'customer_filters' => $customerFilters,
        ];
    }

    /**
     * Rows a send has not claimed yet. Everything this action writes or removes is scoped
     * through here, so a campaign that is already going out cannot have the audience pulled
     * out from under it even if a stale page posts to it.
     */
    private function unbatchedRows(WhatsappCampaign $campaign): \Illuminate\Database\Query\Builder
    {
        return DB::table('whatsapp_recipients')
            ->where('whatsapp_campaign_id', $campaign->id)
            ->whereNull('whatsapp_delivery_channel_id');
    }

    /**
     * The audience narrowed to what was selected, and only to rows that can name who they
     * are: recipient_type and recipient_id are not nullable, and every branch of the audience
     * query sets a customer or a session, so this guard only catches a branch added later
     * that sets neither.
     *
     * @param  array<int, string>|null  $chunk  null selects the whole audience
     */
    private function selectedAudience(Builder $audience, ?array $chunk): Builder
    {
        return DB::query()
            ->fromSub($audience, 'audience')
            ->whereRaw('coalesce(audience.customer_id, audience.meta_chat_session_id) is not null')
            ->when($chunk !== null, fn (Builder $query) => $query->whereIn('audience.recipient_key', $chunk));
    }

    /**
     * One pass over the whole audience when nothing was listed, otherwise the keys split into
     * statement sized pieces. An empty list is a deliberate clear and matches nothing.
     *
     * @param  array<int, string>|null  $phoneKeys
     * @return array<int, array<int, string>|null>
     */
    private function keyChunks(?array $phoneKeys): array
    {
        if ($phoneKeys === null) {
            return [null];
        }

        if ($phoneKeys === []) {
            return [];
        }

        return array_chunk($phoneKeys, self::KEY_CHUNK);
    }

    /**
     * Drops rather than rejects what WhatsApp cannot deliver to, the same way the audience
     * itself does: one unusable number in a page selection must not fail the whole save.
     */
    public function prepareForValidation(ActionRequest $request): void
    {
        foreach (['phone_keys', 'select', 'unselect'] as $field) {
            $keys = $this->get($field);

            if (!is_array($keys)) {
                continue;
            }

            $this->set($field, $this->normaliseKeys($keys));
        }
    }

    /**
     * @param  array<int, mixed>  $keys
     * @return array<int, string>
     */
    private function normaliseKeys(array $keys): array
    {
        return array_values(array_unique(array_filter(array_map(
            function ($phone) {
                if (!is_scalar($phone) || !GetWhatsappRecipientsQuery::isSendablePhone((string) $phone)) {
                    return '';
                }

                return GetWhatsappRecipientsQuery::normalisePhoneKey((string) $phone);
            },
            $keys
        ))));
    }

    public function rules(): array
    {
        return [
            'select_all'       => ['sometimes', 'boolean'],
            'phone_keys'       => ['required_without_all:select_all,select,unselect', 'array'],
            'phone_keys.*'     => ['string', 'regex:/^[1-9][0-9]{3,14}$/'],
            /* A delta names only what the user touched. Capped because past a few thousand
               the page is describing an audience rather than an edit, and select_all or an
               explicit list says that far more cheaply. */
            'select'           => ['sometimes', 'array', 'max:'.self::DELTA_CAP],
            'select.*'         => ['string', 'regex:/^[1-9][0-9]{3,14}$/'],
            'unselect'         => ['sometimes', 'array', 'max:'.self::DELTA_CAP],
            'unselect.*'       => ['string', 'regex:/^[1-9][0-9]{3,14}$/'],
            'channels'         => ['sometimes', 'array'],
            'channels.*'       => ['boolean'],
            'customer_filters' => ['sometimes', 'array'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(WhatsappCampaign $campaign, array $modelData): WhatsappCampaign
    {
        $this->asAction = true;
        $this->campaign = $campaign;
        $this->initialisationFromShop($campaign->shop, $modelData);

        return $this->handleFromValidated($campaign);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Shop $shop, WhatsappCampaign $whatsappCampaign, ActionRequest $request): WhatsappCampaign
    {
        $this->campaign = $whatsappCampaign;
        $this->initialisationFromShop($shop, $request);

        return $this->handleFromValidated($whatsappCampaign);
    }

    /**
     * @throws \Throwable
     */
    private function handleFromValidated(WhatsappCampaign $campaign): WhatsappCampaign
    {
        $isSelectAll = filter_var(Arr::get($this->validatedData, 'select_all', false), FILTER_VALIDATE_BOOLEAN);
        $channels    = $this->readAudienceChannels(Arr::get($this->validatedData, 'channels'), $campaign);
        $filters     = $this->readAudienceCustomerFilters(Arr::get($this->validatedData, 'customer_filters'), $campaign);

        $select   = Arr::get($this->validatedData, 'select', []);
        $unselect = Arr::get($this->validatedData, 'unselect', []);

        /* A delta only makes sense against a stored selection, so select_all wins: it says
           the user restated the whole audience rather than edited it. */
        if (!$isSelectAll && ($select || $unselect)) {
            return $this->handleDelta($campaign, $channels, $filters, $select, $unselect);
        }

        return $this->handle(
            $campaign,
            $channels,
            $filters,
            $isSelectAll ? null : Arr::get($this->validatedData, 'phone_keys', []),
            $unselect,
        );
    }

    public function jsonResponse(WhatsappCampaign $campaign): array
    {
        return ['recipients_count' => $campaign->recipients_count];
    }
}
