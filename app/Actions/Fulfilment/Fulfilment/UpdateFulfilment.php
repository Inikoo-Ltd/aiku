<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment;

use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithGetRecurringBillEndDate;
use App\Actions\Web\Website\UpdateWebsite;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Rules\File;

class UpdateFulfilment extends OrgAction
{
    use WithActionUpdate;
    use WithGetRecurringBillEndDate;

    protected Fulfilment $fulfilment;

    public function handle(Fulfilment $fulfilment, array $modelData): Fulfilment
    {
        if (Arr::has($modelData, 'invoice_serial_references')) {
            UpdateShop::make()->action($fulfilment->shop, Arr::only($modelData, ['invoice_serial_references']));
            data_forget($modelData, 'invoice_serial_references');
            $fulfilment->refresh();
        }

        $settings       = $fulfilment->settings;
        $updateSettings = false;
        $updateAll      = Arr::get($modelData, 'update_all', false);
        data_forget($modelData, 'update_all');

        if (Arr::exists($modelData, 'weekly_cut_off_day')) {
            $settings['rental_agreement_cut_off']['weekly']['day'] = $modelData['weekly_cut_off_day'];
            $updateSettings                                        = true;
            data_forget($modelData, 'weekly_cut_off_day');
            $this->getEndDateWeekly(now(), $settings['rental_agreement_cut_off']['weekly']);
        }

        if (Arr::exists($modelData, 'monthly_cut_off')) {
            // need to handle 'last_day' string
            $settings['rental_agreement_cut_off']['monthly']['day']         = $modelData['monthly_cut_off']['date'];
            $settings['rental_agreement_cut_off']['monthly']['is_weekdays'] = $modelData['monthly_cut_off']['isWeekdays'] ?? false;
            $updateSettings                                                 = true;
            data_forget($modelData, 'monthly_cut_off');
        }

        if (Arr::exists($modelData, 'enable_chat')) {
            data_set($settings, 'chat.enable_chat', data_get($modelData, 'enable_chat'));
            $updateSettings = true;

            UpdateWebsite::make()->action(
                website: $fulfilment->shop->website,
                modelData: ['enable_chat' => Arr::pull($modelData, 'enable_chat')],
                hydratorsDelay: 0,
                strict: false,
                audit: true
            );
        }

        if ($updateSettings) {
            $modelData['settings'] = $settings;
        }

        $shopData = Arr::only($modelData, [
            'name',
            'code',
            'currency_id',
            'country_id',
            'language_id',
            'contact_name',
            'company_name',
            'email',
            'phone',
            'address',
            'registration_number',
            'vat_number',
            'invoice_footer',
            'image',
            'sender_email'
        ]);

        $modelData = Arr::except($modelData, array_keys($shopData));

        $fulfilment = $this->update($fulfilment, $modelData, ['settings']);

        if ($updateAll) {
            $recurringBills = $fulfilment->recurringBills->where('status', RecurringBillStatusEnum::CURRENT);
            $currentDate    = now();

            foreach ($recurringBills as $recurringBill) {
                $rentalAgreement         = $recurringBill->rentalAgreement;
                $endDate                 = $this->getEndDate(
                    $currentDate->copy(),
                    Arr::get(
                        $settings,
                        'rental_agreement_cut_off.'.$rentalAgreement->billing_cycle->value
                    )
                );
                $recurringBill->end_date = $endDate;
                $recurringBill->save();
            }
        }

        UpdateShop::make()->action($fulfilment->shop, $shopData);

        return $fulfilment;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        return [
            'weekly_cut_off_day'         => ['sometimes', 'string', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'monthly_cut_off'            => [
                'sometimes',
                'array'
            ],
            'monthly_cut_off.date'       => [
                'sometimes',
                function ($attribute, $value, $fail) {
                    if (is_int($value) && $value >= 1 && $value <= 31) {
                        return true;
                    } elseif ($value === 'last_day') {
                        return true;
                    } else {
                        $fail($attribute.' is invalid.');
                    }

                    return false;
                },
            ],
            'monthly_cut_off.isWeekdays' => [
                'sometimes',
                'boolean',
            ],
            'update_all'                 => [
                'sometimes',
                'boolean',
            ],
            'name'                       => ['sometimes', 'required', 'string', 'max:255'],
            'code'                       => [
                'sometimes',
                'required',
                'max:8',
                'alpha_dash',
                new IUnique(
                    table: 'shops',
                    extraConditions: [

                        ['column' => 'group_id', 'value' => $this->organisation->group_id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->shop->id
                        ],
                    ]
                ),

            ],
            'currency_id'                => ['sometimes', 'required', 'exists:currencies,id'],
            'country_id'                 => ['sometimes', 'required', 'exists:countries,id'],
            'language_id'                => ['sometimes', 'required', 'exists:languages,id'],
            'contact_name'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'                      => ['sometimes', 'nullable', 'email'],
            'phone'                      => ['sometimes', 'nullable'],
            'address'                    => ['sometimes', 'required', new ValidAddress()],
            'registration_number'        => ['sometimes', 'string'],
            'vat_number'                 => ['sometimes', 'string'],
            'invoice_footer'             => ['sometimes', 'string'],
            'enable_chat'                => ['sometimes', 'boolean'],
            'sender_email'               => ['sometimes', 'email'],
            'invoice_serial_references'  => ['sometimes', 'array'],
            'image'                      => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ]
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('monthly_cut_off.isWeekdays')) {
            $this->set('monthly_cut_off.isWeekdays', (bool)$this->get('monthly_cut_off.isWeekdays'));
        }
    }

    public function asController(Fulfilment $fulfilment, ActionRequest $request): Fulfilment
    {
        $this->fulfilment = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);


        return $this->handle($fulfilment, $this->validatedData);
    }

    public function action(Fulfilment $fulfilment, array $modelData, int $hydratorsDelay = 0): Fulfilment
    {
        $this->fulfilment     = $fulfilment;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromFulfilment($fulfilment, $modelData);

        return $this->handle($fulfilment, $this->validatedData);
    }


}
