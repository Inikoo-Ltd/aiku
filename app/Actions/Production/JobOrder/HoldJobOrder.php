<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 Oct 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\OrgAction;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\JobOrder;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class HoldJobOrder extends OrgAction
{
    public const array KINDS = ['missing_label', 'missing_packaging', 'quality_check', 'other'];

    private ?User $user = null;

    public static function kindLabels(): array
    {
        return [
            'missing_label'     => __('Missing label'),
            'missing_packaging' => __('Missing packaging'),
            'quality_check'     => __('Quality check'),
            'other'             => __('Other'),
        ];
    }

    /**
     * A hold takes the job off the artisans' floor (no new step can be started, HELP-4897) and locks
     * receiving into stock; a session already open can still be closed. Cleared holds stay in
     * data.holds as the job's hold history.
     */
    public function handle(JobOrder $jobOrder, array $modelData): JobOrder
    {
        DB::transaction(function () use ($jobOrder, $modelData) {
            $lockedJobOrder = JobOrder::lockForUpdate()->findOrFail($jobOrder->id);
            $data           = $lockedJobOrder->data;

            if ($modelData['on_hold']) {
                if (!in_array($lockedJobOrder->state, JobOrderStateEnum::open())) {
                    throw ValidationException::withMessages([
                        'on_hold' => __('Only an open job order can be put on hold'),
                    ]);
                }

                $data['hold'] = [
                    'reason'  => $modelData['reason'],
                    'kind'    => $modelData['kind'] ?? 'other',
                    'by'      => $this->user?->contact_name,
                    'user_id' => $this->user?->id,
                    'at'      => now()->toDateTimeString(),
                ];
            } elseif ($hold = Arr::pull($data, 'hold')) {
                $data['holds'][] = array_merge($hold, [
                    'cleared_by'      => $this->user?->contact_name,
                    'cleared_user_id' => $this->user?->id,
                    'cleared_at'      => now()->toDateTimeString(),
                ]);
            }

            $lockedJobOrder->update(['data' => $data]);
        });

        return $jobOrder->refresh();
    }

    public function rules(): array
    {
        return [
            'on_hold' => ['required', 'boolean'],
            'reason'  => ['required_if_accepted:on_hold', 'nullable', 'string', 'max:255'],
            'kind'    => ['sometimes', 'nullable', Rule::in(self::KINDS)],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function action(JobOrder $jobOrder, array $modelData, ?User $user = null): JobOrder
    {
        $this->asAction = true;
        $this->user     = $user;
        $this->initialisationFromProduction($jobOrder->production, $modelData);

        return $this->handle($jobOrder, $this->validatedData);
    }

    public function asController(JobOrder $jobOrder, ActionRequest $request): JobOrder
    {
        $this->user = $request->user();
        $this->initialisationFromProduction($jobOrder->production, $request);

        return $this->handle($jobOrder, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
