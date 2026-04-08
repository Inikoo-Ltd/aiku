<?php

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Enums\UI\CRM\CustomerCountryTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowCustomerCountry extends OrgAction
{
    use WithCRMAuthorisation;
    use WithCustomersSubNavigation;

    protected Shop $shop;

    public function handle(Shop $shop, string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);
        $countryName = Country::where('code', $countryCode)->value('name') ?? $countryCode;

        $exists = DB::table('customers')
            ->where('shop_id', $shop->id)
            ->whereRaw("location->>0 = ?", [$countryCode])
            ->exists();

        if (!$exists) {
            throw new NotFoundHttpException();
        }

        $invoiceStats = DB::table('invoices')
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->where('customers.shop_id', $shop->id)
            ->whereRaw("customers.location->>0 = ?", [$countryCode])
            ->selectRaw("COUNT(DISTINCT invoices.id) as number_invoices, COALESCE(SUM(invoices.net_amount), 0) as total_net_amount")
            ->first();

        $orderCount = DB::table('orders')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->where('customers.shop_id', $shop->id)
            ->whereRaw("customers.location->>0 = ?", [$countryCode])
            ->count();

        $customerCount = DB::table('customers')
            ->where('shop_id', $shop->id)
            ->whereRaw("location->>0 = ?", [$countryCode])
            ->count();

        return [
            'country_code' => $countryCode,
            'country_name' => $countryName,
            'stats'        => [
                'number_customers' => $customerCount,
                'number_invoices'  => (int) $invoiceStats->number_invoices,
                'total_net_amount' => (float) $invoiceStats->total_net_amount,
                'number_orders'    => $orderCount,
                'currency_code'    => $shop->currency->code,
            ],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, string $country, ActionRequest $request): array
    {
        $this->shop = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(CustomerCountryTabsEnum::values());

        return $this->handle($shop, $country);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Shop/CRM/CustomerCountry',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->originalParameters(), $data['country_name']),
                'title'        => $data['country_name'],
                'pageHead'     => [
                    'title' => $data['country_name'],
                    'icon'  => [
                        'icon'  => ['fal', 'fa-globe'],
                        'title' => __('Country'),
                    ],
                ],
                'tabs'         => [
                    'current'    => $this->tab,
                    'navigation' => CustomerCountryTabsEnum::navigation(),
                ],
                'country_code' => $data['country_code'],
                'country_name' => $data['country_name'],
                'stats'        => $data['stats'],

                CustomerCountryTabsEnum::TOP_PRODUCTS->value => $this->tab == CustomerCountryTabsEnum::TOP_PRODUCTS->value
                    ? fn () => IndexTopSoldProductsInCountry::run($this->shop, $data['country_code'], CustomerCountryTabsEnum::TOP_PRODUCTS->value)
                    : Inertia::lazy(fn () => IndexTopSoldProductsInCountry::run($this->shop, $data['country_code'], CustomerCountryTabsEnum::TOP_PRODUCTS->value)),

                CustomerCountryTabsEnum::SEASONAL_PRODUCTS->value => $this->tab == CustomerCountryTabsEnum::SEASONAL_PRODUCTS->value
                    ? fn () => IndexTopSoldProductsInCountry::run($this->shop, $data['country_code'], CustomerCountryTabsEnum::SEASONAL_PRODUCTS->value)
                    : Inertia::lazy(fn () => IndexTopSoldProductsInCountry::run($this->shop, $data['country_code'], CustomerCountryTabsEnum::SEASONAL_PRODUCTS->value)),
            ]
        )
        ->table(IndexTopSoldProductsInCountry::make()->tableStructure(prefix: CustomerCountryTabsEnum::TOP_PRODUCTS->value))
        ->table(IndexTopSoldProductsInCountry::make()->tableStructure(prefix: CustomerCountryTabsEnum::SEASONAL_PRODUCTS->value));
    }

    public function getBreadcrumbs(array $routeParameters, string $countryName): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.countries.index',
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Countries'),
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.countries.show',
                                'parameters' => $routeParameters,
                            ],
                            'label' => $countryName,
                        ],
                    ],
                ],
            ]
        );
    }
}
