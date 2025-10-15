<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:21 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio\Logs;

use App\Actions\OrgAction;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use App\Models\Dropshipping\Portfolio;
use App\Models\PlatformPortfolioLogs;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StorePlatformPortfolioLog extends OrgAction
{
    private Portfolio $portfolio;

    public function handle(Portfolio $portfolio, array $modelData): PlatformPortfolioLogs
    {
        data_set($modelData, 'group_id', $portfolio->group_id);
        data_set($modelData, 'organisation_id', $portfolio->organisation_id);
        data_set($modelData, 'shop_id', $portfolio->shop_id);
        data_set($modelData, 'customer_id', $portfolio->customer_id);
        data_set($modelData, 'customer_sales_channel_id', $portfolio->customer_sales_channel_id);
        data_set($modelData, 'portfolio_id', $portfolio->id);
        data_set($modelData, 'platform_id', $portfolio->platform_id);
        data_set($modelData, 'platform_type', $portfolio->customerSalesChannel->platform->type);

        if (!Arr::exists($modelData, 'status')) {
            data_set($modelData, 'status', PlatformPortfolioLogsStatusEnum::PROCESSING);
        }

        if (!Arr::exists($modelData, 'type')) {
            data_set($modelData, 'type', PlatformPortfolioLogsTypeEnum::UPDATE_STOCK);
        }

        return PlatformPortfolioLogs::create($modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return [
            'type'     => ['sometimes', 'string'],
            'status'   => ['sometimes', 'string'],
            'response' => ['sometimes', 'nullable'],
        ];
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): PlatformPortfolioLogs
    {
        $this->initialisationFromShop($portfolio->shop, $request);

        return $this->handle($portfolio, $this->validatedData);
    }

    public function action(Portfolio $portfolio, array $modelData, bool $strict = true, bool $audit = true): PlatformPortfolioLogs
    {
        $this->strict = $strict;
        if (!$audit) {
            PlatformPortfolioLogs::disableAuditing();
        }
        $this->asAction  = true;
        $this->portfolio = $portfolio;
        $this->initialisationFromShop($portfolio->shop, $modelData);

        return $this->handle($portfolio, $this->validatedData);
    }
}
