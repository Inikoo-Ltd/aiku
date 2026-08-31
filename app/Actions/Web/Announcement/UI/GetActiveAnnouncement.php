<?php

namespace App\Actions\Web\Announcement\UI;

use App\Actions\OrgAction;
use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Http\Resources\Web\AnnouncementsResource;
use App\Models\Web\Announcement;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\QueryBuilder;

class GetActiveAnnouncement extends OrgAction
{
    use AsAction;

    /**
     * Without a position it lists every active announcement of the website; with one it lists
     * only those that would clash with the given position and dates.
     */
    public function handle(Website $website, ?string $position = null, ?Carbon $from = null, ?Carbon $until = null, ?string $excludeUlid = null): Collection
    {
        $queryBuilder = QueryBuilder::for(Announcement::class)->where('website_id', $website->id);

        if ($position) {
            $queryBuilder->clashingWith($website->id, $position, $from ?? now(), $until);
        } else {
            $queryBuilder->where('status', AnnouncementStatusEnum::ACTIVE);
        }

        if ($excludeUlid) {
            $queryBuilder->where('ulid', '!=', $excludeUlid);
        }

        return $queryBuilder->get();
    }

    public function asController(Website $website, ActionRequest $request): Collection
    {
        return $this->handle(
            $website,
            $request->input('position'),
            $request->input('schedule_at') ? Carbon::parse($request->input('schedule_at')) : null,
            $request->input('schedule_finish_at') ? Carbon::parse($request->input('schedule_finish_at')) : null,
            $request->input('exclude')
        );
    }

    public function jsonResponse(Collection $announcements): AnonymousResourceCollection
    {
        return AnnouncementsResource::collection($announcements);
    }
}
