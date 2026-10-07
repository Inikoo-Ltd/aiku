<?php

namespace App\Actions\Web\WebsiteDialog\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Web\WebsiteDialogTemplatesResource;
use App\Models\Web\WebsiteDialogTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetWebsiteDialogTemplates extends OrgAction
{
    /**
     * @return Collection<int, WebsiteDialogTemplate>
     */
    public function handle(int $groupId): Collection
    {
        return WebsiteDialogTemplate::where('group_id', $groupId)
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(ActionRequest $request): Collection
    {
        return $this->handle($request->user()->group_id);
    }

    public function jsonResponse(Collection $templates): AnonymousResourceCollection
    {
        return WebsiteDialogTemplatesResource::collection($templates);
    }
}
