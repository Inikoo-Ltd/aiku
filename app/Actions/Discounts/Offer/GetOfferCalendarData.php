<?php

namespace App\Actions\Discounts\Offer;

use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Enums\Discounts\OfferCampaign\OfferCampaignOffersStateEnum;
use App\Models\Discounts\Offer;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOfferCalendarData
{
    use AsObject;

    public function handle(Organisation $organisation, int $year, ?OfferCampaignTypeEnum $campaignType = null, ?int $month = null, ?int $limit = null, ?int $shopId = null): array
    {
        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::create($year, 12, 31)->endOfDay();
        $startOfPeriod = $month && $month >= 1 && $month <= 12
            ? Carbon::create($year, $month, 1)->startOfDay()
            : $startOfYear->copy();
        $endOfPeriod = $month && $month >= 1 && $month <= 12
            ? $startOfPeriod->copy()->addMonth()
            : $startOfYear->copy()->addYear();

        $query = Offer::query()
            ->where('organisation_id', $organisation->id)
            ->where('status', true)
            ->where('state', OfferStateEnum::ACTIVE->value)
            ->whereNotNull('start_at')
            ->whereHas('offerCampaign', function ($q) use ($campaignType) {
                $q->where('status', true)
                    ->where('offers_state', OfferCampaignOffersStateEnum::ACTIVE->value);

                if ($campaignType) {
                    $q->where('type', $campaignType->value);
                }
            });

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        $query
            ->where('start_at', '<', $endOfPeriod)
            ->where(function ($q) use ($startOfPeriod) {
                $q->where('end_at', '>=', $startOfPeriod)
                    ->orWhereNull('end_at');
            });

        $totalOffers = (clone $query)->count();

        if ($limit && $limit > 0) {
            $query->limit($limit);
        }

        $offers = $query
            ->with([
                'stats' => function ($q) {
                    $q->select(
                        'offer_id',
                        'first_used_at',
                        'last_used_at',
                        'number_customers',
                        'number_orders',
                        'number_invoices',
                        'number_delivery_notes',
                        'amount',
                        'org_amount',
                        'grp_amount'
                    );
                },
                'offerCampaign' => function ($q) {
                    $q->select(
                        'id',
                        'shop_id',
                        'slug',
                        'code',
                        'name',
                        'type',
                        'status',
                        'offers_state',
                        'start_at',
                        'finish_at'
                    );
                },
                'offerCampaign.shop' => function ($q) {
                    $q->select('id', 'organisation_id', 'slug', 'code', 'name');
                },
            ])
            ->get();

        $loadedOffers = $offers->count();

        [$ranges, $holidays] = $this->buildRangesAndHolidays(
            $offers,
            $organisation,
            $startOfYear,
            $endOfYear
        );

        return [
            'title'   => __('Offer Calendar'),
            'year'    => $year,
            'month'   => $month,
            'holidays' => array_values($holidays),
            'holidayRanges' => $ranges,
            'holidayYearPeriod' => null,
            'allHolidayYears'   => [],
            'defaultPeriod'     => [
                'start_date' => $startOfPeriod->toDateString(),
                'end_date'   => $endOfPeriod->toDateString(),
            ],
            'pagination' => [
                'total'   => $totalOffers,
                'loaded'  => $loadedOffers,
                'limit'   => $limit,
                'hasMore' => $loadedOffers < $totalOffers,
            ],
        ];
    }

    /**
     * @param Collection<int, Offer> $offers
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, array<string, string>>}
     */
    protected function buildRangesAndHolidays(Collection $offers, Organisation $organisation, Carbon $startOfYear, Carbon $endOfYear): array
    {
        $ranges = [];
        $holidays = [];
        $previewStart = $startOfYear->copy()->subYears(2)->startOfDay();
        $previewEnd = $endOfYear->copy()->addYears(2)->endOfDay();

        foreach ($offers as $offer) {
            $startAt = $offer->start_at?->copy()->startOfDay();
            $rangeEndAt = $offer->end_at?->copy()->endOfDay() ?? $previewEnd->copy();
            $holidayEndAt = $offer->end_at?->copy()->endOfDay() ?? $endOfYear->copy();

            if (!$startAt) {
                continue;
            }

            if ($startAt->lt($previewStart)) {
                $fromDate = $previewStart->copy();
            } else {
                $fromDate = $startAt;
            }

            if ($rangeEndAt->gt($previewEnd)) {
                $toDate = $previewEnd->copy();
            } else {
                $toDate = $rangeEndAt;
            }

            if ($fromDate->gt($toDate)) {
                continue;
            }

            $campaign = $offer->offerCampaign;
            $shop = $campaign?->shop;
            $offerStats = $offer->stats;

            $label = $campaign?->name ?? $offer->name ?? $offer->code;

            $range = [
                'from'           => $fromDate->toDateString(),
                'to'             => $toDate->toDateString(),
                'raw_from'       => $startAt->toDateString(),
                'raw_to'         => $offer->end_at?->copy()->endOfDay()?->toDateString(),
                'label'          => $label,
                'offer_code'     => $offer->code,
                'campaign_code'  => $campaign?->code,
                'campaign_type'  => $campaign?->type?->value,
                'duration_label' => $offer->duration ? (OfferDurationEnum::labels()[$offer->duration->value] ?? $offer->duration->value) : null,
                'shop_code'      => $shop?->code,
                'shop_name'      => $shop?->name,
                'state'          => $offer->state->value,
                'status'         => $offer->status,
                'details'        => [
                    'offer' => [
                        'id'               => $offer->id,
                        'slug'             => $offer->slug,
                        'code'             => $offer->code,
                        'name'             => $offer->name,
                        'type'             => $offer->type,
                        'state'            => $offer->state->value,
                        'status'           => $offer->status,
                        'duration'         => $offer->duration?->value,
                        'duration_label'   => $offer->duration ? (OfferDurationEnum::labels()[$offer->duration->value] ?? $offer->duration->value) : null,
                        'trigger_type'     => $offer->trigger_type,
                        'trigger_sub_type' => $offer->trigger_sub_type,
                        'is_discretionary' => $offer->is_discretionary,
                        'is_locked'        => $offer->is_locked,
                        'start_at'         => $offer->start_at?->toDateTimeString(),
                        'end_at'           => $offer->end_at?->toDateTimeString(),
                        'label'            => $offer->label,
                    ],
                    'campaign' => [
                        'slug'         => $campaign?->slug,
                        'code'         => $campaign?->code,
                        'name'         => $campaign?->name,
                        'type'         => $campaign?->type?->value,
                        'status'       => $campaign?->status,
                        'offers_state' => $campaign?->offers_state?->value,
                        'start_at'     => $campaign?->start_at,
                        'finish_at'    => $campaign?->finish_at,
                    ],
                    'shop' => [
                        'slug' => $shop?->slug,
                        'code' => $shop?->code,
                        'name' => $shop?->name,
                    ],
                    'stats' => [
                        'offer' => [
                            'first_used_at'         => $offerStats?->first_used_at,
                            'last_used_at'          => $offerStats?->last_used_at,
                            'number_customers'      => (int) ($offerStats?->number_customers ?? 0),
                            'number_orders'         => (int) ($offerStats?->number_orders ?? 0),
                            'number_invoices'       => (int) ($offerStats?->number_invoices ?? 0),
                            'number_delivery_notes' => (int) ($offerStats?->number_delivery_notes ?? 0),
                            'amount'                => (string) ($offerStats?->amount ?? '0'),
                            'org_amount'            => (string) ($offerStats?->org_amount ?? '0'),
                            'grp_amount'            => (string) ($offerStats?->grp_amount ?? '0'),
                        ],
                    ],
                ],
                'route'          => [
                    'name'       => 'grp.org.shops.show.discounts.campaigns.show',
                    'parameters' => [
                        'organisation'  => $organisation->slug,
                        'shop'          => $shop?->slug ?? 'unknown',
                        'offerCampaign' => $campaign?->slug,
                    ],
                ],
            ];

            $ranges[] = $range;

            $holidayFromDate = $startAt->lt($startOfYear) ? $startOfYear->copy() : $startAt->copy();
            $holidayToDate = $holidayEndAt->gt($endOfYear) ? $endOfYear->copy() : $holidayEndAt->copy();

            if ($holidayFromDate->gt($holidayToDate)) {
                continue;
            }

            $current = $holidayFromDate->copy();

            while ($current->lte($holidayToDate)) {
                $date = $current->toDateString();

                if (!isset($holidays[$date])) {
                    $holidays[$date] = [
                        'date'  => $date,
                        'label' => $label,
                    ];
                } else {
                    $holidays[$date]['label'] = $holidays[$date]['label'].', '.$label;
                }

                $current->addDay();
            }
        }

        return [$ranges, $holidays];
    }
}
