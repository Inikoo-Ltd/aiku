<?php
/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\CRM\Customer;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class DeleteTag extends OrgAction
{
    public function inTradeUnit(TradeUnit $tradeUnit, Tag $tag, ActionRequest $request): Tag
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tag);
    }

    public function inCustomer(Customer $customer, Tag $tag, ActionRequest $request): Tag
    {
        $this->initialisation($customer->organisation, $request);

        return $this->handle($tag);
    }

    public function asController(Organisation $organisation, Tag $tag, ActionRequest $request): ?Tag
    {
        $this->initialisation($organisation, $request);

        try {
            if ($tag->scope === TagScopeEnum::SYSTEM_CUSTOMER) {
                throw ValidationException::withMessages([
                    'scope' => __("You can't delete a system tag."),
                ]);
            }

            return $this->handle($tag);
        } catch (ValidationException $e) {
            request()->session()->flash('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function htmlResponse(Tag $tag=null): void
    {
        if (is_null($tag)) {
            return;
        }

        request()->session()->flash('notification', [
            'status'  => 'success',
            'title'   => __('Success!'),
            'description' => __('Tag successfully deleted.'),
        ]);
    }

    public function handle(Tag $tag): Tag
    {
        if (!empty($tag->tradeUnits())) {
            $tag->tradeUnits()->detach();
        }

        if (!empty($tag->customers())) {
            $tag->customers()->detach();
        }

        $tag->delete();

        return $tag;
    }
}
