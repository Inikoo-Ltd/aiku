<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\Settings\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\Settings\UpdateProcurementSettings;
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
                                        'revoked_at'       => Arr::get($mailbox, 'revoked_at'),
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
                        ...(UpdateProcurementSettings::usesWechat($organisation) ? [$this->wechatSection($organisation)] : []),
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

    /**
     * @return array<string, mixed>
     */
    public function wechatSection(Organisation $organisation): array
    {
        $wechat = Arr::get($organisation->settings, 'procurement.wechat', []);

        return [
            'label'  => __('Supplier WeChat'),
            'icon'   => 'fab fa-weixin',
            'fields' => [
                'wechat_corp_id'        => [
                    'type'        => 'input',
                    'label'       => __('Corp ID'),
                    'placeholder' => 'ww0123456789abcdef',
                    'information' => __('The WeCom company whose staff talk to suppliers, from the WeCom admin console under My company. Suppliers keep using normal WeChat.'),
                    'value'       => Arr::get($wechat, 'corp_id'),
                ],
                'wechat_archive_secret' => [
                    'type'                  => 'password',
                    'label'                 => __('Chat archive secret'),
                    'placeholder'           => $this->secretPlaceholder($wechat, 'archive_secret'),
                    'information'           => __('Shown on the Chat archive page of the WeCom admin console once the chat archive licence is bought. Lets Aiku read the chats of staff with a licence.'),
                    'value'                 => '',
                    'use_generate_password' => false,
                ],
                'wechat_contact_secret' => [
                    'type'                  => 'password',
                    'label'                 => __('Customer contact secret'),
                    'placeholder'           => $this->secretPlaceholder($wechat, 'contact_secret'),
                    'information'           => __('From Customer contact, API in the WeCom admin console. Lets Aiku read the names and companies of the suppliers staff have added.'),
                    'value'                 => '',
                    'use_generate_password' => false,
                ],
                'wechat_private_key'    => [
                    'type'        => 'textarea',
                    'label'       => __('Private key'),
                    'placeholder' => $this->secretPlaceholder($wechat, 'private_key', '-----BEGIN PRIVATE KEY-----'),
                    'information' => __('The private half of the RSA key pair whose public key is uploaded on the Chat archive page. WeCom encrypts every archived message with that public key.'),
                    'value'       => '',
                ],
                'wechat_key_version'    => [
                    'type'        => 'input',
                    'label'       => __('Key version'),
                    'placeholder' => '1',
                    'information' => __('The version WeCom gave the public key when it was uploaded. Update it together with the private key when the key is replaced.'),
                    'value'       => Arr::get($wechat, 'key_version'),
                ],
                'wechat_callback_token'   => [
                    'type'                  => 'password',
                    'label'                 => __('Callback token'),
                    'placeholder'           => $this->secretPlaceholder($wechat, 'callback_token'),
                    'information'           => __('On the Chat archive page, set the callback URL to :url and copy the Token it gives here. WeCom then tells Aiku the moment new messages are archived, instead of Aiku checking every minute.', ['url' => route('webhooks.wecom_archive', $organisation->slug)]),
                    'value'                 => '',
                    'use_generate_password' => false,
                ],
                'wechat_encoding_aes_key' => [
                    'type'                  => 'password',
                    'label'                 => __('EncodingAESKey'),
                    'placeholder'           => $this->secretPlaceholder($wechat, 'encoding_aes_key'),
                    'information'           => __('Given next to the callback token, 43 letters and digits. Used to read what WeCom sends to the callback URL.'),
                    'value'                 => '',
                    'use_generate_password' => false,
                ],
            ],
        ];
    }

    /**
     * Secrets are stored encrypted and never sent back to the browser, so the placeholder is
     * the only sign one is saved; typing a new value replaces it.
     *
     * @param  array<string, mixed>  $wechat
     */
    private function secretPlaceholder(array $wechat, string $key, string $emptyPlaceholder = ''): string
    {
        return filled(Arr::get($wechat, $key)) ? __('Saved, type a new one to replace it') : $emptyPlaceholder;
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
