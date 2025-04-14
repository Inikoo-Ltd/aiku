<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:48:13 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Appointment;

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\OrgAction;
use App\Enums\CRM\Appointment\AppointmentEventEnum;
use App\Enums\CRM\Appointment\AppointmentTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class StoreAppointment extends OrgAction
{
    public Customer|Shop $parent;

    public function handle(Shop $shop, array $modelData): Model
    {
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);
        data_set($modelData, 'name', $modelData['contact_name']);
        data_set($modelData, 'shop_id', $shop->id);

        /** @var Customer $customer */
        $customer = StoreCustomer::run($shop, Arr::only($modelData, ['contact_name', 'company_name', 'email']));

        /** @var \App\Models\CRM\Appointment $appointment */
        $appointment = $customer->appointments()->create(Arr::except($modelData, ['contact_name', 'company_name', 'email']));


        if ($appointment->event == AppointmentEventEnum::CALLBACK) {
            CreateMeetingUsingZoom::run($appointment);
        } elseif ($appointment->event == AppointmentEventEnum::IN_PERSON) {
            $appointment->update(['event_address' => 'https://maps.app.goo.gl/Gr6RQbgkx2gkXuae7']);
        }


        return $appointment;
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function htmlResponse(): RedirectResponse
    {
        return match (class_basename($this->parent)) {
            'Shop' => redirect()->route('org.crm.shop.appointments.index', [
                'shop' => $this->parent
            ]),
            default => back(),
        };
    }

    public function rules(): array
    {
        return [
            'customer_id'   => ['sometimes'],
            'contact_name'  => ['sometimes', 'string'],
            'company_name'  => ['sometimes', 'string'],
            'email'         => ['sometimes', 'email'],
            'schedule_at'   => ['required'],
            'description'   => ['nullable', 'string', 'max:255'],
            'type'          => ['required', Rule::enum(AppointmentTypeEnum::class)],
            'event'         => ['required', Rule::enum(AppointmentEventEnum::class)],
            'event_address' => ['required', 'string']
        ];
    }

    /**
     * @throws Throwable
     */
    public function asController(Customer $customer, ActionRequest $request): Model
    {
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer->shop, $this->validatedData);
    }


    public string $commandSignature = 'appointment:book {shop} {hour} {minute}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $shop   = $command->argument('shop');
        $hour   = $command->argument('hour');
        $minute = $command->argument('minute');

        try {
            $shop = Shop::where('slug', $shop)->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $this->setRawAttributes([
            'name'          => fake()->name,
            'schedule_at'   => now()->setHours($hour)->setMinutes($minute),
            'type'          => AppointmentTypeEnum::LEAD->value,
            'event'         => AppointmentEventEnum::IN_PERSON->value,
            'event_address' => fake()->address
        ]);

        try {
            $validatedData = $this->validateAttributes();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $this->handle($shop, $validatedData);

        $command->info("Appointment created successfully 🎉");

        return 0;
    }
}
