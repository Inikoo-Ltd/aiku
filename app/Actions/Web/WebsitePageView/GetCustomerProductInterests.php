<?php

namespace App\Actions\Web\WebsitePageView;

use App\Models\CRM\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCustomerProductInterests
{
    use AsAction;

    public function handle(Customer $customer, int $days = 90, int $limit = 10): Collection
    {
        $webUserIds = $customer->webUsers()->pluck('web_users.id');

        if ($webUserIds->isEmpty()) {
            return collect();
        }

        return DB::table('website_page_views')
            ->select([
                'webpages.model_id as product_id',
                'products.name as product_name',
                'products.code as product_code',
                DB::raw('COUNT(website_page_views.id) as view_count'),
                DB::raw('MAX(website_page_views.view_date) as last_viewed_at'),
                DB::raw('SUM(CASE WHEN wce.event_type = \'add_to_basket\' THEN 1 ELSE 0 END) as add_to_basket_count'),
            ])
            ->join('website_visitors', 'website_page_views.website_visitor_id', '=', 'website_visitors.id')
            ->join('webpages', 'website_page_views.webpage_id', '=', 'webpages.id')
            ->join('products', 'webpages.model_id', '=', 'products.id')
            ->leftJoin('website_conversion_events as wce', function ($join) {
                $join->on('wce.website_visitor_id', '=', 'website_visitors.id')
                    ->on('wce.product_id', '=', 'webpages.model_id');
            })
            ->whereIn('website_visitors.web_user_id', $webUserIds)
            ->where('webpages.model_type', 'Product')
            ->where('website_page_views.view_date', '>=', now()->subDays($days)->toDateString())
            ->groupBy('webpages.model_id', 'products.name', 'products.code')
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get();
    }
}
