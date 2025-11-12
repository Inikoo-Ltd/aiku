<?php

namespace App\Actions\Web\Announcement\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Web\AnnouncementTemplatesResource;
use App\Models\AnnouncementTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAnnouncementTemplates extends OrgAction
{
    use AsAction;

    public function handle(): Collection
    {
        return AnnouncementTemplate::all();
    }

    public function jsonResponse(Collection $templates): AnonymousResourceCollection|JsonResource
    {
        return AnnouncementTemplatesResource::collection($templates);
    }

    public function asController(): Collection
    {
        return $this->handle();
    }
}
