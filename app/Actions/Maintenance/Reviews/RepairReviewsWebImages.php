<?php

namespace App\Actions\Maintenance\Reviews;

use App\Actions\Reviews\Traits\HasReviewCommonLogic;
use App\Models\Helpers\Media;
use App\Models\Reviews\Review;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairReviewsWebImages
{
    use AsAction;
    use HasReviewCommonLogic;

    public function handle(Review $review)
    {
        $this->storeReviewWebImages($review);
    }

    public string $commandSignature = 'repair:review_web_images';

    public function asCommand(Command $command)
    {
        $command->info('Repairing Media Model Type');
        $reviewIdsFromMedia = Media::where('model_type', (new Review())->getMorphClass())
            ->distinct()
            ->pluck('model_id')
            ->all();

        $reviews = Review::whereIn('id', $reviewIdsFromMedia);
        $total = $reviews->count();
        $i = 1;

        foreach ($reviews->get() as $review) {
            $this->handle($review);

            $command->info("Repaired Review [$review->id] ($i/$total)");
            $i++;
        }
    }
}
