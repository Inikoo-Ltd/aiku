<?php

namespace App\Actions\CRM\Appointment;

use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Appointment;
use App\Models\CRM\AppointmentType;
use App\Actions\CRM\Prospect\StoreProspect;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

trait WithAppointmentRules
{
    use WithAppointmentPhoneRules;

    protected function appointmentRules(bool $isUpdate, ?Appointment $appointment = null): array
    {
        $required = $isUpdate ? ['sometimes', 'required'] : ['required'];
        $optional = $isUpdate ? ['sometimes'] : ['nullable'];

        $appointmentTypeId = $this->get('appointment_type_id', $appointment?->appointment_type_id);

        return [
            'appointment_type_id' => [
                ...$required,
                'integer',
                Rule::exists('appointment_types', 'id')->where('shop_id', $this->shop->id)->whereNull('deleted_at'),
            ],
            'starts_at'           => [...$required, 'date_format:Y-m-d H:i'],
            'contact_name'        => [...$required, 'string', 'max:255'],
            'email'               => [...$optional, 'nullable', 'email', 'max:255'],
            'phone'               => $this->appointmentPhoneRules($appointmentTypeId ? (int) $appointmentTypeId : null, $isUpdate),
            'number_visitors'     => [...$optional, 'integer', 'min:1', 'max:100'],
            'notes'               => [...$optional, 'nullable', 'string', 'max:5000'],
            'user_id'             => [
                ...$optional,
                'nullable',
                'integer',
                Rule::exists('appointment_type_user', 'user_id')->where('appointment_type_id', (int) $appointmentTypeId),
            ],
        ];
    }

    protected function prepareAppointmentData(Shop $shop, array $modelData, ?Appointment $appointment = null): array
    {
        if (Arr::has($modelData, 'starts_at')) {
            $modelData['starts_at'] = Carbon::createFromFormat('Y-m-d H:i', $modelData['starts_at'], $shop->timezone?->name ?? 'UTC')->utc();
        }

        if (Arr::hasAny($modelData, ['starts_at', 'appointment_type_id'])) {
            $appointmentType      = AppointmentType::withTrashed()->find($modelData['appointment_type_id'] ?? $appointment->appointment_type_id);
            $startsAt             = $modelData['starts_at'] ?? $appointment->starts_at;
            $modelData['ends_at'] = $startsAt->copy()->addMinutes($appointmentType->duration_minutes);
        }

        $customerId     = Arr::pull($modelData, 'customer_id');
        $marketingOptIn = (bool) Arr::pull($modelData, 'marketing_opt_in', false);

        if ($customerId || !$appointment || Arr::hasAny($modelData, ['email', 'phone'])) {
            $visitor = $customerId
                ? Customer::where('shop_id', $shop->id)->find($customerId)
                : $this->resolveVisitor(
                    shop: $shop,
                    email: Arr::get($modelData, 'email', $appointment?->email),
                    phone: Arr::get($modelData, 'phone', $appointment?->phone),
                    contactName: Arr::get($modelData, 'contact_name', $appointment?->contact_name),
                    marketingOptIn: $marketingOptIn
                );

            $modelData['visitor_type'] = $visitor?->getMorphClass();
            $modelData['visitor_id']   = $visitor?->id;
        }

        if (Arr::has($modelData, 'state')) {
            $state                     = $modelData['state'] instanceof AppointmentStateEnum ? $modelData['state'] : AppointmentStateEnum::from($modelData['state']);
            $modelData['cancelled_at'] = $state === AppointmentStateEnum::CANCELLED ? now() : null;
            $modelData['declined_at']  = $state === AppointmentStateEnum::DECLINED ? now() : null;
            if ($state === AppointmentStateEnum::ACCEPTED && !$appointment?->accepted_at) {
                $modelData['accepted_at'] = now();
            }
        }

        return $modelData;
    }

    /**
     * @throws \Throwable
     */
    protected function resolveVisitor(Shop $shop, ?string $email, ?string $phone, ?string $contactName, bool $marketingOptIn = false): Customer|Prospect|null
    {
        if (!$email && !$phone) {
            return null;
        }

        $customer = $this->findInShop(Customer::class, $shop, $email, $phone);
        if ($customer) {
            return $customer;
        }

        /** @var Prospect|null $prospect */
        $prospect = $this->findInShop(Prospect::class, $shop, $email, $phone);
        if ($prospect) {
            return $prospect->customer ?? $prospect;
        }

        return StoreProspect::make()->action(
            $shop,
            array_filter([
                'contact_name'    => $contactName,
                'email'           => $email,
                'phone'           => $phone,
                'dont_contact_me' => !$marketingOptIn,
                'is_opt_in'       => $marketingOptIn,
                'data'            => ['source' => 'appointment'],
            ], fn ($value) => !is_null($value) && $value !== ''),
            strict: false
        );
    }

    /**
     * @param class-string<Customer|Prospect> $modelClass
     */
    private function findInShop(string $modelClass, Shop $shop, ?string $email, ?string $phone): Customer|Prospect|null
    {
        $query = $modelClass::where('shop_id', $shop->id);

        if ($email) {
            $byEmail = (clone $query)->whereRaw('lower(email) = ?', [strtolower($email)])->first();
            if ($byEmail) {
                return $byEmail;
            }
        }

        return $phone ? (clone $query)->where('phone', $phone)->first() : null;
    }
}
