<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 23:25:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Production;

use App\Actions\OrgAction;
use App\Models\Production\Production;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * HELP-4636: when a product in development is ready for its targets, and who is asked to review it. The Production
 * Manager or the accountant changes them.
 */
class SetDevelopmentReviewSettings extends OrgAction
{
    /**
     * @param  array<string, mixed>  $modelData
     */
    public function handle(Production $production, array $modelData): Production
    {
        $production->update(['settings' => [
            ...$production->settings,
            'development_review' => array_merge($production->developmentReviewSettings(), $modelData),
        ]]);

        return $production;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            "org-supervisor.{$this->organisation->id}",
            "accounting.{$this->organisation->id}.edit",
        ]);
    }

    public function rules(): array
    {
        return [
            'min_sessions'        => ['required', 'integer', 'min:1', 'max:100'],
            'min_artisans'        => ['required', 'integer', 'min:1', 'max:20'],
            'min_hours'           => ['required', 'numeric', 'min:0', 'max:200'],
            'min_session_minutes' => ['required', 'numeric', 'min:0', 'max:240'],
            'idle_weeks'          => ['required', 'integer', 'min:1', 'max:52'],
            'reviewer_ids'        => ['present', 'array', 'max:5'],
            'reviewer_ids.*'      => ['integer', Rule::exists('users', 'id')->where('group_id', $this->organisation->group_id)->where('status', true)],
        ];
    }

    public function action(Production $production, array $modelData): Production
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
