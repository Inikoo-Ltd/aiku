<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\Traits\WithGroupModuleScope;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

trait WithTicketsScope
{
    use WithGroupModuleScope;

    protected function initialisationFromTicketsScope(ActionRequest $request, ?Organisation $organisation = null, ?Shop $shop = null): static
    {
        return $this->initialisationFromModuleScope($request, $organisation, $shop);
    }

    /**
     * @param  array<int, string>  $extraParameters
     *
     * @return array{name: string, parameters: array<int, string>}
     */
    protected function ticketsRoute(string $suffix, array $extraParameters = []): array
    {
        return $this->moduleScopeRoute('tickets', $suffix, $extraParameters);
    }

    protected function ticketsBreadcrumbs(): array
    {
        return array_merge(
            $this->moduleScopeParentBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $this->ticketsRoute('index'),
                        'label' => __('Tickets'),
                    ],
                ],
            ]
        );
    }

    protected function ticketsListBreadcrumbs(): array
    {
        return array_merge(
            $this->ticketsBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $this->ticketsRoute('list'),
                        'label' => __('List'),
                    ],
                ],
            ]
        );
    }
}
