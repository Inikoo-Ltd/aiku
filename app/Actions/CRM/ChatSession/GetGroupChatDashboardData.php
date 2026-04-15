<?php

/*
 * Author: stewicca
 * Created: Mon, 14 Apr 2025
 * Copyright (c) 2025, Inikoo Ltd
 */

namespace App\Actions\CRM\ChatSession;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Livechat\ChatMessage;
use App\Models\CRM\Livechat\ChatSession;
use App\Models\CRM\Livechat\ShopHasChatAgent;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetGroupChatDashboardData
{
    use AsAction;

    public function handle(Group $group): array
    {
        $openShopIds = Shop::query()
            ->where('group_id', $group->id)
            ->where('state', ShopStateEnum::OPEN)
            ->pluck('id');

        $chatEnabledOpenShopIds = Shop::query()
            ->where('group_id', $group->id)
            ->where('state', ShopStateEnum::OPEN)
            ->where('settings->chat->enable_chat', true)
            ->pluck('id');

        $sessionQuery = ChatSession::query()->whereIn('shop_id', $openShopIds);

        $stats = [
            'chatEnabledShops'    => $chatEnabledOpenShopIds->count(),
            'chatAgents'          => ShopHasChatAgent::query()->whereIn('shop_id', $openShopIds)->distinct('chat_agent_id')->count('chat_agent_id'),
            'chatSessionsTotal'   => (clone $sessionQuery)->count(),
            'chatSessionsWaiting' => (clone $sessionQuery)->where('status', ChatSessionStatusEnum::WAITING)->count(),
            'chatSessionsActive'  => (clone $sessionQuery)->where('status', ChatSessionStatusEnum::ACTIVE)->count(),
            'chatSessionsClosed'  => (clone $sessionQuery)->where('status', ChatSessionStatusEnum::CLOSED)->count(),
            'chatMessagesTotal'   => $this->countMessages($openShopIds),
            'chatMessagesUnread'  => $this->countUnreadMessages($openShopIds),
        ];

        $tableRows = $this->getOrganisations($group);

        return [
            'stats' => $stats,
            'table' => $this->buildTableData($tableRows),
        ];
    }

    private function getOrganisations(Group $group): array
    {
        return Organisation::query()
            ->where('group_id', $group->id)
            ->orderBy('name')
            ->get(['id', 'slug', 'name'])
            ->map(function (Organisation $organisation): array {
                $orgShopIds = Shop::query()
                    ->where('organisation_id', $organisation->id)
                    ->where('state', ShopStateEnum::OPEN)
                    ->pluck('id');

                $sessionQuery = ChatSession::query()->whereIn('shop_id', $orgShopIds);

                return [
                    'id'              => $organisation->id,
                    'slug'            => $organisation->slug,
                    'name'            => $organisation->name,
                    'chatAgentsCount' => ShopHasChatAgent::query()->where('organisation_id', $organisation->id)->distinct('chat_agent_id')->count('chat_agent_id'),
                    'sessionsTotal'   => (clone $sessionQuery)->count(),
                    'sessionsActive'  => (clone $sessionQuery)->where('status', ChatSessionStatusEnum::ACTIVE)->count(),
                    'sessionsWaiting' => (clone $sessionQuery)->where('status', ChatSessionStatusEnum::WAITING)->count(),
                    'sessionsClosed'  => (clone $sessionQuery)->where('status', ChatSessionStatusEnum::CLOSED)->count(),
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
            'organisation' => [
                'formatted_value'   => __('Organisation'),
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
                    'organisation' => [
                        $intervalValue => [
                            'formatted_value' => $row['name'],
                            'raw_value'       => $row['name'],
                            'tooltip'         => $row['name'],
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
            'idTable'   => 'chat_group_dashboard_table',
            'tableData' => [
                'charts'      => [],
                'current_tab' => 'organisations',
                'id'          => 'chat_organisations',
                'tabs'        => [
                    'organisations' => [
                        'icon'  => null,
                        'title' => __('Organisations'),
                    ],
                ],
                'tables' => [
                    'organisations' => [
                        'header' => [
                            'columns' => $headerColumns,
                        ],
                        'body'   => $body,
                        'slug'   => 'organisations',
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
                    'options' => [['label' => __('All'), 'value' => 'always']],
                    'type'    => 'select',
                    'value'   => 'always',
                ],
                'currency_type' => [
                    'align'   => 'left',
                    'id'      => 'currency_type',
                    'options' => [['label' => __('All'), 'value' => 'always']],
                    'type'    => 'select',
                    'value'   => 'always',
                ],
                'model_state_type' => [
                    'align'   => 'left',
                    'id'      => 'model_state_type',
                    'options' => [['label' => __('All'), 'value' => 'all']],
                    'type'    => 'select',
                    'value'   => 'all',
                ],
            ],
        ];
    }
}
