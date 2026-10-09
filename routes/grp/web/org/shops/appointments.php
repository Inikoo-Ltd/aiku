<?php

use App\Actions\CRM\Appointment\UI\CreateAppointment;
use App\Actions\CRM\Appointment\UI\EditAppointment;
use App\Actions\CRM\Appointment\UI\IndexAppointments;
use App\Actions\CRM\AppointmentStaff\UI\CreateAppointmentStaff;
use App\Actions\CRM\AppointmentStaff\UI\EditAppointmentStaff;
use App\Actions\CRM\AppointmentStaff\UI\IndexAppointmentStaff;
use App\Actions\CRM\AppointmentType\UI\CreateAppointmentType;
use App\Actions\CRM\AppointmentType\UI\EditAppointmentType;
use App\Actions\CRM\AppointmentType\UI\IndexAppointmentTypes;
use Illuminate\Support\Facades\Route;

Route::get('', IndexAppointments::class)->name('index');
Route::get('create', CreateAppointment::class)->name('create');
Route::get('{appointment:id}/edit', EditAppointment::class)->name('edit')->whereNumber('appointment');

Route::get('types', IndexAppointmentTypes::class)->name('types.index');
Route::get('types/create', CreateAppointmentType::class)->name('types.create');
Route::get('types/{appointmentType:slug}/edit', EditAppointmentType::class)->name('types.edit');

Route::get('staff', IndexAppointmentStaff::class)->name('staff.index');
Route::get('staff/create', CreateAppointmentStaff::class)->name('staff.create');
Route::get('staff/{user:id}/edit', EditAppointmentStaff::class)->name('staff.edit')->withoutScopedBindings();
