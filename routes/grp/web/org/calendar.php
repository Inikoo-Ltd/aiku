<?php

use App\Actions\HumanResources\CalendarEvent\UI\ShowOrganisationCalendar;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowOrganisationCalendar::class)->name('index');
