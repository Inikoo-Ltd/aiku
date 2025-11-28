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
use App\Http\Resources\Web\AnnouncementResource;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;

class GetIrisAnnouncements extends IrisAction
{
    public function handle(Website $website): array
    {
        $announcementsTopBar = AnnouncementResource::collection(
            $website->announcements()
                ->where('status', AnnouncementStatusEnum::ACTIVE)
                ->where(function ($q) {
                    $q->where('settings->position', 'top-bar')
                        ->orWhereNull('settings')
                        ->orWhereRaw("NOT jsonb_exists(settings, 'position')");
                })
                ->get()
        )->toArray(request());



        $announcementsBottomMenu = AnnouncementResource::collection(
            $website->announcements()
                ->where('status', AnnouncementStatusEnum::ACTIVE)
                ->where('settings->position', 'bottom-menu')
                ->get()
        )->toArray(request());



        $announcementsTopFooter = AnnouncementResource::collection(
            $website->announcements()
                ->where('status', AnnouncementStatusEnum::ACTIVE)
                ->where('settings->position', 'top-footer')
                ->get()
        )->toArray(request());

        return [
            'top_bar' => $announcementsTopBar,
            'bottom_menu' => $announcementsBottomMenu,
            'top_footer' => $announcementsTopFooter
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

}
