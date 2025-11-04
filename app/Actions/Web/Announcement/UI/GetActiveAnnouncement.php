<?php

namespace App\Actions\Web\Announcement\UI;

use App\Actions\OrgAction;
use App\Enums\Portfolio\Announcement\AnnouncementStatusEnum;
use App\Models\Announcement;
use App\Models\Portfolio\PortfolioWebsite;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\QueryBuilder;

class GetActiveAnnouncement extends OrgAction
{
    use AsAction;

    public function handle(PortfolioWebsite $portfolioWebsite): Collection
    {
        $queryBuilder = QueryBuilder::for(Announcement::class);

        return $queryBuilder
            ->where('portfolio_website_id', $portfolioWebsite->id)
            ->where('status', AnnouncementStatusEnum::ACTIVE->value)
            ->get();
    }
}
