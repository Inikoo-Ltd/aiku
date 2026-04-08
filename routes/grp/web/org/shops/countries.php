<?php

use App\Actions\CRM\Customer\UI\IndexCustomerCountries;

Route::get('/', IndexCustomerCountries::class)->name('index');
