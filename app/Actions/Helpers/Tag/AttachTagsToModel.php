<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\Helpers\Tag\Hydrators\TagHydrateModels;
use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\CRM\Customer;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class AttachTagsToModel extends OrgAction
{
    private TradeUnit|Customer $parent;
    private bool $inRetina = false;

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): void
    {
        try {
            $this->parent = $tradeUnit;
            $this->initialisationFromGroup($tradeUnit->group, $request);
            $this->handle($tradeUnit, $this->validatedData, true);

            request()->session()->flash('notification', [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Tags successfully attached.'),
            ]);
        } catch (ValidationException $e) {
            request()->session()->flash('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => $e->getMessage(),
            ]);
        }
    }

    public function inCustomer(Customer $customer, ActionRequest $request): void
    {
        try {
            $this->parent = $customer;
            $this->initialisation($customer->organisation, $request);
            $this->handle($customer, $this->validatedData, true);

            request()->session()->flash('notification', [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Tags successfully attached.'),
            ]);
        } catch (ValidationException $e) {
            request()->session()->flash('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => $e->getMessage(),
            ]);
        }
    }

    public function inRetina(Customer $customer, ActionRequest $request): void
    {
        try {
            $this->inRetina = true;
            $this->parent = $customer;
            $this->initialisation($customer->organisation, $request);
            $this->handle($customer, $this->validatedData, true);

            request()->session()->flash('notification', [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Tags successfully attached.'),
            ]);
        } catch (ValidationException $e) {
            request()->session()->flash('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => $e->getMessage(),
            ]);
        }
    }

    public function action(TradeUnit|Customer $parent, array $modelData): void
    {
        try {
            $this->parent = $parent;

            if ($parent instanceof TradeUnit) {
                $this->initialisationFromGroup($parent->group, $modelData);
            } else {
                $this->initialisation($parent->organisation, $modelData);
            }

            $this->handle($parent, $this->validatedData, true);

            request()->session()->flash('notification', [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Tags successfully attached.'),
            ]);
        } catch (ValidationException $e) {
            request()->session()->flash('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => $e->getMessage(),
            ]);
        }
    }

    public function handle(TradeUnit|Customer $model, array $modelData, $replace = false): void
    {
        if ($replace) {
            $model->tags()->sync($modelData['tags_id']);
        } else {
            $model->tags()->syncWithoutDetaching($modelData['tags_id']);
        }

        $model->refresh();

        foreach ($modelData['tags_id'] as $tagId) {
            $tag = Tag::find($tagId);

            if ($tag) {
                TagHydrateModels::dispatch($tag);
            }
        }
    }

    public function rules(): array
    {
        return [
            'tags_id'   => ['sometimes', 'nullable', 'array'],
            'tags_id.*' => [
                'sometimes',
                'nullable',
                'exists:tags,id',
                function ($attribute, $value, $fail) {
                    if (!empty($value)) {
                        $exist = \DB::table('tags')->where('group_id', $this->group->id);

                        if ($this->parent instanceof TradeUnit) {
                            $exist->where('scope', TagScopeEnum::PRODUCT_PROPERTY);
                        }

                        if ($this->parent instanceof Customer) {
                            $exist->whereIn('scope', [TagScopeEnum::ADMIN_CUSTOMER->value, TagScopeEnum::USER_CUSTOMER->value]);
                        }

                        if ($this->inRetina) {
                            $exist->where('scope', TagScopeEnum::USER_CUSTOMER);
                        }

                        $exist = $exist->where('id', $value)->pluck('id')->toArray();

                        if (empty($exist)) {
                            $fail('Tag with ID ' . $value . ' is not applicable for this model.');
                        }
                    }
                }
            ],
        ];
    }
}
