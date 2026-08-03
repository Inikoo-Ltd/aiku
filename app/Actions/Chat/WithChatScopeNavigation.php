<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 15 Jul 2026 14:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

trait WithChatScopeNavigation
{
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): Shop
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment->shop);
    }

    protected function chatRoute(string $suffix): array
    {
        if (isset($this->fulfilment)) {
            return [
                'name'       => 'grp.org.fulfilments.show.chat.'.$suffix,
                'parameters' => [$this->organisation->slug, $this->fulfilment->slug],
            ];
        }

        if (isset($this->shop)) {
            return [
                'name'       => 'grp.org.shops.show.chat.'.$suffix,
                'parameters' => [$this->organisation->slug, $this->shop->slug],
            ];
        }

        return [
            'name'       => 'grp.org.chat.'.$suffix,
            'parameters' => [$this->organisation->slug],
        ];
    }

    protected function chatParentBreadcrumbs(array $routeParameters): array
    {
        if (isset($this->fulfilment)) {
            return ShowFulfilment::make()->getBreadcrumbs($routeParameters);
        }

        if (isset($this->shop)) {
            return ShowShop::make()->getBreadcrumbs($routeParameters);
        }

        return ShowGroupDashboard::make()->getBreadcrumbs();
    }

    protected function chatConversationsBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            $this->chatParentBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-comments',
                        'route' => $this->chatRoute('conversations.show'),
                        'label' => __('Conversations'),
                    ],
                ],
            ]
        );
    }
}
