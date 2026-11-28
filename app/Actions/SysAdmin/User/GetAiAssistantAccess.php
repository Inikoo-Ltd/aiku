<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 13:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What a person's AI assistant can do in Aiku: the switches an administrator sets on the user,
 * described the same way on the user edit page and on the person's own profile.
 */
class GetAiAssistantAccess
{
    use AsObject;

    /**
     * @return array{connected: bool, docs_url: string, abilities: array<int, array{key: string, title: string, items: array<int, string>, note: string|null, examples: array<int, string>, writes: bool}>}
     */
    public function handle(User $user): array
    {
        $help     = self::help();
        $examples = self::examples();

        $switches = [
            'can_use_mcp'                  => $user->can_use_mcp,
            'can_use_mcp_sql'              => $user->can_use_mcp && $user->can_use_mcp_sql,
            'can_use_mcp_discontinue'      => $user->can_use_mcp && $user->can_use_mcp_discontinue,
            'can_use_mcp_goods'            => $user->can_use_mcp && $user->can_use_mcp_goods,
            'can_use_mcp_prices'           => $user->can_use_mcp && $user->can_use_mcp_prices,
            'can_use_mcp_web'              => $user->can_use_mcp && $user->can_use_mcp_web,
            'can_use_mcp_info_websites'    => $user->can_use_mcp && $user->can_use_mcp_info_websites,
            'can_use_mcp_procurement'      => $user->can_use_mcp && $user->can_use_mcp_procurement,
            'can_use_mcp_place_orders'     => $user->can_use_mcp && $user->can_use_mcp_place_orders,
            'can_use_mcp_costing'          => $user->can_use_mcp && $user->can_use_mcp_costing,
            'can_use_mcp_locations'        => $user->can_use_mcp && $user->can_use_mcp_locations,
            'can_use_mcp_production'       => $user->can_use_mcp && $user->can_use_mcp_production,
            'can_use_mcp_customer_service' => $user->can_use_mcp && $user->can_use_mcp_customer_service,
            'can_use_mcp_settings'         => $user->can_use_mcp && $user->can_use_mcp_settings,
        ];

        $abilities = [];
        foreach (array_keys(array_filter($switches)) as $key) {
            $abilities[] = [
                'key'      => $key,
                'title'    => $key === 'can_use_mcp' ? __('Read Aiku') : $help[$key]['title'],
                'items'    => $help[$key]['items'],
                'note'     => $key === 'can_use_mcp' ? null : $help[$key]['note'],
                'examples' => $examples[$key],
                'writes'   => !in_array($key, ['can_use_mcp', 'can_use_mcp_sql']),
            ];
        }

        return [
            'connected' => (bool) $user->can_use_mcp,
            'docs_url'  => route('aiku-public.docs.show', 'connecting-your-ai-assistant-to-aiku'),
            'abilities' => $abilities,
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function examples(): array
    {
        return [
            'can_use_mcp'                  => [
                __('How did my shop sell last week compared with the week before? Which families grew most and which fell?'),
                __('Which products sell well but have less than two weeks of stock left?'),
                __('Show me order AW-123456: where is it and when was it dispatched?'),
                __('Which offers brought the most orders last month, and which did nobody use?'),
                __('What are customers saying in their reviews this month? Summarise the complaints.'),
                __('Raise a ticket: the label printer in packing prints blank labels since this morning.'),
                __('Ask the warehouse to count location A-12-03 and tell me when it is done.'),
            ],
            'can_use_mcp_sql'              => [
                __('Across every organisation, which trade units sold more this quarter than the same quarter last year?'),
                __('Which pages were slowest yesterday and how many errors did we have?'),
            ],
            'can_use_mcp_discontinue'      => [
                __('What still depends on SKO ABC-01 if I discontinue it?'),
                __('Discontinue ABC-01 in every organisation except Spain, and stop ordering it from the supplier.'),
            ],
            'can_use_mcp_goods'            => [
                __('Show me the ingredients and the country of origin of trade unit ABC-01.'),
                __('Set the net weight of ABC-01 to 250 g and its customs code to 3307 41 00.'),
            ],
            'can_use_mcp_prices'           => [
                __('Show the master prices of the Incense Cones family with cost and margin.'),
                __('Raise every product in that family by 5% in pounds and euros.'),
            ],
            'can_use_mcp_web'              => [
                __('Which products are shown as "Sells well with" on the Incense Cones family page? Replace them with our three best sellers.'),
                __('The old Gift Sets page is closed: point its redirects to the Gifts department.'),
            ],
            'can_use_mcp_info_websites'    => [
                __('On aw.gifts, change the opening hours in the footer to 9:00 to 17:00.'),
                __('Add a page about our wholesale terms and show me a preview first.'),
            ],
            'can_use_mcp_procurement'      => [
                __('What should we order from the manufacturing hub this week? Start with what runs out first.'),
                __('Add the ten most urgent SKOs to the hub shopping list in whole batches.'),
            ],
            'can_use_mcp_place_orders'     => [
                __('Show me the hub basket and send it.'),
                __('Place a purchase order with our incense supplier for everything with less than four weeks of stock.'),
            ],
            'can_use_mcp_costing'          => [
                __('Which stock deliveries are still missing their costs?'),
                __('Delivery SD-1234: shipping was 820 euros and duty 310 pounds, save them.'),
            ],
            'can_use_mcp_locations'        => [
                __('Where is SKO ABC-01 kept?'),
                __('Put these twenty SKOs on location B-04-02.'),
            ],
            'can_use_mcp_production'       => [
                __('Show me the recipe of artefact ABC-01 and its materials cost.'),
                __('Add a labelling step after filling, 120 units per hour.'),
            ],
            'can_use_mcp_customer_service' => [
                __('Which chats are waiting for me? Open the oldest one.'),
                __('The customer says two mugs arrived broken: draft a reply asking for photos.'),
                __('Credit the customer 12 euros for the broken mugs and tell them.'),
            ],
            'can_use_mcp_settings'         => [
                __('Who gets Ask CRM questions in the German shop?'),
                __('Add the new customer service colleague to Ask CRM in every shop of the organisation.'),
            ],
        ];
    }

    public static function help(): array
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
                    __('Put master products on pre-order (back-order or made-to-order) and set their lead time, deposit and maximum per order, in every shop following the master.'),
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
            'can_use_mcp_info_websites'    => [
                'title' => __('Change info websites'),
                'items' => [
                    __('Read the pages and shared parts (header, footer) of the information-only websites.'),
                    __('Change, add or delete a page or shared part, after showing a preview to check.'),
                    __('Every change is kept in the AI changes list with their name and request, and can be undone there.'),
                    __('Only for group webmasters and administrators.'),
                ],
                'note'  => __('Nothing is saved until they have seen the preview and said yes.'),
            ],
            'can_use_mcp_settings'         => [
                'title' => __('Change who gets Ask CRM and Ask warehouse questions'),
                'items' => [
                    __('Show who receives Ask CRM and Ask warehouse questions, and their backups, in each shop and in the organisation.'),
                    __('Add or remove staff on those lists in one shop, or in every shop of an organisation at once.'),
                    __('Every change is kept in the AI changes list with their name and request, and can be undone there.'),
                    __('Only in the shops and organisations where this person is an admin in Aiku.'),
                ],
                'note'  => $confirms,
            ],
        ];
    }
}
