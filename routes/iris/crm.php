<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 07 Mar 2024 12:03:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\CRM\Appointment\CheckCustomerAppointment;
use App\Actions\CRM\Appointment\GetBookedScheduleAppointment;
use App\Actions\CRM\Appointment\LoginCustomerAppointment;
use App\Actions\CRM\Appointment\RegisterCustomerAppointment;
use App\Actions\CRM\Appointment\StoreAppointment;

Route::prefix('appointment')->as('appointment.')->group(function () {
    Route::get('/schedule', GetBookedScheduleAppointment::class)->name('schedule');
    Route::post('/check/email', CheckCustomerAppointment::class)->name('check.email');
    Route::post('/login', LoginCustomerAppointment::class)->name('login');
    Route::post('/register', RegisterCustomerAppointment::class)->name('register');
});

Route::post('/appointment', [StoreAppointment::class, 'inCustomer'])->name('appointment.store');
