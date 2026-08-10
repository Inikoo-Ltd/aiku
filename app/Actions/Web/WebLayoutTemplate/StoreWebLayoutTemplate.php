<?php

namespace App\Actions\Web\WebLayoutTemplate;

use App\Actions\OrgAction;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;

class StoreWebLayoutTemplate extends OrgAction
{
    public function handle(Webpage $webpage, array $modelData): void
    {
        
    }

    public function rules(): array
    {
        dd($this);
        return [

        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request): void
    {
        $this->initialisationFromGroup($webpage->group, $request);

        $this->handle($webpage, $this->validatedData);
    }
}
