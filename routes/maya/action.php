<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 May 2024 20:27:04 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */


use App\Actions\Fulfilment\Pallet\ReturnPalletToCustomer;
use App\Actions\Fulfilment\Pallet\SetPalletAsDamaged;
use App\Actions\Fulfilment\Pallet\SetPalletAsLost;
use App\Actions\Fulfilment\Pallet\SetPalletAsNotReceived;
use App\Actions\Fulfilment\Pallet\SetPalletRental;
use App\Actions\Fulfilment\Pallet\UndoPalletStateToReceived;
use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\Pallet\UpdatePalletLocation;
use App\Actions\Fulfilment\PalletDelivery\ReceivedPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\SetPalletDeliveryAsBookedIn;
use App\Actions\Fulfilment\PalletDelivery\StartBookingPalletDelivery;
use App\Actions\UI\Notification\MarkNotificationAsRead;
use App\Actions\UI\Profile\UpdateProfile;

Route::patch('location/{location:id}/pallet/{pallet:id}', UpdatePalletLocation::class)->name('pallet.location.update')->withoutScopedBindings();
Route::patch('pallet/{pallet:id}/return', ReturnPalletToCustomer::class)->name('pallet.return');
Route::patch('pallet/{pallet:id}', [UpdatePallet::class, 'fromApi'])->name('pallet.update');
Route::patch('pallet/{pallet:id}/not-received', SetPalletAsNotReceived::class)->name('pallet.not-received');
Route::patch('pallet/{pallet:id}/undo-not-received', UndoPalletStateToReceived::class)->name('pallet.undo-not-received');
Route::patch('pallet/{pallet:id}/damaged', SetPalletAsDamaged::class)->name('pallet.damaged');
Route::patch('pallet/{pallet:id}/lost', SetPalletAsLost::class)->name('pallet.lost');
Route::patch('pallet/{pallet:id}/set-rental', SetPalletRental::class)->name('pallet.set-rental');

Route::post('profile', UpdateProfile::class)->name('profile.update');
Route::patch('notification/{notification}', MarkNotificationAsRead::class)->name('notifications.read');


Route::patch('pallet-delivery/{palletDelivery:id}/received', ReceivedPalletDelivery::class)->name('pallet-delivery.received');
Route::patch('pallet-delivery/{palletDelivery:id}/start-booking', StartBookingPalletDelivery::class)->name('pallet-delivery.start_booking');
Route::patch('pallet-delivery/{palletDelivery:id}/booked-in', SetPalletDeliveryAsBookedIn::class)->name('pallet-delivery.booked-in');