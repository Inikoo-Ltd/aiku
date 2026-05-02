<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 14 Oct 2024 14:05:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Bundle\UI;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithPlatformStatusCheck;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dropshipping\CustomerSalesChannel;

class IndexRetinaBundles extends RetinaAction
{
    use WithPlatformStatusCheck;

    private CustomerSalesChannel $customerSalesChannel;


    public function handle(): void
    {
    }

    public function tableStructure(CustomerSalesChannel $parent, ?array $modelOperations = null, $prefix = null): \Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table->withLabelRecord([__('bundle'), __('bundles')]);
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState([
                    'title' => __("No bundles found"),
                    'count' => $parent->number_bundles
                ]);

            $table->column(key: 'image', label: 'Image', canBeHidden: false, searchable: true);
            $table->column(key: 'name', label: __('Bundle'), canBeHidden: false, sortable: true, searchable: true);

            if ($parent->platform->type == PlatformTypeEnum::MANUAL) {
                $table->column(key: 'product_state', label: '', canBeHidden: false);
            }

            if ($parent->status !== CustomerSalesChannelStatusEnum::CLOSED) {
                $table->column(key: 'actions', label: '', canBeHidden: false);
            }

            if ($parent->platform->type !== PlatformTypeEnum::MANUAL) {
                $table->column(key: 'status', label: __('Status'));
                $table->column(key: 'message', label: '', canBeHidden: false);

                $matchesLabel = $parent->platform->name.' '.__('product');

                $table->column(key: 'matches', label: $matchesLabel, canBeHidden: false);
                $table->column(key: 'create_new', label: '', canBeHidden: false);
            }


            $table->column(key: 'delete', label: '', canBeHidden: false);
        };
    }

}
