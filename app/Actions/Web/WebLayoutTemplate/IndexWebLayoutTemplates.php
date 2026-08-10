<?php

/*
 * Author Louis Perez
 * Created on 10-08-2026-14h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\WebLayoutTemplate;

use App\Actions\OrgAction;
use App\Http\Resources\Web\WebLayoutTemplatesResource;
use App\Models\Web\WebLayoutTemplate;
use App\Models\Web\Webpage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class IndexWebLayoutTemplates extends OrgAction
{
    public function handle(Webpage $webpage): Collection
    {
        return WebLayoutTemplate::where('type', class_basename($webpage))
            ->where('scope', $webpage->type)
            ->orderBy('name')
            ->get();
    }

    public function asController(Webpage $webpage, ActionRequest $request): AnonymousResourceCollection
    {
        $this->initialisationFromGroup($webpage->group, $request);

        return WebLayoutTemplatesResource::collection($this->handle($webpage));
    }
}
