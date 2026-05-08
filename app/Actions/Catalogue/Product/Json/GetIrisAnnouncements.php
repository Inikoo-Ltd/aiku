<?php

/*
 * author Arya Permana - Kirin
 * created on 04-06-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Models\Web\Announcement;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;

class GetIrisAnnouncements extends IrisAction
{
    public function handle(Website $website): array
    {
        $announcementsTopBar = $this->getAnnouncementsData(
            $website->announcements()
                ->where('status', AnnouncementStatusEnum::ACTIVE)
                ->where(function ($q) {
                    $q->where('settings->position', 'top-bar')
                        ->orWhereNull('settings')
                        ->orWhereRaw("NOT jsonb_exists(settings, 'position')");
                })
                ->get()
        );


        $announcementsBottomMenu = $this->getAnnouncementsData(
            $website->announcements()
                ->where('status', AnnouncementStatusEnum::ACTIVE)
                ->where('settings->position', 'bottom-menu')
                ->get()
        );


        $announcementsTopFooter = $this->getAnnouncementsData(
            $website->announcements()
                ->where('status', AnnouncementStatusEnum::ACTIVE)
                ->where('settings->position', 'top-footer')
                ->get()
        );


        return [
          //  'top_bar'     => $announcementsTopBar,
         //   'bottom_menu' => $announcementsBottomMenu,
            'top_footer'  => $announcementsTopFooter
        ];
    }


    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($this->website);
    }

    public function jsonResponse(array $announcements): array|\Illuminate\Http\Resources\Json\JsonResource
    {
        return $announcements;
    }

    public function getAnnouncementsData($announcementsModels): array
    {
        $announcements = [];
        /** @var Announcement $announcement */
        foreach ($announcementsModels as $announcement) {
            $extractedSettings = $announcement->extractSettings($announcement->settings);

            $announcements[] = [
                'ulid'                 => $announcement->ulid,
                'code'                 => $announcement->code,
                'name'                 => $announcement->name,
                'status'               => $announcement->status->statusIcon()[$announcement->status->value],
                'state_icon'           => $announcement->state->stateIcon()[$announcement->state->value],
                'show_pages'           => $extractedSettings['show_pages'],
                'hide_pages'           => $extractedSettings['hide_pages'],
                'container_properties' => $announcement->container_properties,
                'created_at'           => $announcement->created_at,
                'fields'               => $announcement->fields,
                'id'                   => $announcement->id,
                'icon'                 => $announcement->icon,
                'schedule_at'          => $announcement->schedule_at,
                'schedule_finish_at'   => $announcement->schedule_finish_at,
                'settings'             => $announcement->settings,
                'state'                => $announcement->state,
                'template_code'        => $announcement->template_code,
                'ready_at'             => $announcement->ready_at
            ];
        }

        return $announcements;
    }

}
