<?php

namespace App\Actions\CRM\Appointment;

use App\Actions\CRM\AppointmentType\GetAppointmentTypeAvailableSlots;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Enums\CRM\AppointmentType\AppointmentTypeMeetingModeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Appointment;
use App\Models\CRM\AppointmentType;
use App\Actions\CRM\Prospect\StoreProspect;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'email'               => $this->appointmentEmailRules($appointmentTypeId ? (int) $appointmentTypeId : null, $isUpdate),
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

    protected function appointmentEmailRules(?int $appointmentTypeId, bool $isUpdate): array
    {
        $isVideoCall = $appointmentTypeId && AppointmentType::withTrashed()->find($appointmentTypeId)?->meeting_mode === AppointmentTypeMeetingModeEnum::VIDEO_CALL;

        if (!$isVideoCall) {
            return [...($isUpdate ? ['sometimes'] : []), 'nullable', 'email', 'max:255'];
        }

        return [...($isUpdate ? ['sometimes'] : []), 'required', 'email', 'max:255'];
    }

    protected function appointmentEmailMessages(): array
    {
        return [
            'email.required' => __('Video calls need an email, it is where the confirmation and the reminder go.'),
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
     * An appointment that holds its time cannot start in the past, go over what its type takes at
     * once, or be given to someone who is at an appointment of another type at that time. Several
     * bookings of one type can share a time and a member of staff, up to the type's capacity. A shop
     * that takes one appointment at a time does not take one beside an appointment of another type.
     */
    protected function ensureAppointmentFits(array $modelData, ?Appointment $appointment = null): void
    {
        $state = Arr::get($modelData, 'state', $appointment?->state);
        $state = $state instanceof AppointmentStateEnum ? $state : AppointmentStateEnum::from($state);

        $appointmentTypeId = (int) Arr::get($modelData, 'appointment_type_id', $appointment?->appointment_type_id);
        $userId            = Arr::has($modelData, 'user_id') ? $modelData['user_id'] : $appointment?->user_id;
        /** @var Carbon $startsAt */
        $startsAt          = $modelData['starts_at'] ?? $appointment->starts_at;
        /** @var Carbon $endsAt */
        $endsAt            = $modelData['ends_at'] ?? $appointment->ends_at;

        $takesNewTime = !$appointment
            || !$appointment->state->holdsSlot()
            || !$startsAt->equalTo($appointment->starts_at)
            || $appointmentTypeId !== $appointment->appointment_type_id;
        $changesStaff = $userId && (int) $userId !== $appointment?->user_id;

        if (!$state->holdsSlot() || (!$takesNewTime && !$changesStaff)) {
            return;
        }

        $others = fn () => Appointment::whereIn('state', AppointmentStateEnum::holdingSlot())
            ->when($appointment, fn ($query) => $query->where('id', '!=', $appointment->id));

        if ($takesNewTime) {
            if ($startsAt->isPast()) {
                throw ValidationException::withMessages(['starts_at' => __('Choose a time that has not passed yet.')]);
            }

            $slots = GetAppointmentTypeAvailableSlots::make();
            $slots->lockShopAppointmentTypes(AppointmentType::withTrashed()->find($appointmentTypeId)->shop);
            $appointmentType = AppointmentType::withTrashed()->lockForUpdate()->find($appointmentTypeId);
            $buffer          = $appointmentType->breakMinutes();
            $booked          = $others()
                ->where('appointment_type_id', $appointmentTypeId)
                ->where('starts_at', '<', $endsAt->copy()->addMinutes($buffer))
                ->where('ends_at', '>', $startsAt->copy()->subMinutes($buffer))
                ->count();

            if ($booked >= $appointmentType->capacity_per_slot) {
                throw ValidationException::withMessages(['starts_at' => __('This time is already fully booked for :type.', ['type' => $appointmentType->name])]);
            }

            $otherTypeBooking = $slots->isOneAtATime($appointmentType->shop) ? $others()
                ->where('shop_id', $appointmentType->shop_id)
                ->where('appointment_type_id', '!=', $appointmentTypeId)
                ->where('starts_at', '<', $endsAt->copy()->addMinutes($buffer))
                ->where('ends_at', '>', $startsAt->copy()->subMinutes($buffer))
                ->with('appointmentType:id,name')
                ->first() : null;

            if ($otherTypeBooking) {
                throw ValidationException::withMessages(['starts_at' => __('This shop takes one appointment at a time, and this time is taken by a :type appointment.', ['type' => $otherTypeBooking->appointmentType?->name])]);
            }
        }

        $isBusyElsewhere = $userId && $others()
            ->where('user_id', $userId)
            ->where('appointment_type_id', '!=', $appointmentTypeId)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($isBusyElsewhere) {
            throw ValidationException::withMessages(['user_id' => __(':name already has another appointment at this time.', ['name' => User::find($userId)?->contact_name])]);
        }
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
