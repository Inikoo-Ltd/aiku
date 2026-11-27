<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\Employee;
use App\Actions\CRM\Customer\UpdateCustomerCreditLine;
use App\Actions\CRM\Customer\AnonymiseCustomer;
use App\Actions\CRM\Customer\CompanyIdLabels;
use App\Actions\Accounting\EInvoice\VerifyCustomerDic;
use App\Actions\OrgAction;
use App\Actions\Retina\SysAdmin\UpdateRetinaCustomer;
use App\Actions\Traits\Authorisations\WithCRMEditAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerShopTypeEnum;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Http\Resources\Helpers\TaxNumberResource;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditCustomer extends OrgAction
{
    use WithCRMEditAuthorisation {
        authorize as authorizeCrmEdit;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->authorizeCrmEdit($request)
            || UpdateCustomerCreditLine::canGrantCredit($request->user(), $request->route('customer'));
    }

    public function handle(Customer $customer): Customer
    {
        return $customer;
    }

    /** @return array<int, array{description: string}> */
    private function getEmailChangeWarnings(Customer $customer): array
    {
        $webUsers = $customer->webUsers()->get(['username', 'email']);
        if ($webUsers->isEmpty()) {
            return [];
        }

        $matchingWebUser = $webUsers->firstWhere('email', $customer->email);
        if ($matchingWebUser) {
            return [
                ['description' => __('Changing this email will also update the login email of web user :username.', ['username' => $matchingWebUser->username])]
            ];
        }

        return [
            ['description' => __('This customer has :count web user(s) with different login emails; changing this email will not affect them.', ['count' => $webUsers->count()])]
        ];
    }


    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): Customer
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customer);
    }

    private function dicVerificationNote(Customer $customer): string
    {
        $verification = Arr::get($customer->data, 'dic_verification');
        if (!$verification) {
            return '';
        }

        $checkedOn = Carbon::parse($verification['checked_at'])->format('j M Y');
        $register  = $verification['register_dic'] ? $verification['register_dic'].' ('.$verification['register_name'].')' : '';
        $searched  = ($verification['ico'] ?? null) ?: ($verification['vat_number'] ?? '');

        return match ($verification['status']) {
            VerifyCustomerDic::MATCHED      => __('Finančná správa register, :date: matches :register.', ['date' => $checkedOn, 'register' => $register]),
            VerifyCustomerDic::FILLED       => __('Finančná správa register, :date: filled from :register.', ['date' => $checkedOn, 'register' => $register]),
            VerifyCustomerDic::MISMATCH     => __('Finančná správa register, :date: DIFFERENT, the register has :register for IČO :ico.', ['date' => $checkedOn, 'register' => $register, 'ico' => $searched]),
            VerifyCustomerDic::NAME_DIFFERS => __('Finančná správa register, :date: IČO :ico is :register, a different name, so it was not filled.', ['date' => $checkedOn, 'register' => $register, 'ico' => $searched]),
            VerifyCustomerDic::NOT_REFILLED => __('Finančná správa register, :date: IČO :ico is :register, not filled again as the DIČ was cleared.', ['date' => $checkedOn, 'register' => $register, 'ico' => $searched]),
            default                         => __('Finančná správa register, :date: no subject with IČO or VAT number :ico.', ['date' => $checkedOn, 'ico' => $searched]),
        };
    }

    public function htmlResponse(Customer $customer, ActionRequest $request): Response
    {
        $spain = Country::where('code', 'ES')->first();

        $isExternal = $customer->shop->type == ShopTypeEnum::EXTERNAL;

        $companyIdLabels = CompanyIdLabels::for($customer->shop, $customer->address?->country_code);
        $altLabel        = CompanyIdLabels::customerAltLabel($customer);


        $contact    = [
            'title'  => __('Contact information'),
            'icon'   => 'fal fa-address-book',
            'label'  => __('Contact'),
            'fields' => [
                'contact_name'             => [
                    'type'  => 'input',
                    'label' => __('Contact name'),
                    'value' => $customer->contact_name
                ],
                'company_name'             => [
                    'type'  => 'input',
                    'label' => __('Company'),
                    'value' => $customer->company_name
                ],
                'shop_type'                => [
                    'type'        => 'select',
                    'label'       => __('Shop type'),
                    'placeholder' => __('Select shop type'),
                    'required'    => false,
                    'value'       => $customer->shop_type?->value,
                    'options'     => collect(CustomerShopTypeEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values()->all(),
                ],
                'email'                    => [
                    'type'  => 'input',
                    'label' => __('Email'),
                    'value' => $customer->email,
                    'information_warning' => $this->getEmailChangeWarnings($customer),
                ],
                'phone'                    => [
                    'type'  => 'phone',
                    'label' => __('Phone'),
                    'value' => $customer->phone
                ],
                'contact_address'          => [
                    'type'    => 'address',
                    'label'   => __('Address'),
                    'value'   => AddressFormFieldsResource::make($customer->address)->getArray(),
                    'options' => [
                    ]
                ],
                'delivery_address'         => [
                    'hidden'       => $customer->shop->type == ShopTypeEnum::DROPSHIPPING,
                    'type'         => 'delivery_address',
                    'label'        => __('Delivery Address'),
                    'noSaveButton' => true,
                    'options'      => [
                        'same_as_contact'      => [
                            'label'       => __('Same as contact address'),
                            'key_payload' => 'delivery_address_id',
                            'payload'     => $customer->address_id
                        ],
                    ],
                    'value'        => [
                        'is_same_as_contact' => $customer->delivery_address_id == $customer->address_id,
                        'address'            => AddressFormFieldsResource::make($customer->deliveryAddress)->getArray()
                    ],
                ],
                'tax_number'               => [
                    'type'                  => 'tax_number',
                    'label'                 => $companyIdLabels['vat'],
                    'value'                 => $customer->taxNumber ? TaxNumberResource::make($customer->taxNumber)->getArray() : null,
                    'mark_as_valid_button'  => [
                        'show'      => true,
                        'cus_id'    => $customer->id,
                    ],
                    'hasOther'              => [
                        'name'  => 'update_current_orders',
                        'value' => true,
                    ],
                    'country'               => $customer->address->country_code,
                    'options'               => [
                    ],
                ],
                'is_re'                    => [
                    'type'   => 'toggle',
                    'hidden' => $this->organisation->country_id != $spain->id || $customer->address->country_id != $spain->id,
                    'label'  => 'Recargo de equivalencia',
                    'value'  => $customer->is_re,

                ],

            ]
        ];

        $identification = [
            'title'  => __('Id/Fiscal Name'),
            'icon'   => 'fal fa-id-card',
            'label'  => __('Id/Fiscal name'),
            'fields' => [
                'fiscal_name'             => [
                    'type'  => 'input',
                    'label' => __('Fiscal name'),
                    'value' => $customer->fiscal_name
                ],
                'identity_document_number' => [
                    'type'          => 'input',
                    'label'         => $companyIdLabels['id'],
                    'value'         => $customer->identity_document_number
                ],
                'identity_document_number_alt' => [
                    'type'          => 'input',
                    'hidden'        => $altLabel === null,
                    'label'         => $altLabel ?? '',
                    'value'         => $customer->identity_document_number_alt
                ],
            ]
        ];

        if (UpdateRetinaCustomer::showsDicField($customer)) {
            $identification['fields']['peppol_dic'] = [
                'type'        => 'input',
                'label'       => __('DIČ'),
                'information' => trim(__('10 digits, without SK. Peppol e-invoices go to it, so a wrong number sends them to another company. With a validated Slovak VAT number, the DIČ in it is used.').' '.$this->dicVerificationNote($customer)),
                'value'       => UpdateRetinaCustomer::dicFor($customer),
            ];
        } elseif (Arr::get($customer->organisation->settings, 'e_invoicing.countries')) {
            $identification['fields']['peppol_id'] = [
                'type'        => 'input',
                'label'       => __('Peppol ID'),
                'information' => __('Where Peppol e-invoices are sent, as scheme:number, e.g. 0245:2120643690 for a Slovak DIČ. Leave empty to use the DIČ in the tax number.'),
                'value'       => $customer->peppol_id,
            ];
        }

        $accounting = [
            'title'  => __('Accounting'),
            'icon'   => 'fal fa-file-invoice',
            'label'  => __('Accounting'),
            'fields' => [

                'is_credit_customer'   => [
                    'type'  => 'toggle',
                    'label' => __('Credit Customer'),
                    'value' => $customer->is_credit_customer,
                ],
                'excess_refund_mode'   => [
                    'type'        => 'select',
                    'label'       => __('Money given back'),
                    'information' => __('Overrides the shop setting for this customer when an order paid by card is invoiced for less than was paid or is cancelled.'),
                    'value'       => Arr::get($customer->settings, 'excess_refund_mode', 'shop'),
                    'options'     => [
                        ['label' => __('As the shop'), 'value' => 'shop'],
                        ['label' => __('Refund to the card'), 'value' => 'refund'],
                        ['label' => __('Keep as credit'), 'value' => 'credit'],
                    ],
                ],
                'accounting_reference' => [
                    'type'     => 'input',
                    'label'    => __('Sage Customer Number'),
                    'value'    => $customer->accounting_reference,
                    'required' => false,
                ],
            ]
        ];
        $creditLineUpdateRoute = [
            'name'       => 'grp.models.customer.credit_line.update',
            'parameters' => [$customer->id]
        ];
        $creditLine = [
            'title'  => __('Credit line'),
            'icon'   => 'fal fa-money-bill',
            'label'  => __('Credit line'),
            'fields' => [
                'credit_limit'       => [
                    'type'        => 'input',
                    'label'       => __('Credit limit'),
                    'value'       => $customer->credit_limit,
                    'updateRoute' => $creditLineUpdateRoute,
                    'required'    => false,
                ],
                'payment_terms_days' => [
                    'type'        => 'input',
                    'label'       => __('Payment terms (days)'),
                    'value'       => $customer->payment_terms_days,
                    'updateRoute' => $creditLineUpdateRoute,
                    'required'    => false,
                ],
            ]
        ];
        $canGrantCredit = UpdateCustomerCreditLine::canGrantCredit($request->user(), $customer);

        $tags       = [
            'title'  => __('Tags'),
            'icon'   => 'fal fa-tags',
            'label'  => __('Tags'),
            'fields' => [
                'tags' => [
                    'type'                   => 'tags-customer',
                    'label'                  => __('Tags'),
                    'value'                  => $customer->tags->where('scope', TagScopeEnum::ADMIN_CUSTOMER)->pluck('id')->toArray(),
                    'noSaveButton'           => true,
                    'isWithRefreshFieldForm' => true,
                    'tag_routes'             => [
                        'index_tag'  => [
                            'name'       => 'grp.json.customer.tags.index',
                            'parameters' => [
                                'customer' => $customer,
                            ]
                        ],
                        'store_tag'  => [
                            'name'       => 'grp.models.customer.tags.store',
                            'parameters' => [
                                'customer' => $customer->id,
                            ]
                        ],
                        'update_tag' => [
                            'name'       => 'grp.models.customer.tags.update',
                            'parameters' => [
                                'customer' => $customer->id,
                            ],
                            'method'     => 'patch'
                        ],
                        'delete_tag' => [
                            'name'       => 'grp.models.customer.tags.delete',
                            'parameters' => [
                                'customer' => $customer->id,
                            ],
                            'method'     => 'delete'
                        ],
                        'attach_tag' => [
                            'name'       => 'grp.models.customer.tags.attach',
                            'parameters' => [
                                'customer' => $customer->id,
                            ],
                            'method'     => 'post'
                        ],
                        'detach_tag' => [
                            'name'       => 'grp.models.customer.tags.detach',
                            'parameters' => [
                                'customer' => $customer->id,
                            ],
                            'method'     => 'delete'
                        ],
                    ],
                ],
            ]
        ];

        $vip = [
            'title'  => __('VIP'),
            'icon'   => 'fal fa-medal',
            'label'  => __('VIP'),
            'fields' => [
                'is_vip'   => [
                    'type'  => 'toggle',
                    'label' => __('VIP'),
                    'value' => $customer->is_vip,
                ],
            ]
        ];

        $staff = [
            'title'  => __('Staff'),
            'icon'   => 'fal fa-user-tag',
            'label'  => __('Staff'),
            'fields' => [
                'as_employee_id' => [
                    'type'        => 'select',
                    'label'       => __('Employee behind this account'),
                    'placeholder' => __('Not an employee'),
                    'searchable'  => true,
                    'value'       => $customer->as_employee_id,
                    'options'     => Employee::where('organisation_id', $this->organisation->id)
                        ->whereIn('state', [EmployeeStateEnum::HIRED, EmployeeStateEnum::WORKING, EmployeeStateEnum::LEAVING])
                        ->orderBy('contact_name')
                        ->get(['id', 'contact_name'])
                        ->map(fn (Employee $employee) => ['value' => $employee->id, 'label' => $employee->contact_name])
                        ->all(),
                    'information' => $customer->is_staff
                        ? __('Staff account: excluded from customer analytics')
                        : __('Linking an employee marks the account as staff. Company email addresses and partner accounts are detected on their own.'),
                ],
            ]
        ];

        if ($isExternal) {
            $blueprint = [
                [
                    'title'  => __('Tax number'),
                    'icon'   => 'fal fa-fingerprint',
                    'label'  => __('Tax number'),
                    'fields' => Arr::only($contact['fields'], ['tax_number']),
                ]
            ];
        } else {
            $blueprint = [$contact, $identification, $accounting, $tags, $vip, $staff];
        }

        if (!$this->authorizeCrmEdit($request)) {
            $blueprint = [$creditLine];
        } elseif ($canGrantCredit) {
            $blueprint[] = $creditLine;
        }

        if (!$isExternal && AnonymiseCustomer::canBeAnonymisedBy($request->user(), $customer)) {
            $blueprint[] = [
                'title'  => __('Delete'),
                'label'  => __('Delete'),
                'icon'   => 'fal fa-trash-alt',
                'fields' => [
                    'delete_customer' => [
                        'type'      => 'delete_customer',
                        'label'     => __('Delete customer'),
                        'noSaveButton' => true,
                        'reference' => AnonymiseCustomer::confirmationText($customer),
                        'orders'    => $customer->orders()->whereNotIn('state', [OrderStateEnum::CANCELLED, OrderStateEnum::CREATING])->count(),
                        'invoices'  => $customer->invoices()->count(),
                        'owes_money' => in_array('owes_money', AnonymiseCustomer::erasureHoldReasons($customer)),
                        'route'     => [
                            'name'       => 'grp.models.customer.anonymise',
                            'parameters' => ['customer' => $customer->id],
                        ],
                    ],
                ],
            ];
        }

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit Customer').' '.$customer->reference,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($customer, $request),
                    'next'     => $this->getNext($customer, $request),
                ],
                'pageHead'    => [
                    'title'   => $customer->name,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ]
                    ],
                ],


                'formData' => [
                    'blueprint' => $blueprint,
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.customer.update',
                            'parameters' => [$customer->id]

                        ],
                    ]

                ],

            ]
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowCustomer::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    public function getPrevious(Customer $customer, ActionRequest $request): ?array
    {
        $previous = Customer::where('slug', '<', $customer->slug)->when(true, function ($query) use ($customer, $request) {
            if ($request->route()->getName() == 'shops.show.customers.show') {
                $query->where('customers.shop_id', $customer->shop_id);
            }
        })->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Customer $customer, ActionRequest $request): ?array
    {
        $next = Customer::where('slug', '>', $customer->slug)->when(true, function ($query) use ($customer, $request) {
            if ($request->route()->getName() == 'shops.show.customers.show') {
                $query->where('customers.shop_id', $customer->shop_id);
            }
        })->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Customer $customer, string $routeName): ?array
    {
        if (!$customer) {
            return null;
        }

        return match ($routeName) {
            'grp.org.shops.show.crm.customers.edit' => [
                'label' => $customer->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $customer->organisation->slug,
                        'shop'         => $customer->shop->slug,
                        'customer'     => $customer->slug
                    ]

                ]
            ]
        };
    }
}
