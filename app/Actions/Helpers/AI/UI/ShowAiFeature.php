<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\AI\UI;

use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithShopPpcPermissions;
use App\Actions\UI\WithInertia;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\AiTimeSeries;
use App\Models\Helpers\AiUsage;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAiFeature extends OrgAction
{
    use WithInertia;
    use WithShopPpcPermissions;

    public const array SHOPPING_ASSISTANT_FEATURES = ['AskShoppingAssistant', 'TagShoppingAssistantConversationChallenges'];

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    private string $feature;

    public function asController(string $feature, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);

        abort_unless(AiTimeSeries::where('feature', $feature)->exists(), 404);

        return $this->handle($feature);
    }

    public function handle(string $feature): LengthAwarePaginator
    {
        $this->feature = $feature;

        return QueryBuilder::for(AiUsage::class)
            ->where('feature', $feature)
            ->select('id', 'created_at', 'provider', 'model', 'prompt_tokens', 'completion_tokens', 'cost')
            ->defaultSort('-created_at')
            ->allowedSorts(['created_at', 'model', 'prompt_tokens', 'completion_tokens', 'cost'])
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table->withLabelRecord([__('call'), __('calls')])
                ->withEmptyState(['title' => __('No calls recorded yet')]);

            $table->column(key: 'created_at', label: __('Latest calls'), canBeHidden: false, sortable: true);
            $table->column(key: 'model', label: __('Model'), canBeHidden: false, sortable: true);
            $table->column(key: 'prompt_tokens', label: __('Tokens in'), sortable: true, type: 'number');
            $table->column(key: 'completion_tokens', label: __('Tokens out'), sortable: true, type: 'number');
            $table->column(key: 'cost', label: __('Cost'), canBeHidden: false, sortable: true, type: 'number');
        };
    }

    public function htmlResponse(LengthAwarePaginator $calls, ActionRequest $request): Response
    {
        $feature   = $this->feature;
        $dashboard = ShowAiDashboard::make();

        $calls->getCollection()->transform(fn (AiUsage $call) => [
            'id'                => $call->id,
            'created_at'        => $call->created_at,
            'model'             => $call->model ? $dashboard->modelLabel($call->model) : '',
            'provider'          => $call->provider,
            'prompt_tokens'     => (int) $call->prompt_tokens,
            'completion_tokens' => (int) $call->completion_tokens,
            'cost'              => $call->cost === null ? null : (float) $call->cost,
        ]);

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
                    'actions' => in_array($feature, self::SHOPPING_ASSISTANT_FEATURES) && $this->canViewGroupShoppingAssistant($request->user()) ? [
                        [
                            'type'  => 'button',
                            'style' => 'tertiary',
                            'icon'  => ['fal', 'fa-hat-wizard'],
                            'label' => __(':name report', ['name' => GetShoppingAssistantSettings::nameInGroup($this->group->id)]),
                            'route' => [
                                'name'       => 'grp.websites.shopping_assistant.show',
                                'parameters' => [],
                            ],
                        ],
                    ] : [],
                ],
                'description' => $dashboard->featureDescription($feature),
                'spend'       => $dashboard->getFeatures($feature)[0] ?? null,
                'daily'       => $dashboard->getDaily($feature),
                'models'      => $dashboard->getModels($feature),
                'calls'       => JsonResource::collection($calls),
            ]
        )->table($this->tableStructure());
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
