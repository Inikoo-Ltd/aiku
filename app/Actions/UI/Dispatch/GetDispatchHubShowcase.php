<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 */

namespace App\Actions\UI\Dispatch;

use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchHubShowcase
{
    use AsObject;

    public function handle(Warehouse $warehouse): array
    {
        return [
            GetDispatchHubFulfilmentWidget::run($warehouse),
            GetDispatchHubB2BWidget::run($warehouse),
            GetDispatchHubExternalWidget::run($warehouse),
            GetDispatchHubB2CWidget::run($warehouse),
            GetDispatchHubDropshippingWidget::run($warehouse)
        ];
    }
}
