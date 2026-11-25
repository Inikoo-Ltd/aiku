<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-16h-18m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\SysAdmin;

use App\Actions\Accounting\EInvoice\BuildUblInvoice;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithModelAddressActions;
use App\Actions\Traits\WithPrepareTaxNumberValidation;
use App\Models\CRM\Customer;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Lorisleiva\Actions\ActionRequest;
use App\Traits\SanitizeInputs;

class UpdateRetinaCustomer extends RetinaAction
{
    use WithActionUpdate;
    use WithModelAddressActions;
    use WithPrepareTaxNumberValidation;
    use SanitizeInputs;

    private bool $action = false;

    public function handle(Customer $customer, array $modelData): Customer
    {
        if (Arr::has($modelData, 'peppol_dic')) {
            $dic = Arr::pull($modelData, 'peppol_dic');
            if (self::needsPeppolDic($customer)) {
                $modelData['peppol_id'] = $dic ? BuildUblInvoice::PEPPOL_SCHEME.':'.$dic : null;
            }
        }

        return UpdateCustomer::run($customer, $modelData);
    }

    /**
     * Slovak business customers (company name or company number) of a Slovak company have a DIČ, where their e-invoices go,
     * as do those who already gave one. A Peppol ID staff entered in another scheme is never shown or overwritten as a DIČ.
     */
    public static function needsPeppolDic(Customer $customer): bool
    {
        if ($customer->peppol_id) {
            return str_starts_with($customer->peppol_id, BuildUblInvoice::PEPPOL_SCHEME.':');
        }

        return $customer->address?->country_code === 'SK'
            && $customer->organisation->country?->code === 'SK'
            && (trim((string)$customer->company_name) !== '' || strlen(preg_replace('/\D/', '', (string)$customer->identity_document_number)) >= 8);
    }

    /**
     * A Slovak business customer of an organisation that e-invoices Slovakia, with no DIČ to send its e-invoices to:
     * staff are told on its orders so the DIČ is fixed before invoicing and the e-invoice still goes out the same day.
     */
    public static function lacksDicForEInvoices(Customer $customer): bool
    {
        return in_array('SK', Arr::get($customer->organisation->settings, 'e_invoicing.countries') ?? [], true)
            && self::needsPeppolDic($customer)
            && self::dicFor($customer) === null;
    }

    /**
     * The DIČ e-invoices go to, in the order BuildUblInvoice uses: a validated Slovak VAT number first, else the one stored.
     */
    public static function dicFor(Customer $customer): ?string
    {
        $fromVat = $customer->taxNumber?->valid && $customer->taxNumber->country_code === 'SK'
            ? BuildUblInvoice::participantId('SK'.preg_replace('/^SK/i', '', (string)$customer->taxNumber->number))['id'] ?? null
            : null;

        return $fromVat ?? ($customer->peppol_id && str_starts_with($customer->peppol_id, BuildUblInvoice::PEPPOL_SCHEME.':') ? Str::after($customer->peppol_id, ':') : null);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action) {
            return true;
        }

        return $this->customer?->id === $request->route()->parameter('customer')->id && $request->user()->is_root;
    }


    public function prepareForValidation(ActionRequest $request): void
    {
        $this->setSanitizeFields([
            'contact_name',
            'company_name',
            'email',
            'contact_address',
            'tax_number',
        ]);
        $this->sanitizeInputs();
        $this->prepareTaxNumberInput($request);
        if ($request->has('peppol_dic')) {
            $request->merge(['peppol_dic' => preg_replace('/\D/', '', (string)$request->input('peppol_dic')) ?: null]);
        }
    }

    public function rules(): array
    {
        return [
            'contact_name'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'fiscal_name'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'                        => [
                'sometimes',
                'nullable',
                'email',
                new IUnique(
                    table: 'customers',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'id', 'value' => $this->customer->id, 'operator' => '!='],
                    ]
                ),
                new IUnique(
                    table: 'web_users',
                    extraConditions: [
                        ['column' => 'website_id', 'value' => $this->shop->website?->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'customer_id', 'value' => $this->customer->id, 'operator' => '!='],
                    ]
                ),
            ],
            'phone'                        => ['sometimes', 'nullable', new Phone()],
            'contact_address'              => ['sometimes', 'required', new ValidAddress(requireFullAddress: !$this->asAction)],
            'tax_number'                   => ['sometimes', 'nullable', 'array'],
            'is_re'                        => ['sometimes', 'boolean'],
            'delivery_address'             => ['sometimes', 'nullable', new ValidAddress(requireFullAddress: !$this->asAction)],
            'delivery_address_id'          => ['sometimes', 'integer'],
            'tags'                         => ['sometimes', 'array'],
            'is_gift_opted_out'            => ['sometimes', 'boolean'],
            'identity_document_number'     => ['sometimes', 'nullable', 'string'],
            'identity_document_number_alt' => ['sometimes', 'nullable', 'string'],
            'peppol_dic'                   => [
                'sometimes',
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (is_string($value) && !BuildUblInvoice::isSlovakDic($value)) {
                        $fail(__('This is not a valid Slovak DIČ. Please check the number.'));
                    }
                },
            ],
        ];
    }

    public function asController(Customer $customer, ActionRequest $request): Customer
    {
        $this->enableSanitize();
        $this->initialisation($request);

        return $this->handle($customer, $this->validatedData);
    }

    public function action(Customer $customer, array $modelData): Customer
    {
        $this->action = true;
        $this->initialisationFulfilmentActions($customer->fulfilmentCustomer, $modelData);

        return $this->handle($customer, $this->validatedData);
    }


}
