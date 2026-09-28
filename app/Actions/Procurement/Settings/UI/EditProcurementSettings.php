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
        $mailbox  = Arr::get($organisation->settings, 'procurement.gmail', []);
        $whatsapp = Arr::get($organisation->settings, 'procurement.whatsapp', []);

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
                        [
                            'label'  => __('Supplier WhatsApp'),
                            'icon'   => 'fab fa-whatsapp',
                            'fields' => [
                                'whatsapp_display_phone'           => [
                                    'type'        => 'input',
                                    'label'       => __('Phone number'),
                                    'placeholder' => '+44 7700 900000',
                                    'information' => __('A WhatsApp Business number of its own for procurement, not a shop number, registered under this organisation\'s Meta app.'),
                                    'value'       => Arr::get($whatsapp, 'display_phone'),
                                ],
                                'whatsapp_phone_number_id'         => [
                                    'type'        => 'input',
                                    'label'       => __('Phone number ID'),
                                    'information' => __('From Meta WhatsApp Manager, API setup. Messages to this number arrive in the procurement inbox.'),
                                    'value'       => Arr::get($whatsapp, 'phone_number_id'),
                                ],
                                'whatsapp_waba_id'                 => [
                                    'type'  => 'input',
                                    'label' => __('WhatsApp Business Account ID'),
                                    'value' => Arr::get($whatsapp, 'waba_id'),
                                ],
                                'whatsapp_phone_status'            => [
                                    'type'         => 'whatsapp_phone_status',
                                    'label'        => __('Status'),
                                    'information'  => __('Ask Meta whether this number is live and whether the WhatsApp Business Account delivers its messages to Aiku.'),
                                    'noSaveButton' => true,
                                    'value'        => Arr::get($whatsapp, 'last_status_check'),
                                    'routes'       => [
                                        'status'          => ['name' => 'grp.org.procurement.settings.whatsapp_phone.status', 'parameters' => [$organisation->slug]],
                                        'subscribed_apps' => ['name' => 'grp.org.procurement.settings.whatsapp_app.subscribed', 'parameters' => [$organisation->slug]],
                                    ],
                                ],
                                'whatsapp_message_template'        => [
                                    'type'        => 'input',
                                    'label'       => __('Message template'),
                                    'information' => __('Approved template used when the supplier has not written in 24 hours. Its body must have one variable, which carries the message.').$this->templateFetchStatus($whatsapp, 'message_template'),
                                    'value'       => Arr::get($whatsapp, 'message_template'),
                                ],
                                'whatsapp_purchase_order_template' => [
                                    'type'        => 'input',
                                    'label'       => __('Purchase order template'),
                                    'information' => __('Approved template with a document header and one body variable for the order reference, used to send purchase orders.').$this->templateFetchStatus($whatsapp, 'purchase_order_template'),
                                    'value'       => Arr::get($whatsapp, 'purchase_order_template'),
                                ],
                                'whatsapp_template_language'       => [
                                    'type'        => 'input',
                                    'label'       => __('Template language'),
                                    'placeholder' => 'en',
                                    'value'       => Arr::get($whatsapp, 'template_language'),
                                ],
                            ],
                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.org.procurement.settings.update',
                            'parameters' => [$organisation->slug],
                        ],
                    ],
                ],
            ]
        );
    }

    private function templateFetchStatus(array $whatsapp, string $key): string
    {
        $fetch = Arr::get($whatsapp, "{$key}_meta");

        if (! $fetch) {
            return '';
        }

        if ($fetch['fetch_status'] !== 'found') {
            return ' '.__('Meta: :error', ['error' => $fetch['error']]);
        }

        return ' '.__('Meta: :status, :language.', ['status' => Arr::get($fetch, 'template.status'), 'language' => Arr::get($fetch, 'template.language')]);
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
