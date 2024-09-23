<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Apr 2023 16:05:15 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Tests\Feature;

use App\Actions\Mail\DispatchedEmail\StoreDispatchEmail;
use App\Actions\Mail\DispatchedEmail\UpdateDispatchedEmail;
use App\Actions\Mail\Mailshot\StoreMailshot;
use App\Actions\Mail\Mailshot\UpdateMailshot;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Mail\Outbox\StoreOutbox;
use App\Actions\Web\Website\StoreWebsite;
use App\Enums\Mail\Outbox\OutboxBlueprintEnum;
use App\Enums\Mail\Outbox\OutboxTypeEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Mail\Mailshot;
use App\Models\Catalogue\Shop;
use App\Models\Mail\Outbox;
use App\Models\Web\Website;

beforeAll(function () {
    loadDB();
});


beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = $this->organisation->group;
});

test('post rooms seeded correctly', function () {

    $postRooms = $this->group->postRooms;
    expect($postRooms->count())->toBe(5)
        ->and($this->group->mailStats->number_post_rooms)->toBe(5);
});

test('seed organisation outboxes customers command', function () {

    $this->artisan('org:seed-outboxes '.$this->organisation->slug)->assertExitCode(0);
    $this->artisan('org:seed-outboxes')->assertExitCode(0);
    expect($this->group->mailStats->number_outboxes)->toBe(1)
        ->and($this->organisation->mailStats->number_outboxes)->toBe(1)
        ->and($this->organisation->mailStats->number_outboxes_type_test)->toBe(1)
        ->and($this->organisation->mailStats->number_outboxes_state_active)->toBe(1);
});

test('outbox seeded when shop created', function () {
    $shop   = StoreShop::make()->action($this->organisation, Shop::factory()->definition());
    expect($shop->group->mailStats->number_outboxes)->toBe(12)
        ->and($shop->organisation->mailStats->number_outboxes)->toBe(12)
        ->and($shop->mailStats->number_outboxes)->toBe(11);

    return $shop;

});

test('seed shop outboxes by command', function (Shop $shop) {
    $this->artisan('shop:seed-outboxes '.$shop->slug)->assertExitCode(0);
    $this->artisan('shop:seed-outboxes')->assertExitCode(0);
    expect($shop->group->mailStats->number_outboxes)->toBe(12);
})->depends('outbox seeded when shop created');

test('outbox seeded when website created', function (Shop $shop) {
    $website = StoreWebsite::make()->action(
        $shop,
        Website::factory()->definition()
    );
    expect($website->group->mailStats->number_outboxes)->toBe(22)
        ->and($website->organisation->mailStats->number_outboxes)->toBe(22)
        ->and($website->shop->mailStats->number_outboxes)->toBe(21);

    return $website;

})->depends('outbox seeded when shop created');


test('seed websites outboxes by command', function (Website $website) {
    $this->artisan('website:seed-outboxes '.$website->slug)->assertExitCode(0);
    $this->artisan('website:seed-outboxes')->assertExitCode(0);
    expect($website->group->mailStats->number_outboxes)->toBe(22);
})->depends('outbox seeded when website created');


test('outbox seeded when fulfilment created', function () {
    $fulfilment = createFulfilment($this->organisation);
    expect($fulfilment->group->mailStats->number_outboxes)->toBe(26)
        ->and($fulfilment->organisation->mailStats->number_outboxes)->toBe(26)
        ->and($fulfilment->shop->mailStats->number_outboxes)->toBe(4);

    return $fulfilment;

});

test('seed fulfilments outboxes by command', function (Fulfilment $fulfilment) {
    $this->artisan('fulfilment:seed-outboxes '.$fulfilment->slug)->assertExitCode(0);
    $this->artisan('fulfilment:seed-outboxes')->assertExitCode(0);
    expect($fulfilment->group->mailStats->number_outboxes)->toBe(26);
})->depends('outbox seeded when fulfilment created');


test('create mailshot', function (Shop $shop) {

    /** @var Outbox $outbox */
    $outbox = $shop->outboxes()->where('type', OutboxTypeEnum::MARKETING)->first();

    $mailshot = StoreMailshot::make()->action($outbox, Mailshot::factory()->definition());
    $this->assertModelExists($mailshot);

    return $mailshot;
})->depends('outbox seeded when shop created');

test('update mailshot', function ($mailshot) {
    $mailshot = UpdateMailshot::make()->action($mailshot, Mailshot::factory()->definition());
    $this->assertModelExists($mailshot);
    return $mailshot;
})->depends('create mailshot');

test('create dispatched email in outbox', function (Shop $shop) {
    /** @var Outbox $outbox */
    $outbox          = $shop->outboxes()->where('type', OutboxTypeEnum::MARKETING)->first();
    $dispatchedEmail = StoreDispatchEmail::make()->action(
        $outbox,
        fake()->email,
        []
    );
    $this->assertModelExists($dispatchedEmail);
})->depends('outbox seeded when shop created');

test('create dispatched email in mailshot', function ($mailshot) {
    $dispatchedEmail = StoreDispatchEmail::make()->action(
        $mailshot,
        fake()->email,
        []
    );
    $this->assertModelExists($dispatchedEmail);

    return $dispatchedEmail;
})->depends('create mailshot');


test('update dispatched email', function ($dispatchedEmail) {
    $updatedDispatchEmail = UpdateDispatchedEmail::make()->action(
        $dispatchedEmail,
        []
    );
    $this->assertModelExists($updatedDispatchEmail);
    return $updatedDispatchEmail;
})->depends('create dispatched email in mailshot');

test('test post room hydrator', function ($shop) {
    $postRoom = $this->group->postRooms()->first();

    $outbox = StoreOutbox::make()->action(
        $postRoom,
        $shop,
        [
            'type'      => OutboxTypeEnum::NEWSLETTER,
            'name'      => 'Test',
            'blueprint' => OutboxBlueprintEnum::EMAIL_TEMPLATE,
            'layout'    => []
        ]
    );

    expect($outbox)->toBeInstanceOf(Outbox::class)
        ->and($outbox->postRoom->stats->number_outboxes)->toBe(7)
        ->and($outbox->postRoom->stats->number_outboxes_type_newsletter)->toBe(3);

    return $outbox;


})->depends('outbox seeded when shop created');
