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
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class DeleteTag extends OrgAction
{
    protected TradeUnit|null $tradeUnits = null;

    public function inTradeUnit(TradeUnit $tradeUnit, Tag $tag, ActionRequest $request): void
    {
        $this->tradeUnits = $tradeUnit;

        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tag);
    }

    public function asController(Organisation $organisation, Tag $tag, ActionRequest $request): Tag
    {
        $this->initialisation($organisation, $request);

        return $this->handle($tag);
    }

    public function htmlResponse(): void
    {
        request()->session()->flash('notification', [
            'status'  => 'success',
            'title'   => __('Success!'),
            'description' => __('Tag successfully deleted.'),
        ]);
    }

    public function handle(Tag $tag): Tag
    {
        if ($this->tradeUnits) {
            $tag->tradeUnits()->detach();
        }

        $tag->delete();

        return $tag;
    }
}
