<?php

use App\Actions\Discounts\Offer\UI\ShowOfferCalendar;
use Illuminate\Support\Facades\Route;

Route::get('calendar', ShowOfferCalendar::class)->name('calendar');
