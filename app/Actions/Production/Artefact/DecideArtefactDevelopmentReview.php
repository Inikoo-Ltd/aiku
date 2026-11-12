<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 23:15:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * HELP-4636: the Production Manager or the accountant decides on a product in development. Accepting or adjusting
 * saves the targets on the steps from today, through SetArtefactStepTarget; once every piece-rate step has a target
 * the product is no longer paid as development. Keeping it on development needs a reason, and it comes back for
 * review once its steps have as many new sessions again.
 */
class DecideArtefactDevelopmentReview extends OrgAction
{
    private Artefact $artefact;

    /**
     * @param  array{decision: string, targets?: array<int|string, float|int|string>, reason?: string|null}  $modelData
     */
    public function handle(Artefact $artefact, array $modelData): Artefact
    {
        return DB::transaction(function () use ($artefact, $modelData) {
            $review = [
                'decision' => $modelData['decision'],
                'reason'   => $modelData['reason'] ?? null,
                'user_id'  => auth()->id(),
                'user'     => auth()->user()?->chatName(),
                'at'       => now()->toIso8601String(),
            ];

            if ($modelData['decision'] !== 'kept') {
                $today = now(SetArtefactStepTarget::timezone($artefact))->toDateString();
                $steps = $artefact->recipeSteps()->get()->keyBy('id');
                foreach ($modelData['targets'] as $stepId => $rate) {
                    SetArtefactStepTarget::run($artefact, $steps[$stepId], $rate, $today);
                }
                $review['targets'] = $modelData['targets'];
            }

            $isEveryStepTargeted = $artefact->recipeSteps()->with('manufactureTask')->get()
                ->filter(fn (ArtefactManufactureTask $step) => $step->manufactureTask->is_piece_rate)
                ->every(fn (ArtefactManufactureTask $step) => $step->standard_rate !== null);

            $artefact->update([
                'data'              => [...$artefact->data, 'development_review' => $review],
                'is_in_development' => $modelData['decision'] === 'kept' || !$isEveryStepTargeted,
            ]);

            AskToReviewArtefactDevelopment::make()->tickDecided($artefact);

            return $artefact;
        });
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
            'decision'  => ['required', Rule::in(['accepted', 'adjusted', 'kept'])],
            'targets'   => ['required_unless:decision,kept', 'array'],
            'targets.*' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'reason'    => ['required_if:decision,kept', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->artefact->is_in_development) {
            $validator->errors()->add('decision', __(':code is not paid as development', ['code' => $this->artefact->code]));

            return;
        }

        if ($this->get('decision') === 'kept') {
            return;
        }

        if ($feedbackTask = StoreArtefactDevelopmentFeedback::openTaskFor($this->artefact)) {
            $validator->errors()->add('decision', __('Feedback from the floor on :code is still open (:reference): fix the recipe and close that task before saving the targets', ['code' => $this->artefact->code, 'reference' => $feedbackTask->reference]));

            return;
        }

        $stepIds = $this->artefact->recipeSteps()->pluck('id');
        foreach (array_keys($this->get('targets', [])) as $stepId) {
            if (!$stepIds->contains((int) $stepId)) {
                $validator->errors()->add('targets', __('A step is no longer in the recipe, reload the page'));

                return;
            }
        }
    }

    public function action(Artefact $artefact, array $modelData): Artefact
    {
        $this->asAction = true;
        $this->artefact = $artefact;
        $this->initialisationFromProduction($artefact->production, $modelData);

        return $this->handle($artefact, $this->validatedData);
    }

    public function asController(Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->artefact = $artefact;
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($artefact, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
