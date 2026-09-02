<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Http\Resources\Comms\WhatsappCampaignSentRecipientsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WhatsappCampaign;
use App\Models\Comms\WhatsappRecipient;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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

    private function baseQuery(WhatsappCampaign $campaign): \Illuminate\Database\Eloquent\Builder
    {
        return WhatsappRecipient::query()
            ->where('whatsapp_recipients.whatsapp_campaign_id', $campaign->id)
            ->leftJoin('meta_chat_messages', 'meta_chat_messages.id', '=', 'whatsapp_recipients.meta_chat_message_id')
            ->leftJoin('meta_chat_sessions', 'meta_chat_sessions.id', '=', 'meta_chat_messages.meta_chat_session_id')
            ->select([
                'whatsapp_recipients.id',
                'whatsapp_recipients.recipient_name',
                'whatsapp_recipients.phone',
                'whatsapp_recipients.meta_chat_message_id',
                'meta_chat_messages.delivered_at',
                'meta_chat_messages.read_at',
                'meta_chat_messages.metadata',
                'meta_chat_sessions.ulid as meta_chat_session_ulid',
            ]);
    }

    /**
     * The same precedence the resource reads a status with, expressed as SQL so a tab
     * narrows the list to exactly the rows that render with that status.
     */
    private function statusCondition(Builder|\Illuminate\Database\Eloquent\Builder $query, string $status): void
    {
        match ($status) {
            'failed' => $query->where(function ($query) {
                $query->whereNull('whatsapp_recipients.meta_chat_message_id')
                    ->orWhereRaw("meta_chat_messages.metadata->>'wa_status' = 'failed'");
            }),
            'read' => $query->whereNotNull('whatsapp_recipients.meta_chat_message_id')
                ->whereNotNull('meta_chat_messages.read_at')
                ->whereRaw("coalesce(meta_chat_messages.metadata->>'wa_status', '') <> 'failed'"),
            'delivered' => $query->whereNotNull('whatsapp_recipients.meta_chat_message_id')
                ->whereNotNull('meta_chat_messages.delivered_at')
                ->whereNull('meta_chat_messages.read_at')
                ->whereRaw("coalesce(meta_chat_messages.metadata->>'wa_status', '') <> 'failed'"),
            default => $query->whereRaw('1 = 0'),
        };
    }

    protected function getElementGroups(WhatsappCampaign $campaign): array
    {
        $counts = [];

        foreach (['delivered', 'read', 'failed'] as $status) {
            $countQuery = $this->baseQuery($campaign);
            $this->statusCondition($countQuery, $status);
            $counts[$status] = $countQuery->count();
        }

        return [
            'status' => [
                'label'    => __('Status'),
                'elements' => [
                    'delivered' => [__('Delivered'), $counts['delivered']],
                    'read'      => [__('Read'), $counts['read']],
                    'failed'    => [__('Failed'), $counts['failed']],
                ],
                'engine' => function ($query, $elements) {
                    $query->where(function ($query) use ($elements) {
                        foreach ($elements as $status) {
                            $query->orWhere(function ($query) use ($status) {
                                $this->statusCondition($query, $status);
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
