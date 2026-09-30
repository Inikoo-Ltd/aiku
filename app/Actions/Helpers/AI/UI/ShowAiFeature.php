<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\AI\UI;

use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Models\Helpers\AiTimeSeries;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAiFeature extends OrgAction
{
    use WithInertia;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function asController(string $feature, ActionRequest $request): string
    {
        $this->initialisationFromGroup(app('group'), $request);

        abort_unless(AiTimeSeries::where('feature', $feature)->exists(), 404);

        return $feature;
    }

    public function htmlResponse(string $feature, ActionRequest $request): Response
    {
        $dashboard = ShowAiDashboard::make();
        $title     = $dashboard->featureLabel($feature);

        return Inertia::render(
            'Ai/Feature',
            [
                'breadcrumbs' => $this->getBreadcrumbs($feature, $title),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'model' => __('AI'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-robot'],
                        'title' => $title,
                    ],
                ],
                'description' => $dashboard->featureDescription($feature),
                'spend'       => $dashboard->getFeatures($feature)[0] ?? null,
                'daily'       => $dashboard->getDaily($feature),
                'models'      => $dashboard->getModels($feature),
                'calls'       => $this->getLatestCalls($feature),
            ]
        );
    }

    /**
     * @return array<int, array{created_at: string, model: string, provider: string, prompt_tokens: int, completion_tokens: int, cost: float|null}>
     */
    public function getLatestCalls(string $feature): array
    {
        $dashboard = ShowAiDashboard::make();

        return DB::table('ai_usages')
            ->where('feature', $feature)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($call) => [
                'created_at'        => $call->created_at,
                'model'             => $call->model ? $dashboard->modelLabel($call->model) : '',
                'provider'          => $call->provider,
                'prompt_tokens'     => (int) $call->prompt_tokens,
                'completion_tokens' => (int) $call->completion_tokens,
                'cost'              => $call->cost === null ? null : (float) $call->cost,
            ])
            ->all();
    }

    public function getBreadcrumbs(string $feature, string $label): array
    {
        return array_merge(
            ShowAiDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.ai.features.show',
                            'parameters' => ['feature' => $feature],
                        ],
                        'label' => $label,
                    ],
                ],
            ]
        );
    }
}
