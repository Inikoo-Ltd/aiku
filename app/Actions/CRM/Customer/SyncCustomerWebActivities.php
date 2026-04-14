<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\CRM\Customer\CustomerWebActivityTypeEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerWebActivity;
use App\Models\Web\WebsiteConversionEvent;
use App\Models\Web\WebsitePageView;
use App\Models\Web\WebsiteVisitor;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncCustomerWebActivities implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateCommand;

    public string $jobQueue = 'analytics';
    public int $jobTimeout = 120;
    public int $jobTries = 1;

    public string $commandSignature = 'sync:customer-web-activities {organisations?*} {--S|shop= shop slug} {--s|slug=} {--date= : Specific date to sync (Y-m-d), defaults to full 12-month resync}';

    public function __construct()
    {
        $this->model = Customer::class;
    }

    public function getJobUniqueId(Customer $customer, ?Carbon $date = null): string
    {
        return $customer->id.':'.($date?->toDateString() ?? 'full');
    }

    public function handle(Customer $customer, ?Carbon $date = null): void
    {
        $webUserIds = $customer->webUsers()->pluck('id');

        if ($webUserIds->isEmpty()) {
            return;
        }

        if ($date !== null) {
            $this->syncForDate($customer, $webUserIds, $date);
        } else {
            $this->syncFull($customer, $webUserIds);
        }
    }

    private function syncFull(Customer $customer, Collection $webUserIds): void
    {
        $cutoff = now()->subMonths(12)->startOfDay();

        $visitorIds = WebsiteVisitor::whereIn('web_user_id', $webUserIds)
            ->where('last_seen_at', '>=', $cutoff)
            ->pluck('id', 'id');

        if ($visitorIds->isEmpty()) {
            return;
        }

        CustomerWebActivity::where('customer_id', $customer->id)->delete();

        $visitorWebUserMap = WebsiteVisitor::whereIn('id', $visitorIds->keys())
            ->pluck('web_user_id', 'id');

        $this->syncPageViews($customer, $visitorIds->keys()->all(), $visitorWebUserMap, $cutoff->toDateString(), null);
        $this->syncConversionEvents($customer, $visitorIds->keys()->all(), $visitorWebUserMap, $cutoff->toDateString(), null);
    }

    private function syncForDate(Customer $customer, Collection $webUserIds, Carbon $date): void
    {
        $dateString = $date->toDateString();

        $visitorIds = WebsiteVisitor::whereIn('web_user_id', $webUserIds)
            ->whereDate('last_seen_at', '>=', $dateString)
            ->pluck('id', 'id');

        if ($visitorIds->isEmpty()) {
            return;
        }

        CustomerWebActivity::where('customer_id', $customer->id)
            ->whereDate('activity_date', $dateString)
            ->delete();

        $visitorWebUserMap = WebsiteVisitor::whereIn('id', $visitorIds->keys())
            ->pluck('web_user_id', 'id');

        $this->syncPageViews($customer, $visitorIds->keys()->all(), $visitorWebUserMap, $dateString, $dateString);
        $this->syncConversionEvents($customer, $visitorIds->keys()->all(), $visitorWebUserMap, $dateString, $dateString);
    }

    private function syncPageViews(Customer $customer, array $visitorIds, Collection $visitorWebUserMap, string $from, ?string $to): void
    {
        $query = WebsitePageView::whereIn('website_visitor_id', $visitorIds)
            ->where('view_date', '>=', $from);

        if ($to !== null) {
            $query->where('view_date', '<=', $to);
        }

        $query->chunkById(500, function ($pageViews) use ($customer, $visitorWebUserMap) {
            $rows = $pageViews->map(function (WebsitePageView $pv) use ($customer, $visitorWebUserMap) {
                $activityType = $pv->page_sub_type === 'product'
                    ? CustomerWebActivityTypeEnum::ProductView->value
                    : CustomerWebActivityTypeEnum::PageView->value;

                return [
                    'group_id'           => $customer->group_id,
                    'organisation_id'    => $customer->organisation_id,
                    'shop_id'            => $customer->shop_id,
                    'website_id'         => $pv->website_id,
                    'customer_id'        => $customer->id,
                    'web_user_id'        => $visitorWebUserMap[$pv->website_visitor_id] ?? null,
                    'website_visitor_id' => $pv->website_visitor_id,
                    'activity_type'      => $activityType,
                    'page_url'           => mb_substr($pv->page_url, 0, 4096),
                    'page_path'          => mb_substr($pv->page_path, 0, 4096),
                    'page_type'          => $pv->page_type,
                    'page_sub_type'      => $pv->page_sub_type,
                    'webpage_id'         => $pv->webpage_id,
                    'product_id'         => null,
                    'quantity'           => 0,
                    'duration_seconds'   => $pv->duration_seconds,
                    'activity_date'      => $pv->view_date,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
            })->all();

            CustomerWebActivity::insert($rows);
        });
    }

    private function syncConversionEvents(Customer $customer, array $visitorIds, Collection $visitorWebUserMap, string $from, ?string $to): void
    {
        $query = WebsiteConversionEvent::whereIn('website_visitor_id', $visitorIds)
            ->where('event_date', '>=', $from);

        if ($to !== null) {
            $query->where('event_date', '<=', $to);
        }

        $query->chunkById(500, function ($events) use ($customer, $visitorWebUserMap) {
            $rows = $events->map(function (WebsiteConversionEvent $event) use ($customer, $visitorWebUserMap) {
                return [
                    'group_id'           => $customer->group_id,
                    'organisation_id'    => $customer->organisation_id,
                    'shop_id'            => $customer->shop_id,
                    'website_id'         => $event->website_id,
                    'customer_id'        => $customer->id,
                    'web_user_id'        => $visitorWebUserMap[$event->website_visitor_id] ?? null,
                    'website_visitor_id' => $event->website_visitor_id,
                    'activity_type'      => CustomerWebActivityTypeEnum::AddToBasket->value,
                    'page_url'           => mb_substr($event->page_url, 0, 4096),
                    'page_path'          => mb_substr($event->page_path, 0, 4096),
                    'page_type'          => null,
                    'page_sub_type'      => null,
                    'webpage_id'         => $event->webpage_id,
                    'product_id'         => $event->product_id,
                    'quantity'           => $event->quantity,
                    'duration_seconds'   => 0,
                    'activity_date'      => $event->event_date,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
            })->all();

            CustomerWebActivity::insert($rows);
        });
    }
}
