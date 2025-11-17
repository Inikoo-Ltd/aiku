<?php

namespace App\Actions\Web\Announcement\UI;

use App\Actions\OrgAction;
use App\Models\Announcement;
use Lorisleiva\Actions\Concerns\AsController;
use Inertia\Inertia;

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
                'announcement_data' => $announcement
            ]
        );
    }
}
