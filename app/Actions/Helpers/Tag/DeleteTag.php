<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\OrgAction;
use App\Models\Helpers\Tag;
use Lorisleiva\Actions\ActionRequest;

class DeleteTag extends OrgAction
{
    public function handle(Tag $tag): Tag
    {
        $tag->delete();
        return $tag;
    }

    public function asController(Tag $tag, ActionRequest $request)
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($tag, $this->validatedData);
    }

}
