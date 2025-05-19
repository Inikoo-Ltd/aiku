<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 22-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\ExternalLink;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Models\SysAdmin\Group;
use App\Models\Web\ExternalLink;

class StoreExternalLink extends OrgAction
{
    use WithWebAuthorisation;


    protected Group $group;

    public function handle(Group $group, array $modelData): ExternalLink
    {

        return  $group->externalLinks()->create($modelData);


    }

    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'ascii',
                'max:10000',
            ],
            'status' => [
                'required',
                'string',
                'max:255'
            ],

        ];
    }


    public function action(Group $group, array $modelData, int $hydratorsDelay = 0, bool $strict = true): ExternalLink|null
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }
}
