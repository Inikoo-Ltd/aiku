<?php

namespace App\Actions\Web\Announcement\UI;

use App\Actions\OrgAction;
use App\Models\Announcement;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsController;

class DeliverAnnouncement extends OrgAction
{
    use AsController;

    public function handle(Announcement $announcement): Announcement
    {
        return $announcement;
    }

    public function htmlResponse(Announcement $announcement)
    {
        return Inertia::render(
            'DeliverAnnouncement',
            [
                'announcement_data' => $announcement,
            ]
        );
    }
}
