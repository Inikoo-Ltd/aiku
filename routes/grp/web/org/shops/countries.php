<?php

use App\Actions\CRM\Customer\UI\ShowCustomerCountry;

Route::get('/{country}', ShowCustomerCountry::class)->name('show');
