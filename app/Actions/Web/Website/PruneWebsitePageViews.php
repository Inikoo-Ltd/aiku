<?php

namespace App\Actions\Web\Website;

use App\Models\Web\WebsitePageView;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class PruneWebsitePageViews
{
    use AsAction;

    public const int RETENTION_DAYS = 30;

    public function handle(): void
    {
        WebsitePageView::where('view_date', '<', now()->subDays(self::RETENTION_DAYS)->toDateString())->delete();
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:prune_website_page_views';
    }

    public function asCommand(Command $command): int
    {
        try {
            $this->handle();
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
