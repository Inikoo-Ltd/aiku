<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Jun 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews;

use App\Actions\Reviews\Traits\HasReviewHydrators;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\HumanResources\WorkSchedule;
use App\Models\Reviews\Review;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class AutoPublishReviews
{
    use AsAction;
    use HasReviewHydrators;

    public string $jobQueue        = 'long-running';
    public string $commandSignature = 'reviews:auto-publish';

    public function handle(): void
    {
        Review::query()
            ->where('state', ReviewStateEnum::WAITING_APPROVAL)
            ->whereNotNull('auto_approve_at')
            ->where('auto_approve_at', '<=', Carbon::now()->utc())
            ->with(['shop.organisation', 'shop.timezone'])
            ->cursor()
            ->each(function (Review $review) {
                $effective = $review->shop->getEffectiveWorkSchedule();
                $schedule  = $effective['schedule'];
                $timezone  = $effective['timezone'];

                if ($schedule instanceof WorkSchedule && !$schedule->isOpenNow($timezone)) {
                    return;
                }

                $review->update([
                    'state'         => ReviewStateEnum::PUBLISHED,
                    'review_status' => ReviewStatusEnum::APPROVED,
                    'approved'      => true,
                    'auto_approved' => true,
                    'published_at'  => now(),
                ]);

                $this->reviewHydrators($review);
            });
    }

    public function asCommand(): void
    {
        $this->handle();
    }
}
