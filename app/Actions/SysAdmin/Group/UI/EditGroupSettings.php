<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jul 2024 19:36:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\UI;

use App\Actions\Helpers\TimeZone\Json\IndexTimeZones;
use App\Enums\Inventory\OrgStock\OrgStockValuationMethodEnum;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\UI\ShowSysAdminDashboard;
use App\Actions\SysAdmin\WithSysAdminAuthorization;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Support\Forms\SesConfigurationBlueprint;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;

class EditGroupSettings extends OrgAction
{
    use WithSysAdminAuthorization;

    public function handle(Group $group): Group
    {

        return $group;
    }


    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(group(), $request);
        return $this->handle($this->group);
    }


    public function htmlResponse(Group $group, ActionRequest $request): Response
    {

        return Inertia::render("EditModel", [
            "title"       => __("group"),
            "breadcrumbs" => $this->getBreadcrumbs(
                $request->route()->originalParameters()
            ),
            "pageHead" => [
                "title"   => $group->name,
                "actions" => [
                    [
                        "type"  => "button",
                        "style" => "exitEdit",
                        "route" => [
                            "name"       => "grp.sysadmin.dashboard",
                            "parameters" => [],
                        ],
                    ],
                ],
            ],


            "formData" => [
                "blueprint" => [
                    [
                        "label"   => __("Group Information"),
                        "title"   => __("id"),
                        "icon"    => "fa-light fa-user",
                        "current" => true,
                        "fields"  => [
                            "name" => [
                                "type"        => "input",
                                "label"       => __("name"),
                                "value"       => $group->name ?? '',
                            ],
                            "logo" => [
                                "type"  => "avatar",
                                "label" => __("logo"),
                                "value" => $group->imageSources(320, 320)
                            ],
                        ],
                    ],
                    [
                        'label'  => __('Languages'),
                        'icon'   => 'fa-light fa-language',
                        'fields' => [
                            'language_id' => [
                                'type'          => 'select',
                                'label'         => __('Main language'),
                                'placeholder'   => __('Select your language'),
                                'required'      => true,
                                'value'         => $group->language_id,
                                'options'       => GetLanguagesOptions::make()->all(),
                                'searchable'    => true
                            ],
                            'extra_languages' => [
                                'type'          => 'select',
                                'label'         => __('Extra language'),
                                'placeholder'   => __('Select your language'),
                                'required'      => true,
                                'value'         => $group->extra_languages,
                                'options'       => GetLanguagesOptions::make()->all(),
                                'searchable'    => true,
                                'mode'          => 'tags',
                                'labelProp'     => 'name',
                                 'valueProp' => 'id',
                            ]

                        ],
                    ],
                     [
                        'label'  => __('Clocks'),
                        'icon'   => 'fa-light fa-clock',
                        'fields' => [
                            'timezones' => [
                                'type'        => 'select_infinite',
                                'label'       => __('Timezones shown in the footer'),
                                'information' => __('These clocks are shown to everybody in the group'),
                                'options'     => IndexTimeZones::make()->optionsFor($group->world_clock_timezones),
                                'mode'        => 'multiple',
                                'fetchRoute'  => [
                                    'name' => 'grp.json.timezones',
                                ],
                                'valueProp'   => 'value',
                                'labelProp'   => 'label',
                                'required'    => false,
                                'value'       => $group->world_clock_timezones,
                            ],
                        ],
                    ],
                    [
                        'label'  => __('AWS-SES configuration'),
                        'icon'   => 'fa-light fa-key',
                        'fields' => SesConfigurationBlueprint::make(
                            $group->settings ?? [],
                            ['failover', 'customer_notification', 'user_notification']
                        ),
                    ],
                    [
                        'label'  => __('Staff chat'),
                        'icon'   => 'fal fa-comments',
                        'fields' => [
                            'staff_chat_quick_replies' => [
                                'type'        => 'textarea',
                                'label'       => __('Quick replies'),
                                'information' => __('One per line, shown as buttons in the staff chat composer'),
                                'value'       => implode("\n", Arr::get($group->settings, 'staff_chat.quick_replies', [])),
                            ],
                        ],
                    ],
                    [
                        'label'  => __('AI shopping assistant'),
                        'icon'   => 'fa-light fa-robot',
                        'fields' => [
                            'shopping_assistant_name' => [
                                'type'        => 'input',
                                'label'       => __('Assistant name'),
                                'information' => __('The name customers see on every website that has not set its own. Left empty: :name', ['name' => config('services.shopping_assistant.name')]),
                                'value'       => Arr::get($group->settings, 'shopping_assistant.name'),
                                'placeholder' => config('services.shopping_assistant.name'),
                                'required'    => false,
                            ],
                            'shopping_assistant_weekly_summary_user_ids' => [
                                'type'        => 'multiselect-tags',
                                'label'       => __('Weekly summary to'),
                                'information' => __('Emailed every Monday: conversations, orders, revenue, AI cost and the top customer challenges of the week before, with a link to the report. Only group administrators and group webmasters receive it. Left empty: nobody.'),
                                'options'     => User::where('group_id', $group->id)
                                    ->where('status', true)
                                    ->orderBy('contact_name')
                                    ->get(['id', 'contact_name', 'username', 'nickname'])
                                    ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->chatName()]),
                                'labelProp'   => 'name',
                                'valueProp'   => 'id',
                                'value'       => Arr::get($group->settings, 'shopping_assistant.weekly_summary_user_ids', []),
                            ],
                            'shopping_assistant_unmet_needs_user_ids' => [
                                'type'        => 'multiselect-tags',
                                'label'       => __('Products customers asked for, to'),
                                'information' => __('Emailed every Monday: what customers asked the assistant for last week that we do not sell or had out of stock, with how often and in which shops. For the purchasing team. Left empty: nobody.'),
                                'options'     => User::where('group_id', $group->id)
                                    ->where('status', true)
                                    ->orderBy('contact_name')
                                    ->get(['id', 'contact_name', 'username', 'nickname'])
                                    ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->chatName()]),
                                'labelProp'   => 'name',
                                'valueProp'   => 'id',
                                'value'       => Arr::get($group->settings, 'shopping_assistant.unmet_needs_user_ids', []),
                            ],
                            'shopping_assistant_unmet_needs_slack_channel' => [
                                'type'        => 'input',
                                'label'       => __('Products customers asked for, Slack channel'),
                                'information' => __('The same list is posted every Monday to this Slack channel (its id, e.g. C02TCK849NG). The aiku bot must be a member. Left empty: no Slack post.'),
                                'value'       => Arr::get($group->settings, 'shopping_assistant.unmet_needs_slack_channel'),
                                'required'    => false,
                            ],
                            'shopping_assistant_retention_months' => [
                                'type'        => 'input',
                                'label'       => __('Keep conversation texts for (months)'),
                                'information' => __('After this, what customers wrote and were told is wiped and only the figures stay. Anonymising a customer wipes theirs at once. Left empty: :months', ['months' => config('services.shopping_assistant.retention_months')]),
                                'value'       => Arr::get($group->settings, 'shopping_assistant.retention_months'),
                                'placeholder' => (string) config('services.shopping_assistant.retention_months'),
                                'required'    => false,
                            ],
                        ],
                    ],
                    [
                        'label'  => __('Printer'),
                        'icon'   => 'fa-light fa-print',
                        'fields' => [
                            "printnode_api_key" => [
                                "type"        => "purePassword",
                                "label"       => __("Printnode Apikey"),
                                "value"       => Arr::get($group->settings, 'printnode.apikey', ''),
                            ],
                            'print_by_printnode' => [
                                'type'  => 'toggle',
                                'label' => __('Print by Printnode'),
                                'value' => Arr::get($group->settings, 'printnode.print_by_printnode', false),
                            ],
                        ]
                    ],
                    [
                        'label'  => __('Inventory'),
                        'icon'   => 'fa-light fa-inventory',
                        'fields' => [
                            'official_stock_valuation_method' => [
                                'type'             => 'radio',
                                'mode'             => 'card',
                                'columns'          => 1,
                                'valueProp'        => 'value',
                                'label'            => __('Official stock valuation method'),
                                'information'      => __('Drives stock values, margins, product costs and dashboards. LPP is not an allowed valuation method in the UK.'),
                                'required'         => true,
                                'value'            => Arr::get($group->settings, 'inventory.official_valuation_method', OrgStockValuationMethodEnum::official()->value),
                                'options'          => [
                                    ...array_map(fn (OrgStockValuationMethodEnum $method) => [
                                        'value'       => $method->value,
                                        'title'       => $method->label().' ('.$method->fullName().')',
                                        'description' => $method->description(),
                                    ], [OrgStockValuationMethodEnum::FIFO, OrgStockValuationMethodEnum::WAC]),
                                ],
                                'saveConfirmation' => [
                                    'title'       => __('Change the official stock valuation method?'),
                                    'description' => __('This is a serious accounting decision. It must not be taken without prior discussion with all the accountants across the group (all organisations). Stock values, margins, product costs and dashboards will all change.'),
                                    'yesLabel'    => __('I understand, change it'),
                                ],
                            ],
                        ],
                    ],
                ],
                "args" => [
                    "updateRoute" => [
                        "name"       => "grp.models.group-settings.update",
                    ],
                ],
            ],
        ]);
    }

    public function getBreadcrumbs($suffix = null): array
    {
        return array_merge(
            ShowSysAdminDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.sysadmin.settings.edit',
                        ],
                        'label' => __('settings'),
                        'icon'  => 'fal fa-slide-h',
                    ],
                    'suffix' => $suffix

                ]
            ]
        );
    }
}
