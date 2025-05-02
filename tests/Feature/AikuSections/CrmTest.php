<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Nov 2024 15:22:46 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Comms\Mailshot\StoreMailshot;
use App\Actions\CRM\BackInStockReminder\DeleteBackInStockReminder;
use App\Actions\CRM\BackInStockReminder\StoreBackInStockReminder;
use App\Actions\CRM\BackInStockReminder\UpdateBackInStockReminder;
use App\Actions\CRM\Customer\AddDeliveryAddressToCustomer;
use App\Actions\CRM\Customer\DeleteCustomerDeliveryAddress;
use App\Actions\CRM\Customer\HydrateCustomers;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBasket;
use App\Actions\CRM\Customer\Search\ReindexCustomerSearch;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\Customer\UpdateBalanceCustomer;
use App\Actions\CRM\CustomerNote\StoreCustomerNote;
use App\Actions\CRM\CustomerNote\UpdateCustomerNote;
use App\Actions\CRM\Favourite\StoreFavourite;
use App\Actions\CRM\Favourite\UnFavourite;
use App\Actions\CRM\Favourite\UpdateFavourite;
use App\Actions\CRM\Poll\StorePoll;
use App\Actions\CRM\Poll\UpdatePoll;
use App\Actions\CRM\PollOption\StorePollOption;
use App\Actions\CRM\PollOption\UpdatePollOption;
use App\Actions\CRM\PollReply\StorePollReply;
use App\Actions\CRM\PollReply\UpdatePollReply;
use App\Actions\CRM\Prospect\Search\ReindexProspectSearch;
use App\Actions\CRM\Prospect\StoreProspect;
use App\Actions\CRM\Prospect\Tags\SyncTagsProspect;
use App\Actions\CRM\Prospect\UpdateProspect;
use App\Actions\CRM\Prospect\UpdateProspectEmailClicked;
use App\Actions\CRM\Prospect\UpdateProspectEmailHardBounced;
use App\Actions\CRM\Prospect\UpdateProspectEmailOpened;
use App\Actions\CRM\Prospect\UpdateProspectEmailSent;
use App\Actions\CRM\Prospect\UpdateProspectEmailSoftBounced;
use App\Actions\CRM\Prospect\UpdateProspectEmailUndoUnsubscribed;
use App\Actions\CRM\Prospect\UpdateProspectEmailUnsubscribed;
use App\Actions\CRM\WebUser\HydrateWebUser;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Web\Website\StoreWebsite;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\CRM\BackInStockReminder;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerNote;
use App\Models\CRM\Favourite;
use App\Models\CRM\Poll;
use App\Models\CRM\PollOption;
use App\Models\CRM\PollReply;
use App\Models\CRM\Prospect;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Country;
use App\Models\Helpers\Query;
use App\Models\Ordering\Order;
use App\Models\Web\Website;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(
    /**
     * @throws \Throwable
     */
    function () {
        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createShop();

        list(
            $this->tradeUnit,
            $this->product
        ) = createProduct($this->shop);

        $this->shop = UpdateShop::make()->action($this->shop, ['state' => ShopStateEnum::OPEN]);


        $website = Website::first();
        if (!$website) {
            $storeData = Website::factory()->definition();

            $website = StoreWebsite::make()->action(
                $this->shop,
                $storeData
            );
        }
        $this->website = $website;


        Config::set(
            'inertia.testing.page_paths',
            [resource_path('js/Pages/Grp')]
        );
        actingAs($this->user);
    }
);


test('create customer', function () {
    $customer = StoreCustomer::make()->action(
        $this->shop,
        Customer::factory()->definition(),
    );
    $customer->refresh();

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->reference)->toBe('000001')
        ->and($customer->status)->toBe(CustomerStatusEnum::APPROVED)
        ->and($customer->group->crmStats->number_customers)->toBe(1)
        ->and($customer->organisation->crmStats->number_customers)->toBe(1)
        ->and($customer->shop->crmStats->number_customers)->toBe(1);

    return $customer;
});

test('create other customer', function () {
    $customer = StoreCustomer::make()->action(
        $this->shop,
        Customer::factory()->definition()
    );

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->reference)->toBe('000002')
        ->and($customer->status)->toBe(CustomerStatusEnum::APPROVED);

    return $customer;
});

test('create web user', function (Customer $customer) {
    $webUser = StoreWebUser::make()->action(
        $customer,
        [
            'email'    => 'example@mail.com',
            'username' => 'example',
            'password' => 'password',
        ]
    );


    expect($webUser)->toBeInstanceOf(WebUser::class);

    return $customer;
})->depends('create customer');


test('prospect queries are seeded', function () {
    $this->artisan('query:seed-prospects')->assertExitCode(0);
    expect(Query::where('model', 'Prospect')->count())->toBe(2);
});

test('create prospect', function () {
    $shop      = $this->shop;
    $modelData = Prospect::factory()->definition();
    data_set($modelData, 'tags', ['seo', 'tag 1', ' hello ', 'Seo']);
    $prospect = StoreProspect::make()->action($shop, $modelData);

    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($shop->crmStats->number_prospects)->toBe(1)
        ->and($shop->crmStats->number_prospects_state_no_contacted)->toBe(1)
        ->and($shop->crmStats->number_prospects_state_contacted)->toBe(0)
        ->and($shop->crmStats->number_prospects_state_fail)->toBe(0)
        ->and($shop->crmStats->number_prospects_state_success)->toBe(0)
        ->and($this->organisation->crmStats->number_prospects)->toBe(1)
        ->and($this->organisation->crmStats->number_prospects_state_no_contacted)->toBe(1)
        ->and($this->organisation->crmStats->number_prospects_state_contacted)->toBe(0)
        ->and($this->organisation->crmStats->number_prospects_state_fail)->toBe(0)
        ->and($this->organisation->crmStats->number_prospects_state_success)->toBe(0);

    $this->assertDatabaseCount('tags', 3);

    return $prospect;
});

test('update prospect', function () {
    /** @var Prospect $prospect */
    $prospect  = Prospect::first();
    $modelData = [
        'contact_name' => 'new name',
    ];
    $prospect  = UpdateProspect::make()->action(prospect: $prospect, modelData: $modelData);
    expect($prospect)->toBeInstanceOf(Prospect::class)->and($prospect->contact_name)->toBe('new name');

    return $prospect;
});

test('create 2nd prospect', function () {
    $shop         = $this->shop;
    $organisation = $this->organisation;
    $modelData    = Prospect::factory()->definition();
    $prospect     = StoreProspect::make()->action($shop, $modelData);
    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($shop->crmStats->number_prospects)->toBe(2)
        ->and($shop->crmStats->number_prospects_state_no_contacted)->toBe(2)
        ->and($shop->crmStats->number_prospects_state_contacted)->toBe(0)
        ->and($shop->crmStats->number_prospects_state_fail)->toBe(0)
        ->and($shop->crmStats->number_prospects_state_success)->toBe(0)
        ->and($organisation->crmStats->number_prospects)->toBe(2)
        ->and($organisation->crmStats->number_prospects_state_no_contacted)->toBe(2)
        ->and($organisation->crmStats->number_prospects_state_contacted)->toBe(0)
        ->and($organisation->crmStats->number_prospects_state_fail)->toBe(0)
        ->and($organisation->crmStats->number_prospects_state_success)->toBe(0);

    return $prospect;
});

test('update prospect tags', function ($prospect) {
    $modelData = [
        'tags' => ['seo', 'social'],
    ];
    $prospect  = SyncTagsProspect::make()->action(prospect: $prospect, modelData: $modelData);
    expect($prospect)->toBeInstanceOf(Prospect::class)->and($prospect->tags->count())->toBe(2);

    return $prospect;
})->depends('create 2nd prospect');

test('prospect query count', function () {
    $this->artisan('query:count')->assertExitCode(0);

    $query  = Query::first();
    $query2 = Query::skip(1)->first();

    expect($query->number_items)->toBe(2)
        ->and($query2->number_items)->toBe(2);
});

test('create prospect mailshot', function () {
    $shop   = $this->shop;
    $outbox = Outbox::where('shop_id', $shop->id)->where('code', OutboxCodeEnum::INVITE)->first();
    expect($outbox)->toBeInstanceOf(Outbox::class);
    $dataModel = [
        'subject'           => 'hello',
        'type'              => MailshotTypeEnum::INVITE,
        'state'             => MailshotStateEnum::IN_PROCESS,
        'recipients_recipe' => []

    ];
    $mailshot  = StoreMailshot::make()->action($outbox, $dataModel);
    expect($mailshot)->toBeInstanceOf(Mailshot::class)
        ->and($outbox->intervals->runs_all)->toBe(1);
});

test('add delivery address to customer', function (Customer $customer) {
    $country  = Country::latest()->first();
    $customer = AddDeliveryAddressToCustomer::make()->action(
        $customer,
        [
            'delivery_address' => [
                'address_line_1'      => fake()->streetAddress,
                'address_line_2'      => fake()->buildingNumber,
                'sorting_code'        => '',
                'postal_code'         => fake()->postcode,
                'locality'            => fake()->city,
                'dependent_locality'  => '',
                'administrative_area' => '',
                'country_id'          => $country->id
            ]
        ]
    );

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->addresses->count())->toBe(2);

    return $customer;
})->depends('create customer');

test('remove delivery address from customer', function (Customer $customer) {
    $address  = $customer->addresses()->skip(1)->first();
    $customer = DeleteCustomerDeliveryAddress::make()->action($customer, $address);

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->addresses->count())->toBe(1);

    return $customer;
})->depends('add delivery address to customer');

test('can show list of prospects lists', function () {
    $shop     = $this->shop;
    $response = get(route('grp.org.shops.show.crm.prospects.lists.index', [$shop->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CRM/Prospects/Queries')
            ->has('title');
    });
})->todo();

test('add favourite to customer', function (Customer $customer) {
    $favourite = StoreFavourite::make()->action(
        $customer,
        $this->product,
        []
    );

    $customer->refresh();
    $this->product->refresh();
    expect($favourite)->toBeInstanceOf(Favourite::class)
        ->and($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->favourites)->not->toBeNull()
        ->and($customer->favourites->count())->toBe(1)
        ->and($customer->stats->number_favourites)->toBe(1)
        ->and($customer->stats->number_unfavourited)->toBe(0)
        ->and($this->product->stats->number_customers_who_favourited)->toBe(1)
        ->and($this->product->stats->number_customers_who_un_favourited)->toBe(0);

    return $favourite;
})->depends('create customer');

test('update favourite', function (Favourite $favourite) {
    $targetDate = Carbon::now()->addDays(2)->startOfMinute();

    $updatedFavourite = UpdateFavourite::make()->action(
        favourite: $favourite,
        modelData: [
            'last_fetched_at' => $targetDate
        ],
        strict: false
    );

    $updatedFavourite->refresh();

    expect($updatedFavourite)->toBeInstanceOf(Favourite::class)
        ->and($updatedFavourite->last_fetched_at->toDateString())->toEqual($targetDate->toDateString());

    return $updatedFavourite;
})->depends('add favourite to customer');

test('un-favourite', function (Favourite $favourite) {
    $customer   = $favourite->customer;
    $product    = $favourite->product;
    $unFavorite = UnFavourite::make()->action(
        $favourite,
        [
            'unfavourited_at' => now()
        ]
    );

    $customer->refresh();
    $product->refresh();
    expect($unFavorite)->unfavourited_at->not->toBeNull()
        ->and($customer->stats->number_favourites)->toBe(0)
        ->and($customer->stats->number_unfavourited)->toBe(1)
        ->and($this->product->stats->number_customers_who_favourited)->toBe(0)
        ->and($this->product->stats->number_customers_who_un_favourited)->toBe(1);

})->depends('add favourite to customer');


test('add back in stock reminder to customer', function (Customer $customer) {
    $reminder = StoreBackInStockReminder::make()->action(
        $customer,
        $this->product,
        []
    );

    $customer->refresh();

    expect($reminder)->toBeInstanceOf(BackInStockReminder::class)
        ->and($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->backInStockReminder)->not->toBeNull()
        ->and($customer->backInStockReminder->count())->toBe(1);

    return $reminder;
})->depends('create customer');

test('update back in stock reminder', function (BackInStockReminder $reminder) {
    $targetDate = Carbon::now()->addDays(2)->startOfMinute();

    $updatedReminder = UpdateBackInStockReminder::make()->action(
        backInStockReminder: $reminder,
        modelData: [
            'last_fetched_at' => $targetDate
        ],
        strict: false
    );

    $updatedReminder->refresh();

    expect($updatedReminder)->toBeInstanceOf(BackInStockReminder::class);

    // ->and($updatedFavourite->last_fetched_at)->toEqual($targetDate); //:(

    return $updatedReminder;
})->depends('add back in stock reminder to customer');

test('delete back in stock reminder', function (BackInStockReminder $reminder) {
    $deletedReminder = DeleteBackInStockReminder::make()->action(
        backInStockReminder: $reminder,
    );

    expect(BackInStockReminder::find($deletedReminder->id))->toBeNull();

    return $deletedReminder;
})->depends('update back in stock reminder');

test('create customer note', function (Customer $customer) {
    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->customerNotes)->not->toBeNull()
        ->and($customer->customerNotes->count())->toBe(2);

    $note = StoreCustomerNote::make()->action(
        $customer,
        [
            'note' => 'note A1234'
        ]
    );

    $customer->refresh();

    expect($note)->toBeInstanceOf(CustomerNote::class)
        ->and($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->customerNotes)->not->toBeNull()
        ->and($customer->customerNotes->count())->toBe(3);

    return $note;
})->depends('create customer');

test('update customer note', function (CustomerNote $note) {
    $updatedNote = UpdateCustomerNote::make()->action(
        $note,
        [
            'note' => 'note A1234 update'
        ]
    );

    $updatedNote->refresh();

    expect($updatedNote)->toBeInstanceOf(CustomerNote::class);

    return $updatedNote;
})->depends('create customer note');

test('hydrate customers', function (Customer $customer) {
    HydrateCustomers::run($customer);
    $this->artisan('hydrate:customers')->assertExitCode(0);
})->depends('create customer');

test('hydrate web user', function (Customer $customer) {
    $webUser = $customer->webUsers->first();
    HydrateWebUser::run($webUser);
    $this->artisan('hydrate:web_users')->assertExitCode(0);
})->depends('create web user');

test('store poll', function () {
    $poll = StorePoll::make()->action(
        $this->shop,
        [
            'name'                     => 'name',
            'label'                    => 'poll label',
            'in_registration'          => false,
            'in_registration_required' => true,
            'in_iris'                  => true,
            'in_iris_required'         => true,
            'type'                     => PollTypeEnum::OPTION
        ]
    );

    $poll->refresh();

    expect($poll)->toBeInstanceOf(Poll::class)
        ->and($poll->name)->toBe('name')
        ->and($poll->label)->toBe('poll label')
        ->and($poll->in_registration)->toBeFalse()
        ->and($poll->in_registration_required)->toBeTrue()
        ->and($poll->in_iris)->toBeTrue()
        ->and($poll->in_iris_required)->toBeTrue()
        ->and($poll->type)->toBe(PollTypeEnum::OPTION);

    return $poll;
});

test('update poll', function (Poll $poll) {
    $poll = UpdatePoll::make()->action(
        $poll,
        [
            'name'                     => 'option poll B',
            'label'                    => 'some option',
            'in_registration'          => true,
            'in_registration_required' => false,
            'in_iris'                  => false,
            'in_iris_required'         => false,
        ]
    );

    $poll->refresh();

    expect($poll)->toBeInstanceOf(Poll::class)
        ->and($poll->name)->toBe('option poll B')
        ->and($poll->label)->toBe('some option')
        ->and($poll->in_registration)->toBeTrue()
        ->and($poll->in_registration_required)->toBeFalse()
        ->and($poll->in_iris)->toBeFalse()
        ->and($poll->in_iris_required)->toBeFalse();

    return $poll;
})->depends('store poll');

test('store poll option', function (Poll $poll) {
    $pollOption = StorePollOption::make()->action(
        $poll,
        [
            'value' => 'value1',
            'label' => '1',
        ]
    );

    $pollOption->refresh();
    $poll->refresh();

    expect($pollOption)->toBeInstanceOf(PollOption::class)
        ->and($pollOption->value)->toBe('value1')
        ->and($pollOption->label)->toBe('1')
        ->and($poll)->toBeInstanceOf(Poll::class)
        ->and($poll->pollOptions->count())->toBe(1);

    return $pollOption;
})->depends('update poll');

test('update poll option', function (PollOption $pollOption) {
    $pollOption = UpdatePollOption::make()->action(
        $pollOption,
        [
            'value' => 'value1x',
            'label' => '1x',
        ]
    );

    $pollOption->refresh();

    expect($pollOption)->toBeInstanceOf(PollOption::class)
        ->and($pollOption->value)->toBe('value1x')
        ->and($pollOption->label)->toBe('1x');

    return $pollOption;
})->depends('store poll option');

test('store open question poll', function () {
    $poll = StorePoll::make()->action(
        $this->shop,
        [
            'name'                     => 'open question',
            'label'                    => 'open label',
            'in_registration'          => false,
            'in_registration_required' => true,
            'in_iris'                  => true,
            'in_iris_required'         => true,
            'type'                     => PollTypeEnum::OPEN_QUESTION
        ]
    );

    $poll->refresh();

    expect($poll)->toBeInstanceOf(Poll::class)
        ->and($poll->name)->toBe('open question')
        ->and($poll->label)->toBe('open label')
        ->and($poll->in_registration)->toBeFalse()
        ->and($poll->in_registration_required)->toBeTrue()
        ->and($poll->in_iris)->toBeTrue()
        ->and($poll->in_iris_required)->toBeTrue()
        ->and($poll->type)->toBe(PollTypeEnum::OPEN_QUESTION);

    return $poll;
});

test('store open question poll reply', function (Customer $customer, Poll $poll) {
    $pollReply = StorePollReply::make()->action(
        $poll,
        [
            'customer_id' => $customer->id,
            'value'       => 'Something'
        ]
    );

    $pollReply->refresh();
    $poll->refresh();

    expect($poll)->toBeInstanceOf(Poll::class)
        ->and($poll->pollReplies->count())->toBe(1)
        ->and($pollReply)->toBeInstanceOf(PollReply::class)
        ->and($pollReply->value)->toBe('Something');

    return $pollReply;
})->depends('create customer', 'store open question poll');

test('store option poll reply', function (Customer $customer, PollOption $pollOption) {
    $poll = $pollOption->poll;

    $pollReply = StorePollReply::make()->action(
        $poll,
        [
            'customer_id'    => $customer->id,
            'poll_option_id' => $pollOption->id
        ]
    );

    $pollReply->refresh();
    $poll->refresh();

    expect($poll)->toBeInstanceOf(Poll::class)
        ->and($poll->pollReplies->count())->toBe(1)
        ->and($pollReply)->toBeInstanceOf(PollReply::class)
        ->and($pollReply->poll_option_id)->toBe($pollOption->id);

    return $pollReply;
})->depends('create customer', 'update poll option');

test('update open question poll reply', function (PollReply $pollReply) {
    $pollReply = UpdatePollReply::make()->action(
        $pollReply,
        [
            'value' => 'Something 1'
        ]
    );

    $pollReply->refresh();

    expect($pollReply)->toBeInstanceOf(PollReply::class)
        ->and($pollReply->value)->toBe('Something 1');

    return $pollReply;
})->depends('store open question poll reply');

test('UI Index customers', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.shops.show.crm.customers.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Shop/CRM/Customers')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'customers')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI create customer', function () {
    $response = get(route('grp.org.shops.show.crm.customers.create', [$this->organisation->slug, $this->shop->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 4);
    });
});

test('UI show customer', function () {
    $customer = Customer::first();
    $response = get(route('grp.org.shops.show.crm.customers.show', [$this->organisation->slug, $this->shop->slug, $customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($customer) {
        $page
            ->component('Org/Shop/CRM/Customer')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $customer->name)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI edit customer', function () {
    $customer = Customer::first();
    $response = get(route('grp.org.shops.show.crm.customers.edit', [$this->organisation->slug, $this->shop->slug, $customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($customer) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                    ->where('name', 'grp.models.customer.update')
                    ->where('parameters', [$customer->id])
            )
            ->has('breadcrumbs', 3);
    });
});


test('UI Index customer web users', function () {
    $customer = Customer::first();
    $response = $this->get(route('grp.org.shops.show.crm.customers.show.web-users.index', [$this->organisation->slug, $this->shop->slug, $customer->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($customer) {
        $page
            ->component('Org/Shop/CRM/WebUsers')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $customer->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI Create customer web users', function () {
    $customer = Customer::first();
    $response = $this->get(route('grp.org.shops.show.crm.customers.show.web-users.create', [$this->organisation->slug, $this->shop->slug, $customer->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Create web user')
                    ->etc()
            )
            ->has('formData');
    });
});

test('UI show customer web users', function () {
    $webUser = WebUser::first();

    $response = $this->get(route('grp.org.shops.show.crm.customers.show.web-users.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $webUser->customer->slug,
        $webUser->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) use ($webUser) {
        $page
            ->component('Org/Shop/CRM/WebUser')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $webUser->username)
                    ->etc()
            )
            ->has('data');
    });
});

test('UI Edit customer web users', function () {
    $webUser  = WebUser::first();
    $response = $this->get(route('grp.org.shops.show.crm.customers.show.web-users.edit', [
        $this->organisation->slug,
        $this->shop->slug,
        $webUser->customer->slug,
        $webUser->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Edit web user')
                    ->etc()
            )
            ->has('formData');
    });
});

test('UI Index customer orders', function () {
    /** @var Customer $customer */
    $customer = Customer::first();

    StoreOrder::make()->action(
        $customer,
        []
    );

    $response = $this->get(route('grp.org.shops.show.crm.customers.show.orders.index', [$this->organisation->slug, $this->shop->slug, $customer->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($customer) {
        $page
            ->component('Ordering/Orders')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $customer->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI show order', function () {
    $this->withoutExceptionHandling();
    $order    = Order::first();
    $customer = $order->customer;
    $response = $this->get(route(
        'grp.org.shops.show.crm.customers.show.orders.show',
        [
            $this->organisation->slug,
            $this->shop->slug,
            $customer->slug,
            $order->slug
        ]
    ));

    $response->assertInertia(function (AssertableInertia $page) use ($order) {
        $page
            ->component('Org/Ordering/Order')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $order->reference)
                    ->etc()
            )
            ->has('data');
    });
});

test('can show list of mailshots', function () {
    $shop         = $this->shop;
    $organisation = $this->organisation;
    $response     = get(route('grp.org.shops.show.marketing.mailshots.index', [$organisation->slug, $shop->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Comms/Mailshots')
            ->has('title');
    });
});

test('can show list of prospects', function () {
    $shop         = $this->shop;
    $organisation = $this->organisation;
    $response     = get(route('grp.org.shops.show.crm.prospects.index', [$organisation->slug, $shop->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Shop/CRM/Prospects')
            ->has('title');
    });
});

test('can show list of tags', function () {
    $this->withoutExceptionHandling();
    $shop         = $this->shop;
    $organisation = $this->organisation;
    $response     = get(route('grp.org.shops.show.crm.prospects.tags.index', [$organisation->slug, $shop->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Shop/CRM/Tags')
            ->has('title');
    });
});

test('UI get section route crm dashboard', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.crm.customers.index', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_CRM->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});


test('UI get section route marketing mailshots index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.marketing.mailshots.index', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_MARKETING->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});


test('customers search', function () {
    $this->artisan('search:customers')->assertExitCode(0);

    $customers = Customer::first();
    ReindexCustomerSearch::run($customers);
    expect($customers->universalSearch()->count())->toBe(1);
});

test('prospects search', function () {
    $this->artisan('search:prospects')->assertExitCode(0);

    $prospects = Prospect::first();
    ReindexProspectSearch::run($prospects);
    expect($prospects->universalSearch()->count())->toBe(1);
});

test('create 3rd prospect (Email Clicked)', function () {
    $shop         = $this->shop;
    $modelData    = Prospect::factory()->definition();
    $prospect     = StoreProspect::make()->action($shop, $modelData);
    $prospect->refresh();
    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($shop->crmStats->number_prospects)->toBe(3);

    $prospect = UpdateProspectEmailClicked::make()->action($prospect, Carbon::now());

    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($prospect->state)->toBe(ProspectStateEnum::CONTACTED)
        ->and($prospect->contacted_state)->toBe(ProspectContactedStateEnum::CLICKED);

    return $prospect;
});

test('create 4th prospect (Email hard Bounced)', function () {
    $shop         = $this->shop;
    $modelData    = Prospect::factory()->definition();
    $prospect     = StoreProspect::make()->action($shop, $modelData);
    $prospect->refresh();
    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($shop->crmStats->number_prospects)->toBe(4);

    $prospect = UpdateProspectEmailHardBounced::make()->action($prospect, Carbon::now());

    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($prospect->state)->toBe(ProspectStateEnum::FAIL)
        ->and($prospect->fail_status)->toBe(ProspectFailStatusEnum::INVALID)
        ->and($prospect->contacted_state)->toBe(ProspectContactedStateEnum::NA);

    return $prospect;
});

test('create 5th prospect (Email Sent)', function () {
    $shop         = $this->shop;
    $modelData    = Prospect::factory()->definition();
    $prospect     = StoreProspect::make()->action($shop, $modelData);
    $prospect     = UpdateProspect::make()->action($prospect, [
        'contacted_state' => ProspectContactedStateEnum::NA
    ]);
    $prospect->refresh();
    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($shop->crmStats->number_prospects)->toBe(5);

    $prospect = UpdateProspectEmailSent::make()->action($prospect);

    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($prospect->state)->toBe(ProspectStateEnum::CONTACTED)
        ->and($prospect->contacted_state)->toBe(ProspectContactedStateEnum::NEVER_OPEN);

    return $prospect;
});

test('create 6th prospect (Email Opened)', function () {
    $shop         = $this->shop;
    $modelData    = Prospect::factory()->definition();
    $prospect     = StoreProspect::make()->action($shop, $modelData);
    $prospect     = UpdateProspect::make()->action($prospect, [
        'contacted_state' => ProspectContactedStateEnum::NA
    ]);
    $prospect->refresh();
    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($shop->crmStats->number_prospects)->toBe(6);

    $prospect = UpdateProspectEmailOpened::make()->action($prospect, Carbon::now());

    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($prospect->state)->toBe(ProspectStateEnum::CONTACTED)
        ->and($prospect->contacted_state)->toBe(ProspectContactedStateEnum::OPEN);

    return $prospect;
});

test('create 7th prospect (Email Soft Bounced)', function () {
    $shop         = $this->shop;
    $modelData    = Prospect::factory()->definition();
    $prospect     = StoreProspect::make()->action($shop, $modelData);
    $prospect     = UpdateProspect::make()->action($prospect, [
        'state'           => ProspectStateEnum::NO_CONTACTED,
        'contacted_state' => ProspectContactedStateEnum::NA
    ]);
    $prospect->refresh();
    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($shop->crmStats->number_prospects)->toBe(7);

    $prospect = UpdateProspectEmailSoftBounced::make()->action($prospect, Carbon::now());

    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($prospect->state)->toBe(ProspectStateEnum::CONTACTED)
        ->and($prospect->contacted_state)->toBe(ProspectContactedStateEnum::SOFT_BOUNCED);

    return $prospect;
});

test('create 8th prospect (Email Undo Unsubscribed)', function () {
    $shop         = $this->shop;
    $modelData    = Prospect::factory()->definition();
    $prospect     = StoreProspect::make()->action($shop, $modelData);
    $prospect->refresh();

    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($shop->crmStats->number_prospects)->toBe(8);

    $prospect = UpdateProspectEmailUndoUnsubscribed::make()->action($prospect);

    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($prospect->state)->toBe(ProspectStateEnum::NO_CONTACTED)
        ->and($prospect->contacted_state)->toBe(ProspectContactedStateEnum::NA)
        ->and($prospect->fail_status)->toBe(ProspectFailStatusEnum::NA);

    return $prospect;
});

test('create 9th prospect (Email Undo Unsubscribed - last_contacted not null)', function () {
    $shop         = $this->shop;
    $modelData    = Prospect::factory()->definition();
    $prospect     = StoreProspect::make()->action($shop, $modelData);
    $prospect     = UpdateProspect::make()->action($prospect, [
        'last_contacted_at'           => Carbon::now(),
    ]);
    $prospect->refresh();

    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($shop->crmStats->number_prospects)->toBe(9);

    $prospect = UpdateProspectEmailUndoUnsubscribed::make()->action($prospect);

    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($prospect->state)->toBe(ProspectStateEnum::CONTACTED)
        ->and($prospect->contacted_state)->toBe(ProspectContactedStateEnum::NEVER_OPEN)
        ->and($prospect->fail_status)->toBe(ProspectFailStatusEnum::NA);

    return $prospect;
});

test('create 10th prospect (Email Unsubscribed)', function () {
    $shop         = $this->shop;
    $modelData    = Prospect::factory()->definition();
    $prospect     = StoreProspect::make()->action($shop, $modelData);
    $prospect->refresh();

    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($shop->crmStats->number_prospects)->toBe(10);

    $prospect = UpdateProspectEmailUnsubscribed::make()->action($prospect, Carbon::now());

    expect($prospect)->toBeInstanceOf(Prospect::class)
        ->and($prospect->state)->toBe(ProspectStateEnum::FAIL)
        ->and($prospect->contacted_state)->toBe(ProspectContactedStateEnum::NA)
        ->and($prospect->fail_status)->toBe(ProspectFailStatusEnum::UNSUBSCRIBED);

    return $prospect;
});

test('UI Index polls', function () {
    $response = $this->get(route('grp.org.shops.show.crm.polls.index', [$this->organisation->slug, $this->shop->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Shop/CRM/Polls')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->shop->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI Show prospects', function (Prospect $prospect) {
    $response = $this->get(route('grp.org.shops.show.crm.prospects.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $prospect->slug
    ]));
    $response->assertInertia(function (AssertableInertia $page) use ($prospect) {
        $page
            ->component('Org/Shop/CRM/Prospect')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $prospect->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('tabs');
    });
})->depends('create prospect');

test('inventory  hydrator', function () {
    $this->artisan('hydrate -s crm')->assertExitCode(0);
});

// update balance
test('add balance customer', function (Customer $customer) {
    $creditTransaction = UpdateBalanceCustomer::make()->action(
        $customer,
        [
            'amount' => 100,
            'type'   => CreditTransactionTypeEnum::ADD_FUNDS_OTHER->value,
            'notes' => 'test',
        ]
    );
    $customer->refresh();

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and((int)$customer->balance)->toBe(100)
        ->and($creditTransaction)->toBeInstanceOf(CreditTransaction::class)
        ->and((int)$creditTransaction->amount)->toBe(100)
        ->and($creditTransaction->type)->toBe(CreditTransactionTypeEnum::ADD_FUNDS_OTHER)
        ->and($creditTransaction->notes)->toBe('test');

    return $customer;
})->depends('create customer');

test('withdraw balance customer', function (Customer $customer) {
    $creditTransaction = UpdateBalanceCustomer::make()->action(
        $customer,
        [
            'amount' => 100,
            'type'   => CreditTransactionTypeEnum::MONEY_BACK->value,
        ]
    );
    $customer->refresh();

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and((int)$customer->balance)->toBe(0)
        ->and($creditTransaction)->toBeInstanceOf(CreditTransaction::class)
        ->and((int)$creditTransaction->amount)->toBe(-100)
        ->and($creditTransaction->type)->toBe(CreditTransactionTypeEnum::MONEY_BACK);

    return $customer;
})->depends('add balance customer');

test('Customer basket hydrator', function () {
    $customer = Customer::first();
    CustomerHydrateBasket::run($customer);
    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->amount_in_basket)->toEqual(0)
        ->and($customer->current_order_in_basket_id)->toBeNull();
});
