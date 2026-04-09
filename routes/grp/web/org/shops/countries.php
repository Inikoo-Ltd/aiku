<?php

use App\Actions\CRM\Customer\UI\IndexCustomerCountries;
use App\Actions\CRM\Customer\UI\ShowCustomerCountry;

Route::get('/', IndexCustomerCountries::class)->name('index');
Route::get('/{country}', ShowCustomerCountry::class)->name('show');
