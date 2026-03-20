<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteConversionEvent;

use App\Actions\CRM\CustomerInterest\RecordCustomerProductInterests;
use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerInterest;
use App\Models\Web\WebsiteConversionEvent;
use App\Models\Web\WebsitePageView;
use App\Models\Web\WebsiteVisitor;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessRegistrationConversionEvent
{
    use AsAction;

    public function handle(Customer $customer, string $sessionId): void
    {
        $website = $customer->shop->website;

        if (! $website) {
            return;
        }

        $registrationVisitor = WebsiteVisitor::query()
            ->where('session_id', $sessionId)
            ->where('website_id', $website->id)
            ->first();

        if (! $registrationVisitor) {
            return;
        }

        $visitorIds = WebsiteVisitor::query()
            ->where('visitor_hash', $registrationVisitor->visitor_hash)
            ->where('website_id', $website->id)
            ->pluck('id');

        $lastProductPageView = WebsitePageView::query()
            ->whereIn('website_visitor_id', $visitorIds)
            ->where('page_sub_type', 'product')
            ->whereNotNull('webpage_id')
            ->latest('id')
            ->first();

        $registrationProductId = null;

        if ($lastProductPageView) {
            $webpage = $lastProductPageView->webpage;

            if ($webpage && $webpage->model_type === 'Product' && $webpage->model_id) {
                $registrationProductId = $webpage->model_id;

                $pageUrl = $lastProductPageView->page_url;
                $pagePath = parse_url($pageUrl, PHP_URL_PATH) ?: '/';

                WebsiteConversionEvent::create([
                    'group_id' => $registrationVisitor->group_id,
                    'organisation_id' => $registrationVisitor->organisation_id,
                    'website_visitor_id' => $registrationVisitor->id,
                    'webpage_id' => $lastProductPageView->webpage_id,
                    'website_id' => $website->id,
                    'shop_id' => $website->shop_id,
                    'event_type' => WebsiteConversionEventTypeEnum::REGISTRATION,
                    'product_id' => $registrationProductId,
                    'quantity' => 1,
                    'page_url' => $pageUrl,
                    'page_path' => $pagePath,
                    'event_date' => now()->toDateString(),
                ]);
            }
        }

        CustomerInterest::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'group_id' => $customer->group_id,
                'organisation_id' => $customer->organisation_id,
                'shop_id' => $customer->shop_id,
                'registration_product_id' => $registrationProductId,
            ]
        );

        RecordCustomerProductInterests::dispatch($customer);
    }
}
