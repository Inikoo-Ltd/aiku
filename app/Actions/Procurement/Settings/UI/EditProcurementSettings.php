<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\Settings\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditProcurementSettings extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'org-supervisor.'.$this->organisation->id.'.procurement']);
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $organisation;
    }

    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        $title   = __('Procurement settings');
        $mailbox = Arr::get($organisation->settings, 'procurement.gmail', []);

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => ['fal', 'fa-cog'],
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Supplier mailbox'),
                            'icon'   => 'fa-light fa-envelope',
                            'fields' => [
                                'mailbox' => [
                                    'type'         => 'mailbox_connect',
                                    'noTitle'      => true,
                                    'noSaveButton' => true,
                                    'value'        => [
                                        'connected'        => filled(Arr::get($mailbox, 'email')),
                                        'email'            => Arr::get($mailbox, 'email'),
                                        'connected_at'     => Arr::get($mailbox, 'connected_at'),
                                        'connect_url'      => route('grp.org.procurement.settings.mailbox.connect', [$organisation->slug]),
                                        'disconnect_route' => [
                                            'name'       => 'grp.org.procurement.settings.mailbox.disconnect',
                                            'parameters' => [$organisation->slug],
                                        ],
                                        'texts'            => [
                                            'connected'       => __('Emails between staff and suppliers go through this mailbox.'),
                                            'not_connected'   => __('Sign in with the address suppliers write to, for example purchasing@ of this organisation, not with your own account. The account you pick is the inbox that gets connected.'),
                                            'success'         => __('This organisation\'s procurement mailbox is now connected.'),
                                            'confirm_disconnect' => __('Disconnect this mailbox? Supplier emails will stop arriving in Aiku.'),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.org.settings.update',
                            'parameters' => [$organisation->id],
                        ],
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.settings.edit',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Settings'),
                    ],
                ],
            ]
        );
    }
}
