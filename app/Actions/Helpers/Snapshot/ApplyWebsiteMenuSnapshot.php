<?php

namespace App\Actions\Helpers\Snapshot;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\Website\PublishWebsiteMarginal;
use App\Models\Helpers\Snapshot;
use App\Models\Web\Website;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class ApplyWebsiteMenuSnapshot extends OrgAction
{
    use WithWebEditAuthorisation;
    private $targetScope = 'published';

    public function handle(Snapshot $snapshot): Snapshot
    {
        $parent = $snapshot->parent;

        if (!$parent instanceof Website) {
            throw ValidationException::withMessages([
                'message' => __('Invalid snapshot were given'),
            ]);
        }

        $marginal = $snapshot->scope->value;
        $marginalCapitalized = ucfirst($marginal);

        if ($this->targetScope == 'published') {
            PublishWebsiteMarginal::run($parent, $marginal, [
                'comment'           => "Set snapshot {$snapshot->published_at} as Live",
                'publisher_id'      => request()->user()?->id,
                'publisher_type'    => class_basename(request()->user()),
                'layout'            => $snapshot->layout,
            ]);
        } else {
            $parent->{"unpublished{$marginalCapitalized}Snapshot"}->updateQuietly([
                'layout'            => [
                    "$marginal" => $snapshot->layout
                ]
            ]);
        }


        return $snapshot;
    }

    public function asUnpublished(Snapshot $snapshot, ActionRequest $request): Snapshot
    {
        $this->targetScope = 'unpublished';
        $this->initialisationFromShop($snapshot->parent->shop, $request);

        return $this->handle($snapshot, $this->validatedData);
    }

    public function asController(Snapshot $snapshot, ActionRequest $request): Snapshot
    {
        $this->initialisationFromShop($snapshot->parent->shop, $request);

        return $this->handle($snapshot, $this->validatedData);
    }
}
