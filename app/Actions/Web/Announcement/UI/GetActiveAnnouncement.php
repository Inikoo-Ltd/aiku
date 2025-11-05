<?php

namespace App\Actions\Web\Announcement\UI;

use App\Actions\OrgAction;
use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Http\Resources\Web\AnnouncementsResource;
use App\Models\Announcement;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\QueryBuilder;

class GetActiveAnnouncement extends OrgAction
{
    use AsAction;

    public function handle(Website $website): Collection
    {
        $queryBuilder = QueryBuilder::for(Announcement::class);

        return $queryBuilder
            ->where('website_id', $website->id)
            ->where('status', AnnouncementStatusEnum::ACTIVE)
            ->get();
    }

    public function jsonResponse(Collection $announcements): AnonymousResourceCollection
    {
        return AnnouncementsResource::collection($announcements);
    }
}
