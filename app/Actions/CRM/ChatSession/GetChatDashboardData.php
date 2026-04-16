<?php

namespace App\Actions\CRM\ChatSession;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Livechat\ChatMessage;
use App\Models\CRM\Livechat\ChatSession;
use App\Models\CRM\Livechat\ShopHasChatAgent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatDashboardData
{
    use AsAction;

    public function handle(Organisation $organisation): array
    {
        $openShopIds = Shop::query()
            ->where('organisation_id', $organisation->id)
            ->where('state', ShopStateEnum::OPEN)
            ->pluck('id');

        $chatEnabledOpenShopIds = Shop::query()
            ->where('organisation_id', $organisation->id)
            ->where('state', ShopStateEnum::OPEN)
            ->where('settings->chat->enable_chat', true)
            ->pluck('id');

        $sessionQuery = ChatSession::query()->whereIn('shop_id', $openShopIds);

        $stats = [
            'chatEnabledShops'    => $chatEnabledOpenShopIds->count(),
            'chatAgents'          => ShopHasChatAgent::query()
                ->join('chat_agents', 'chat_agents.id', '=', 'shop_has_chat_agents.chat_agent_id')
                ->where('shop_has_chat_agents.organisation_id', $organisation->id)
                ->whereNull('chat_agents.deleted_at')
                ->distinct('shop_has_chat_agents.chat_agent_id')
                ->count('shop_has_chat_agents.chat_agent_id'),
            'chatSessionsTotal'   => (clone $sessionQuery)->count(),
            'chatSessionsWaiting' => (clone $sessionQuery)->where('status', ChatSessionStatusEnum::WAITING)->count(),
            'chatSessionsActive'  => (clone $sessionQuery)->where('status', ChatSessionStatusEnum::ACTIVE)->count(),
            'chatSessionsClosed'  => (clone $sessionQuery)->where('status', ChatSessionStatusEnum::CLOSED)->count(),
            'chatMessagesTotal'   => $this->countMessages($openShopIds),
            'chatMessagesUnread'  => $this->countUnreadMessages($openShopIds),
        ];

        $tableRows = $this->getOrganisationShops($organisation);

        return [
            'stats'            => $stats,
            'chatEnabledShops' => $tableRows,
            'table'            => $this->buildTableData($tableRows),
        ];
    }

    private function getOrganisationShops(Organisation $organisation): array
    {
        $agentCounts = ShopHasChatAgent::query()
            ->select('shop_has_chat_agents.shop_id')
            ->selectRaw('COUNT(DISTINCT shop_has_chat_agents.chat_agent_id) as chat_agents_count')
            ->join('chat_agents', 'chat_agents.id', '=', 'shop_has_chat_agents.chat_agent_id')
            ->where('shop_has_chat_agents.organisation_id', $organisation->id)
            ->whereNull('chat_agents.deleted_at')
            ->groupBy('shop_has_chat_agents.shop_id')
            ->get()
            ->keyBy('shop_id');

        $sessionCounts = ChatSession::query()
            ->select('shop_id')
            ->selectRaw('COUNT(*) as sessions_total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sessions_active', [ChatSessionStatusEnum::ACTIVE->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sessions_waiting', [ChatSessionStatusEnum::WAITING->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sessions_closed', [ChatSessionStatusEnum::CLOSED->value])
            ->whereIn('shop_id', Shop::query()->where('organisation_id', $organisation->id)->pluck('id'))
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');

        $shops = Shop::query()
            ->where([
                ['organisation_id', '=', $organisation->id],
                ['state', '=', ShopStateEnum::OPEN],
            ])
            ->orderBy('name')
            ->get(['id', 'slug', 'code', 'name', 'settings']);

        return $shops->map(function (Shop $shop) use ($agentCounts, $sessionCounts): array {
            $agentCount = $agentCounts->get($shop->id)?->chat_agents_count ?? 0;
            $sessionCount = $sessionCounts->get($shop->id);

            return [
                'id'               => $shop->id,
                'slug'             => $shop->slug,
                'name'             => $shop->name,
                'chatActive'       => (bool) Arr::get($shop->settings, 'chat.enable_chat', false),
                'chatAgentsCount'  => (int) $agentCount,
                'sessionsTotal'    => (int) ($sessionCount->sessions_total ?? 0),
                'sessionsActive'   => (int) ($sessionCount->sessions_active ?? 0),
                'sessionsWaiting'  => (int) ($sessionCount->sessions_waiting ?? 0),
                'sessionsClosed'   => (int) ($sessionCount->sessions_closed ?? 0),
            ];
        })->values()->all();
    }

    private function countMessages(Collection $shopIds): int
    {
        if ($shopIds->isEmpty()) {
            return 0;
        }

        return ChatMessage::query()
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_messages.chat_session_id')
            ->whereIn('chat_sessions.shop_id', $shopIds)
            ->count();
    }

    private function countUnreadMessages(Collection $shopIds): int
    {
        if ($shopIds->isEmpty()) {
            return 0;
        }

        return ChatMessage::query()
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_messages.chat_session_id')
            ->whereIn('chat_sessions.shop_id', $shopIds)
            ->where('chat_messages.is_read', false)
            ->count();
    }

    private function buildTableData(array $rows): array
    {
        $intervalValue = 'all';

        $headerColumns = [
            'chat_active' => [
                'formatted_value'   => '',
                'raw_value'         => '',
                'tooltip'           => '',
                'align'             => 'center',
                'sortable'          => false,
                'data_display_type' => 'always',
                'currency_type'     => 'always',
                'type'              => 'icon',
            ],
            'shop' => [
                'formatted_value'   => __('Shop'),
                'raw_value'         => '',
                'tooltip'           => '',
                'align'             => 'left',
                'sortable'          => false,
                'data_display_type' => 'always',
                'currency_type'     => 'always',
                'frozen'            => true,
                'alignFrozen'       => 'left',
            ],
            'agents' => [
                'formatted_value'   => __('Agents'),
                'raw_value'         => '',
                'tooltip'           => '',
                'align'             => 'right',
                'sortable'          => false,
                'data_display_type' => 'always',
                'currency_type'     => 'always',
            ],
            'sessions' => [
                'formatted_value'   => __('Sessions'),
                'raw_value'         => '',
                'tooltip'           => '',
                'align'             => 'right',
                'sortable'          => false,
                'data_display_type' => 'always',
                'currency_type'     => 'always',
            ],
            'active' => [
                'formatted_value'   => __('Active'),
                'raw_value'         => '',
                'tooltip'           => '',
                'align'             => 'right',
                'sortable'          => false,
                'data_display_type' => 'always',
                'currency_type'     => 'always',
            ],
            'waiting' => [
                'formatted_value'   => __('Waiting'),
                'raw_value'         => '',
                'tooltip'           => '',
                'align'             => 'right',
                'sortable'          => false,
                'data_display_type' => 'always',
                'currency_type'     => 'always',
            ],
            'closed' => [
                'formatted_value'   => __('Closed'),
                'raw_value'         => '',
                'tooltip'           => '',
                'align'             => 'right',
                'sortable'          => false,
                'data_display_type' => 'always',
                'currency_type'     => 'always',
            ],
        ];

        $body = collect($rows)->map(function (array $row) use ($intervalValue): array {
            return [
                'state'   => 'active',
                'columns' => [
                    'shop' => [
                        $intervalValue => [
                            'formatted_value' => $row['name'],
                            'raw_value'       => $row['name'],
                            'tooltip'         => $row['name'],
                        ],
                    ],
                    'chat_active' => [
                        $intervalValue => [
                            'raw_value'       => $row['chatActive'] ? '1' : '0',
                            'tooltip'         => $row['chatActive'] ? __('Active') : __('Inactive'),
                            'icon_left'       => $row['chatActive']
                                ? [
                                    'icon'    => 'fas fa-check-circle',
                                    'tooltip' => __('Active'),
                                    'class'   => 'text-emerald-500',
                                ]
                                : [
                                    'icon'    => 'fas fa-times-circle',
                                    'tooltip' => __('Inactive'),
                                    'class'   => 'text-red-400',
                                ],
                        ],
                    ],
                    'agents' => [
                        $intervalValue => [
                            'formatted_value' => (string) $row['chatAgentsCount'],
                            'raw_value'       => (string) $row['chatAgentsCount'],
                            'tooltip'         => '',
                        ],
                    ],
                    'sessions' => [
                        $intervalValue => [
                            'formatted_value' => (string) $row['sessionsTotal'],
                            'raw_value'       => (string) $row['sessionsTotal'],
                            'tooltip'         => '',
                        ],
                    ],
                    'active' => [
                        $intervalValue => [
                            'formatted_value' => (string) $row['sessionsActive'],
                            'raw_value'       => (string) $row['sessionsActive'],
                            'tooltip'         => '',
                        ],
                    ],
                    'waiting' => [
                        $intervalValue => [
                            'formatted_value' => (string) $row['sessionsWaiting'],
                            'raw_value'       => (string) $row['sessionsWaiting'],
                            'tooltip'         => '',
                        ],
                    ],
                    'closed' => [
                        $intervalValue => [
                            'formatted_value' => (string) $row['sessionsClosed'],
                            'raw_value'       => (string) $row['sessionsClosed'],
                            'tooltip'         => '',
                        ],
                    ],
                ],
            ];
        })->values()->all();

        return [
            'idTable' => 'chat_dashboard_table',
            'tableData' => [
                'charts'      => [],
                'current_tab' => 'shops',
                'id'          => 'chat_shops',
                'tabs'        => [
                    'shops' => [
                        'icon'  => null,
                        'title' => __('Shops'),
                    ],
                ],
                'tables'      => [
                    'shops' => [
                        'header' => [
                            'columns' => $headerColumns,
                        ],
                        'body'   => $body,
                        'slug'   => 'shops',
                        'state'  => 'active',
                    ],
                ],
            ],
            'intervals' => [
                'options'        => [
                    [
                        'label'      => __('All'),
                        'value'      => $intervalValue,
                        'labelShort' => __('All'),
                    ],
                ],
                'value'          => $intervalValue,
                'range_interval' => '',
            ],
            'settings' => [
                'data_display_type' => [
                    'align'   => 'left',
                    'id'      => 'data_display_type',
                    'options' => [
                        [
                            'label' => __('All'),
                            'value' => 'always',
                        ],
                    ],
                    'type'  => 'select',
                    'value' => 'always',
                ],
                'currency_type' => [
                    'align'   => 'left',
                    'id'      => 'currency_type',
                    'options' => [
                        [
                            'label' => __('All'),
                            'value' => 'always',
                        ],
                    ],
                    'type'  => 'select',
                    'value' => 'always',
                ],
                'model_state_type' => [
                    'align'   => 'left',
                    'id'      => 'model_state_type',
                    'options' => [
                        [
                            'label' => __('All'),
                            'value' => 'all',
                        ],
                    ],
                    'type'  => 'select',
                    'value' => 'all',
                ],
            ],
        ];
    }
}
