<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 15 Oct 2025 15:52:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio\Logs;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use App\Models\PlatformPortfolioLogs;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdatePlatformPortfolioLog extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private PlatformPortfolioLogs $platformPortfolioLog;

    public function handle(PlatformPortfolioLogs $platformPortfolioLog, array $modelData): PlatformPortfolioLogs
    {
        if (Arr::exists($modelData, 'status') && is_string($modelData['status'])) {
            $modelData['status'] = PlatformPortfolioLogsStatusEnum::from($modelData['status']);
        }

        if (Arr::exists($modelData, 'type') && is_string($modelData['type'])) {
            $modelData['type'] = PlatformPortfolioLogsTypeEnum::from($modelData['type']);
        }

        return $this->update($platformPortfolioLog, $modelData);
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
        $rules = [
            'type'     => ['sometimes', 'string'],
            'status'   => ['sometimes', 'string'],
            'response' => ['sometimes', 'nullable'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function asController(PlatformPortfolioLogs $platformPortfolioLog, ActionRequest $request): PlatformPortfolioLogs
    {
        $this->initialisationFromShop($platformPortfolioLog->shop, $request);

        return $this->handle($platformPortfolioLog, $this->validatedData);
    }

    public function action(PlatformPortfolioLogs $platformPortfolioLog, array $modelData, bool $strict = true, bool $audit = true): PlatformPortfolioLogs
    {
        $this->strict = $strict;
        if (!$audit) {
            PlatformPortfolioLogs::disableAuditing();
        }
        $this->asAction            = true;
        $this->platformPortfolioLog = $platformPortfolioLog;
        $this->initialisationFromShop($platformPortfolioLog->shop, $modelData);

        return $this->handle($platformPortfolioLog, $this->validatedData);
    }
}
