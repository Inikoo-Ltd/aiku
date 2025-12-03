<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag\Hydrators;

use App\Models\Helpers\Tag;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TagHydrateModels implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(int $tagID): string
    {
        return $tagID;
    }

    public function handle(int $tagID): void
    {
        $tag = Tag::find($tagID);
        if (!$tag) {
            return;
        }

        // Count distinct model IDs that have this tag assigned
        $number_models = DB::table('model_has_tags')
            ->where('tag_id', $tag->id)
            ->distinct()
            ->count('model_id');
        $stats = [
            'number_models' => $number_models
        ];


        $tag->updateQuietly($stats);
    }
}
