<?php

namespace App\Actions\SupplyChain\Agent\UI;

trait WithAgentEditAction
{
    protected function agentEditAction(string $routeName, array $routeParameters): array|false
    {
        return $this->canEdit ? [
            'type'  => 'button',
            'style' => 'edit',
            'label' => __('Edit'),
            'route' => [
                'name'       => $routeName,
                'parameters' => array_values($routeParameters),
            ],
        ] : false;
    }
}
