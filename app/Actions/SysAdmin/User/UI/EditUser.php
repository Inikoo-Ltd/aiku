<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\UI\Traits\HasPermissionsForm;
use App\Actions\Traits\Authorisations\WithUserPermissionsEditAuthorisation;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditUser extends OrgAction
{
    use HasPermissionsForm;
    use WithUserPermissionsEditAuthorisation;

    public function handle(User $user): User
    {
        return $user;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($request->user()->authTo("sysadmin.view")) {
            return true;
        }

        return $request->route('employee') && $this->canEditUserAsHumanResources($request->user(), $request->route('user'));
    }

    public function asController(User $user, ActionRequest $request): User
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($user);
    }

    public function inEmployee(Organisation $organisation, Employee $employee, User $user, ActionRequest $request): User
    {
        abort_unless($employee->organisation_id === $organisation->id && $employee->users()->whereKey($user->id)->exists(), 404);

        $this->initialisation($organisation, $request);

        return $this->handle($user);
    }

    public function htmlResponse(User $user, ActionRequest $request): Response
    {
        $assistantHelp = $this->assistantHelp();

        $permissionsData = $this->getPermissionsFormData($user);

        return Inertia::render("EditModel", [
            "title"       => __("Editing user").' '.$user->username,
            "breadcrumbs" => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            "pageHead"    => [
                "title"   => $user->username,
                "icon"    => 'fal fa-user-circle',
                "model"   => __('User'),
                "actions" => [
                    [
                        "type"  => "button",
                        "style" => "exitEdit",
                        "route" => [
                            "name"       => preg_replace('/edit$/', "show", $request->route()->getName()),
                            "parameters" => array_values($request->route()->originalParameters()),
                        ],
                    ],
                ],
            ],


            "formData" => [
                "blueprint" => [
                    [
                        "label"   => __("Access"),
                        "title"   => __("access"),
                        "icon"    => "fal fa-door-closed",
                        "current" => true,
                        "fields"  => [
                            "status" => [
                                "type"        => "toggle",
                                "label"       => __("Can login"),
                                "value"       => $user->status,
                                ...($this->hasLeft($user) ? [
                                    "saveConfirmation" => [
                                        "title"       => __("Give the login back to somebody who is not working?"),
                                        "description" => __("This person is not working here any more. Say why they need to log in again; it is kept in their history."),
                                        "yesLabel"    => __("Yes, give it back"),
                                        "whenValueIs" => true,
                                        "reasonField" => "reason",
                                        "reasonLabel" => __("Reason"),
                                    ],
                                ] : []),
                            ],
                            "can_use_mcp" => [
                                "type"        => "toggle",
                                "icon"        => "fal fa-robot",
                                "label"       => __("Can connect AI assistant"),
                                "value"       => $user->can_use_mcp,
                            "help"             => $assistantHelp['can_use_mcp'],
                            ],
                            ...($user->can_use_mcp ? [
                                "can_use_mcp_sql" => [
                                    "type"             => "toggle",
                                    "label"            => __("Super intelligence")." 🧠",
                                    "value"            => $user->can_use_mcp_sql,
                                    "help"             => $assistantHelp['can_use_mcp_sql'],
                                    "noSaveButton"     => true,
                                    "submitOnConfirm"  => true,
                                    "warnOnEnableOnly" => true,
                                    "warnTitle"        => __("Give their personal AI super intelligence?"),
                                    "confirmLabel"     => __("Proceed at your own risk"),
                                    "warningTextHtml"  => __("Their own AI assistant will know <strong>everything in Aiku</strong> — sales, products, stock, customers, suppliers, staff and payments — across <strong>every shop and organisation</strong>, and can ask <strong>any question</strong> of the data. It can <strong>only read</strong>: it can never change or delete anything."),
                                    "warningBox"       => __("This bypasses permissions: their AI will see data this person cannot see in Aiku. Only for people you trust with all company data."),
                                ],
                                "can_use_mcp_discontinue" => [
                                    "type"             => "toggle",
                                    "boxedTitle"       => __("Through their AI assistant"),
                                    "boxedGroup"       => __("Goods"),
                                    "boxed"            => true,
                                    "compact"          => true,
                                    "icon"             => "fal fa-ban",
                                    "label"            => __("Can discontinue SKOs"),
                                    "value"            => $user->can_use_mcp_discontinue,
                                    "help"             => $assistantHelp['can_use_mcp_discontinue'],
                                    "noSaveButton"     => true,
                                    "submitOnConfirm"  => true,
                                    "warnOnEnableOnly" => true,
                                    "warnTitle"        => __("Let their AI assistant discontinue SKOs?"),
                                    "confirmLabel"     => __("Yes, they decide product status"),
                                    "warningTextHtml"  => __("Their AI assistant will be able to <strong>preview and confirm</strong> a SKO going to discontinuing, discontinued, suspended or back to active, <strong>across every organisation</strong> carrying the stock. Every change is audited with their name, the reason and the exact request text."),
                                    "warningBox"       => __("This is a write permission. Only for the people who decide product status."),
                                ],
                                "can_use_mcp_goods" => [
                                    "type"             => "toggle",
                                    "boxed"            => true,
                                    "compact"          => true,
                                    "icon"             => "fal fa-atom",
                                    "label"            => __("Can edit trade units"),
                                    "value"            => $user->can_use_mcp_goods,
                                    "help"             => $assistantHelp['can_use_mcp_goods'],
                                    "noSaveButton"     => true,
                                    "submitOnConfirm"  => true,
                                    "warnOnEnableOnly" => true,
                                    "warnTitle"        => __("Let their AI assistant edit trade units?"),
                                    "confirmLabel"     => __("Yes, they look after our product data"),
                                    "warningTextHtml"  => __("Their AI assistant will be able to <strong>change the properties of trade units</strong>: names, weights, ingredients, origin, customs and safety codes, which reach <strong>every product and organisation</strong> selling them. It can also <strong>give a barcode</strong> to a trade unit that has none, but never takes one from another item. It still needs the goods edit permission this person has in Aiku. Every change is kept in the AI changes log with their name and the exact request text. The conversations it reads are sent to the company behind their assistant."),
                                    "warningBox"       => __("This is a write permission. Only for the people who look after product data."),
                                ],
                                "can_use_mcp_prices" => [
                                    "type"             => "toggle",
                                    "boxedGroup"       => __("Catalogue"),
                                    "boxed"            => true,
                                    "compact"          => true,
                                    "icon"             => "fal fa-tags",
                                    "label"            => __("Can change master prices"),
                                    "value"            => $user->can_use_mcp_prices,
                                    "help"             => $assistantHelp['can_use_mcp_prices'],
                                    "noSaveButton"     => true,
                                    "submitOnConfirm"  => true,
                                    "warnOnEnableOnly" => true,
                                    "warnTitle"        => __("Let their AI assistant change prices?"),
                                    "confirmLabel"     => __("Yes, they set our prices"),
                                    "warningTextHtml"  => __("Their AI assistant will be able to <strong>save master prices</strong>, which <strong>every shop following the master</strong> then sells at, in every currency. It still needs the master edit permission this person has in Aiku. Every save is kept in the AI changes log with their name and the exact request text, and can be reverted there."),
                                    "warningBox"       => __("This is a write permission that changes what customers pay. Only for the people who set our prices."),
                                ],
                                "can_use_mcp_web" => [
                                    "type"             => "toggle",
                                    "boxed"            => true,
                                    "compact"          => true,
                                    "icon"             => "fal fa-globe",
                                    "label"            => __("Can change website content and redirects"),
                                    "value"            => $user->can_use_mcp_web,
                                    "help"             => $assistantHelp['can_use_mcp_web'],
                                    "noSaveButton"     => true,
                                    "submitOnConfirm"  => true,
                                    "warnOnEnableOnly" => true,
                                    "warnTitle"        => __("Let their AI assistant change website content?"),
                                    "confirmLabel"     => __("Yes, they look after the websites"),
                                    "warningTextHtml"  => __("Their AI assistant will be able to <strong>change the related products</strong> shown on family pages and <strong>point the redirects of closed pages to other live pages</strong>. When a shop follows the master catalogue the related products change is made on the <strong>master family</strong> and reaches <strong>every shop following it</strong>. It still needs the same product, master or website edit permissions this person has in Aiku."),
                                    "warningBox"       => __("This is a write permission. Only for the people who look after the websites."),
                                ],
                                "can_use_mcp_procurement" => [
                                    "type"             => "toggle",
                                    "boxedGroup"       => __("Procurement"),
                                    "boxed"            => true,
                                    "compact"          => true,
                                    "icon"             => "fal fa-clipboard-list",
                                    "label"            => __("Can add to the manufacturing hub shopping list"),
                                    "value"            => $user->can_use_mcp_procurement,
                                    "help"             => $assistantHelp['can_use_mcp_procurement'],
                                    "noSaveButton"     => true,
                                    "submitOnConfirm"  => true,
                                    "warnOnEnableOnly" => true,
                                    "warnTitle"        => __("Let their AI assistant fill the shopping list?"),
                                    "confirmLabel"     => __("Yes, they buy from the hub"),
                                    "warningTextHtml"  => __("Their AI assistant will be able to <strong>add SKOs and change quantities</strong> on the shopping list to the manufacturing hub, for the organisations where this person can edit procurement. The hub prepares and ships what is on the list."),
                                    "warningBox"       => __("This is a write permission. Only for the people who plan purchasing."),
                                ],
                                "can_use_mcp_place_orders" => [
                                    "type"             => "toggle",
                                    "boxed"            => true,
                                    "compact"          => true,
                                    "icon"             => "fal fa-paper-plane",
                                    "label"            => __("Can place orders to the manufacturing hub, partners and suppliers"),
                                    "value"            => $user->can_use_mcp_place_orders,
                                    "help"             => $assistantHelp['can_use_mcp_place_orders'],
                                    "noSaveButton"     => true,
                                    "submitOnConfirm"  => true,
                                    "warnOnEnableOnly" => true,
                                    "warnTitle"        => __("Let their AI assistant place orders?"),
                                    "confirmLabel"     => __("Yes, they place our orders"),
                                    "warningTextHtml"  => __("Their AI assistant will be able to <strong>submit the basket</strong> to the manufacturing hub, which then starts producing it, <strong>place rescue purchase orders</strong> with the other partners, which go straight to the partner's warehouse, and <strong>submit purchase orders to suppliers</strong>, including those bought through an agent. It needs the same procurement edit permission this person has in Aiku. Orders it places cannot be undone from the AI changes log."),
                                    "warningBox"       => __("This is a write permission that spends money. Only for the people who place our orders."),
                                ],
                                "can_use_mcp_costing" => [
                                    "type"             => "toggle",
                                    "boxed"            => true,
                                    "compact"          => true,
                                    "icon"             => "fal fa-box-usd",
                                    "label"            => __("Can save stock delivery costs"),
                                    "value"            => $user->can_use_mcp_costing,
                                    "help"             => $assistantHelp['can_use_mcp_costing'],
                                    "noSaveButton"     => true,
                                    "submitOnConfirm"  => true,
                                    "warnOnEnableOnly" => true,
                                    "warnTitle"        => __("Let their AI assistant cost stock deliveries?"),
                                    "confirmLabel"     => __("Yes, they cost our deliveries"),
                                    "warningTextHtml"  => __("Their AI assistant will be able to <strong>save the landed costs</strong> (shipping, duty and extra expenses) of stock deliveries, which decide <strong>the value of the stock put away</strong> and the margins. It still needs the procurement or accounting edit permission this person has in Aiku. Every save is kept in the AI changes log with their name and the exact request text, and can be reverted there until the delivery is costed."),
                                    "warningBox"       => __("This is a write permission that changes the value of our stock. Only for the people who cost our deliveries."),
                                ],
                                "can_use_mcp_locations" => [
                                    "type"             => "toggle",
                                    "boxedGroup"       => __("Warehouse"),
                                    "boxed"            => true,
                                    "compact"          => true,
                                    "icon"             => "fal fa-inventory",
                                    "label"            => __("Can put SKOs on locations"),
                                    "value"            => $user->can_use_mcp_locations,
                                    "help"             => $assistantHelp['can_use_mcp_locations'],
                                    "noSaveButton"     => true,
                                    "submitOnConfirm"  => true,
                                    "warnOnEnableOnly" => true,
                                    "warnTitle"        => __("Let their AI assistant put SKOs on locations?"),
                                    "confirmLabel"     => __("Yes, they look after where stock is kept"),
                                    "warningTextHtml"  => __("Their AI assistant will be able to <strong>add SKOs to warehouse locations</strong>, many at a time, after showing the list. It never moves stock, changes a quantity or takes a SKO off a location that holds stock. It still needs the stock edit permission this person has in Aiku. Every change is kept in the AI changes log with their name and the exact request text, and can be reverted there while the location is still empty."),
                                    "warningBox"       => __("This is a write permission. Only for the people who look after where stock is kept."),
                                ],
                                "can_use_mcp_production" => [
                                    "type"             => "toggle",
                                    "boxedGroup"       => __("Production"),
                                    "boxed"            => true,
                                    "compact"          => true,
                                    "icon"             => "fal fa-hammer",
                                    "label"            => __("Can set up artefacts, raw materials and recipes"),
                                    "value"            => $user->can_use_mcp_production,
                                    "help"             => $assistantHelp['can_use_mcp_production'],
                                    "noSaveButton"     => true,
                                    "submitOnConfirm"  => true,
                                    "warnOnEnableOnly" => true,
                                    "warnTitle"        => __("Let their AI assistant set up production?"),
                                    "confirmLabel"     => __("Yes, they design the products we make"),
                                    "warningTextHtml"  => __("Their AI assistant will be able to <strong>create and edit artefacts, raw materials and tasks</strong>, change raw material costs and <strong>replace the recipe steps</strong> of artefacts. It works in the productions where this person is a group or organisation admin, can edit R&D, or is a shop admin or shopkeeper in one of the organisation's shops. Job orders not yet received pick up the new steps."),
                                    "warningBox"       => __("This is a write permission. Only for the people who set up what production makes."),
                                ],
                                "can_use_mcp_customer_service" => [
                                    "type"             => "toggle",
                                    "boxedGroup"       => __("Customer service"),
                                    "boxed"            => true,
                                    "compact"          => true,
                                    "icon"             => "fal fa-headset",
                                    "label"            => __("Can serve customers"),
                                    "value"            => $user->can_use_mcp_customer_service,
                                    "help"             => $assistantHelp['can_use_mcp_customer_service'],
                                    "noSaveButton"     => true,
                                    "submitOnConfirm"  => true,
                                    "warnOnEnableOnly" => true,
                                    "warnTitle"        => __("Let their AI assistant serve customers?"),
                                    "confirmLabel"     => __("Yes, they look after our customers"),
                                    "warningTextHtml"  => __("Their AI assistant will be able to list the chats waiting on them, read a chat with the customer's details, orders and the shop's rules, and save draft replies on it for them to send. Given the ID of a chat, it will also be able to <strong>reply to the customer</strong> in their name, assign and close the chat, <strong>credit the customer's balance</strong> or send a replacement for a claim, and on the customer's orders <strong>change the delivery address, add notes and add, remove or change products</strong> until packing starts. It still needs the customer service and order permissions this person has in the shop. Every change is kept in the AI changes log with their name and the exact request text."),
                                    "warningBox"       => __("This is a write permission that writes to customers and gives them money. Only for customer service staff."),
                                ],
                            ] : []),
                        ],
                    ],
                    [
                        "label"   => __("Credentials"),
                        "title"   => __("id"),
                        "icon"    => "fal fa-key",
                        "current" => false,
                        "fields"  => [
                            "username" => [
                                "type"        => "input",
                                "label"       => __("Username"),
                                "placeholder" => "username",
                                "value"       => $user->username ?? '',
                            ],
                            "email"    => [
                                "type"        => "input",
                                "label"       => __("Email"),
                                "placeholder" => __("example@mail.com"),
                                "value"       => $user->email ?? '',
                            ],
                            "password" => [
                                "type"        => "password",
                                "label"       => __("Password"),
                                "placeholder" => "********",
                                "value"       => '',
                            ],
                        ],
                    ],
                    "permissions" => [
                        "label"   => __("Permissions"),
                        "title"   => __("Permissions"),
                        "icon"    => "fa-light fa-user-lock",
                        "current" => false,
                        "fields"  => [
                            "permissions" => $this->getPermissionsFieldDefinition($user, $permissionsData, $request->user()),
                        ],
                    ],


                ],
                "args"      => [
                    "updateRoute" => [
                        "name"       => "grp.models.user.update",
                        "parameters" => [$user->id],
                    ],
                ],
            ],
        ]);
    }

    /**
     * What each AI assistant switch lets the assistant do, shown behind the (?) beside the switch.
     *
     * @return array<string, array{title: string, items: array<int, string>, note: string}>
     */
    private function assistantHelp(): array
    {
        $confirms = __('Before changing anything it shows what will change and waits for their yes.');

        return [
            'can_use_mcp'                  => [
                'title' => __('What their AI assistant can do'),
                'items' => [
                    __('Connect an AI assistant to Aiku with their own login.'),
                    __('Answer questions about sales, products, stock, customers, orders, deliveries, marketing, web traffic, reviews and staff, only in the shops, organisations and warehouses this person can see in Aiku.'),
                    __('Raise help tickets and comment on the tickets they raised.'),
                    __('Ask a colleague or a department to do something (a staff task) and follow it up.'),
                    __('Send a Discord message to a colleague in their name.'),
                ],
                'note'  => __('It only reads, unless one of the switches below is on.'),
            ],
            'can_use_mcp_sql'              => [
                'title' => __('Super intelligence'),
                'items' => [
                    __('Ask any question of all the data in Aiku, in every shop and organisation, including data this person cannot see in Aiku.'),
                    __('Read the system records: errors, slow pages and background jobs, and the archived data.'),
                ],
                'note'  => __('It only reads: it can never change or delete anything.'),
            ],
            'can_use_mcp_discontinue'      => [
                'title' => __('Discontinue SKOs'),
                'items' => [
                    __('Show everything that still depends on a SKO: stock, days of cover, open purchase orders and deliveries, dropshipping customers, marketplace listings, live web pages and open orders.'),
                    __('Change a SKO to discontinuing, discontinued, suspended or back to active, in every organisation carrying it (or leave some organisations as they are), now or on a future date.'),
                    __('Stop ordering a SKO from its supplier, for this organisation or for every organisation, or make it orderable again.'),
                    __('Every change needs a reason, is kept in the AI changes list with their name and request, and can be undone there.'),
                ],
                'note'  => $confirms,
            ],
            'can_use_mcp_goods'            => [
                'title' => __('Edit trade units'),
                'items' => [
                    __('Change the name, description, weights, ingredients, country of origin, customs codes and safety codes of a trade unit. The change reaches every product and organisation selling it.'),
                    __('Give a barcode to a trade unit that has none. It never moves or removes a barcode.'),
                    __('Every change is kept in the AI changes list with their name and request, and can be undone there.'),
                    __('Only where this person can edit goods in Aiku.'),
                ],
                'note'  => $confirms,
            ],
            'can_use_mcp_prices'           => [
                'title' => __('Change master prices'),
                'items' => [
                    __('Show the master prices of a family or of products, with cost, units per outer and recent changes by hand.'),
                    __('Save new master prices in the main currencies. The other currencies follow the exchange rate, except prices set by hand.'),
                    __('Every shop following the master sells at the new price.'),
                    __('A price that more than doubles or drops by more than 60% is refused unless they say it is meant.'),
                    __('Every change is kept in the AI changes list with their name and request, and can be undone there.'),
                    __('Only where this person can edit the master catalogue in Aiku.'),
                ],
                'note'  => $confirms,
            ],
            'can_use_mcp_web'              => [
                'title' => __('Change website content and redirects'),
                'items' => [
                    __('Show and replace the related products ("Sells well with") of a family. When the shop follows the master catalogue the change is made on the master family and reaches every shop following it.'),
                    __('Point the redirects of closed pages to another live page of the same website, up to 25 at a time, after showing each old address with where it goes now and where it will go.'),
                    __('Every change is kept in the AI changes list with their name and request, and can be undone there.'),
                    __('Only where this person can edit products, the master catalogue or the website in Aiku.'),
                ],
                'note'  => $confirms,
            ],
            'can_use_mcp_procurement'      => [
                'title' => __('Add to the manufacturing hub shopping list'),
                'items' => [
                    __('Show what to order from the hub: stock, sales, days until it runs out, the recommended quantity and the batch size.'),
                    __('Add SKOs and change quantities on the shopping list as drafts. Quantities are raised to whole production batches.'),
                    __('The hub only sees the list once someone sends it.'),
                    __('Only for the organisations where this person can edit procurement in Aiku.'),
                ],
                'note'  => $confirms,
            ],
            'can_use_mcp_place_orders'     => [
                'title' => __('Place orders'),
                'items' => [
                    __('Send the shopping list to the manufacturing hub, which starts producing it, and change lines the hub has not started yet.'),
                    __('Place rescue orders with the other partners, whose warehouse starts picking at once.'),
                    __('Place purchase orders with suppliers, including those bought through an agent, rounded to whole cartons. They are not emailed: that is done from the purchase order page.'),
                    __('Only where this person can edit procurement in Aiku.'),
                ],
                'note'  => __('Orders spend money and cannot be undone from the AI changes list. Before placing one it shows the order and waits for their yes.'),
            ],
            'can_use_mcp_costing'          => [
                'title' => __('Save stock delivery costs'),
                'items' => [
                    __('Show the costs of a stock delivery and which are still missing.'),
                    __('Save shipping, duty and extra expenses with amount, currency and note, mark a cost as not applicable, or as waiting for its invoice with an estimate.'),
                    __('These costs decide the value of the stock put away and the margins.'),
                    __('Every change is kept in the AI changes list and can be undone there until the delivery is costed.'),
                ],
                'note'  => $confirms,
            ],
            'can_use_mcp_locations'        => [
                'title' => __('Put SKOs on locations'),
                'items' => [
                    __('Show the locations of SKOs.'),
                    __('Add SKOs to warehouse locations of the same organisation, many at a time, after showing what will be added and what is refused.'),
                    __('It never moves stock, changes a quantity or takes a SKO off a location.'),
                    __('Every change is kept in the AI changes list with their name and request, and can be undone there while the location is still empty.'),
                    __('Only where this person can edit stock in Aiku.'),
                ],
                'note'  => $confirms,
            ],
            'can_use_mcp_production'       => [
                'title' => __('Set up production'),
                'items' => [
                    __('Create and edit artefacts (with their SKO, or a new SKO made for them), raw materials with their cost, and manufacture tasks.'),
                    __('Replace the recipe of artefacts: the steps in order, units per artefact, target per hour and the raw materials each step uses, with the materials cost.'),
                    __('Job orders not yet received follow the new steps.'),
                    __('Set the factory location, and create work areas with the product departments made there and the artisans who work there (production managers only).'),
                    __('Every change is kept in the AI changes list with their name and request, and can be undone there.'),
                ],
                'note'  => $confirms,
            ],
            'can_use_mcp_customer_service' => [
                'title' => __('Serve customers'),
                'items' => [
                    __('List the chats waiting on them, and open a chat with the customer, their orders, the language to answer in and the shop\'s rules.'),
                    __('Write draft replies for them to check and send from Aiku.'),
                    __('Reply to the customer in their name, take, close or pause a chat, and link it to a customer.'),
                    __('Settle a claim: credit the customer\'s balance, send a replacement, or add the items free to the next order.'),
                    __('Change an order not yet packed: delivery address, notes, and add, remove or change products.'),
                    __('Only in the shops where this person works in customer service.'),
                ],
                'note'  => __('Drafts need no yes. Everything else is shown first and waits for their yes. What was sent or given to a customer cannot be undone from the AI changes list.'),
            ],
        ];
    }

    private function hasLeft(User $user): bool
    {
        return !$user->status && Employee::where('user_id', $user->id)
            ->whereIn('state', [EmployeeStateEnum::LEFT->value, EmployeeStateEnum::LEAVING->value])
            ->exists();
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowUser::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', "show", $routeName),
            routeParameters: $routeParameters,
            suffix: "(".__("editing").")"
        );
    }
}
