<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\Comms\WhatsappCampaign\WithWhatsappRecipientStatusQuery;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Http\Resources\Comms\WhatsappCampaignSentRecipientsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChatSession;
use App\Models\Comms\WhatsappCampaign;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The people a campaign was actually sent to, with the delivery state of each message.
 *
 * Not to be confused with IndexWhatsappCampaignRecipients, which picks the audience
 * before a send; this reads the whatsapp_recipients rows that send created.
 */
class IndexWhatsappCampaignSentRecipients extends OrgAction
{
    use WithMarketingEditAuthorisation;
    use WithWhatsappRecipientStatusQuery;

    private WhatsappCampaign $campaign;

    public function handle(WhatsappCampaign $campaign, ?string $prefix = null): LengthAwarePaginator
    {
        $this->campaign = $campaign;

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $term = strtolower($value);

            $query->where(function ($query) use ($term) {
                $query->whereRaw('LOWER(whatsapp_recipients.recipient_name COLLATE "C") LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(whatsapp_recipients.phone COLLATE "C") LIKE ?', ["%{$term}%"]);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for($this->baseQuery($campaign));

        foreach ($this->getElementGroups($campaign) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('recipient_name')
            ->allowedSorts(['recipient_name', 'phone', 'delivered_at', 'read_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName() ?? '')
            ->withQueryString();
    }

    /**
     * The session is resolved per recipient rather than read off the joined message, because
     * a failed send leaves meta_chat_message_id null so a re-run can retry it; reaching the
     * session through that column would hide the conversation for exactly the failures an
     * agent needs to open. A subselect rather than a join: phone numbers are not unique per
     * shop, so joining would multiply recipient rows and desync the list from its counts.
     *
     * The shop scope, normalisation and latest('id') tie-break mirror
     * SendWhatsappDeliveryChannel::resolveSession(); these two must agree, or this links to
     * a different session than the one the message was written to.
     */
    private function baseQuery(WhatsappCampaign $campaign): \Illuminate\Database\Eloquent\Builder
    {
        return $this->recipientStatusBaseQuery($campaign)
            ->select([
                'whatsapp_recipients.id',
                'whatsapp_recipients.recipient_name',
                'whatsapp_recipients.phone',
                'whatsapp_recipients.meta_chat_message_id',
                'meta_chat_messages.delivered_at',
                'meta_chat_messages.read_at',
                'meta_chat_messages.metadata',
            ])
            ->addSelect(['meta_chat_session_ulid' => MetaChatSession::select('ulid')
                ->where('shop_id', $campaign->shop_id)
                ->whereColumn(
                    DB::raw("regexp_replace(meta_chat_sessions.phone_number, '[^0-9]', '', 'g')"),
                    'whatsapp_recipients.phone'
                )
                ->latest('id')
                ->limit(1)]);
    }

    protected function getElementGroups(WhatsappCampaign $campaign): array
    {
        $counts = [];

        foreach (['sent', 'delivered', 'read', 'failed'] as $status) {
            $counts[$status] = $this->countRecipientsWithStatus($campaign, $status);
        }

        return [
            'status' => [
                'label'    => __('Status'),
                'elements' => [
                    'sent'      => [__('Sent'), $counts['sent']],
                    'delivered' => [__('Delivered'), $counts['delivered']],
                    'read'      => [__('Read'), $counts['read']],
                    'failed'    => [__('Failed'), $counts['failed']],
                ],
                'engine' => function ($query, $elements) {
                    $query->where(function ($query) use ($elements) {
                        foreach ($elements as $status) {
                            $query->orWhere(function ($query) use ($status) {
                                $this->recipientStatusCondition($query, $status);
                            });
                        }
                    });
                },
            ],
        ];
    }

    public function tableStructure(?string $prefix = null, ?WhatsappCampaign $campaign = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $campaign) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No recipients yet'),
                    'count' => 0,
                ])
                ->column(key: 'name', label: __('Recipient'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: __('Status'), canBeHidden: false, align: 'right')
                ->column(key: 'actions', label: '', canBeHidden: false, align: 'right');

            if ($campaign) {
                foreach ($this->getElementGroups($campaign) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }
        };
    }

    public function asController(Organisation $organisation, Shop $shop, WhatsappCampaign $whatsappCampaign, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($whatsappCampaign);
    }

    public function jsonResponse(LengthAwarePaginator $recipients): AnonymousResourceCollection
    {
        return WhatsappCampaignSentRecipientsResource::collection($recipients);
    }
}
