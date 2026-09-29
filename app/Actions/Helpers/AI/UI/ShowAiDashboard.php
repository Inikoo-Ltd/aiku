<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\AI\UI;

use App\Actions\Helpers\AI\GetOpenRouterBalance;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAiDashboard extends OrgAction
{
    use WithInertia;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $title = __('AI Dashboard');

        return Inertia::render(
            'Ai/Dashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-robot'],
                        'title' => $title,
                    ],
                ],
                'balance'     => GetOpenRouterBalance::run(),
                'features'    => $this->getFeatures(),
                'daily'       => $this->getDaily(),
                'models'      => $this->getModels(),
            ]
        );
    }

    /**
     * @return array<int, array{feature: string, label: string, today: float, week: float, month: float, year: float, calls_month: int, tokens_month: int}>
     */
    public function getFeatures(?string $feature = null): array
    {
        $columns = [
            'today' => TimeSeriesFrequencyEnum::DAILY,
            'week'  => TimeSeriesFrequencyEnum::WEEKLY,
            'month' => TimeSeriesFrequencyEnum::MONTHLY,
            'year'  => TimeSeriesFrequencyEnum::YEARLY,
        ];

        $features = [];

        foreach ($columns as $column => $frequency) {
            $records = DB::table('ai_time_series_records')
                ->join('ai_time_series', 'ai_time_series.id', 'ai_time_series_records.ai_time_series_id')
                ->where('ai_time_series_records.frequency', $frequency->singleLetter())
                ->where('ai_time_series_records.period', TimeSeriesPeriodCalculator::resolvePeriodFromDate(now(), $frequency)['period'])
                ->when($feature, fn ($query) => $query->where('ai_time_series.feature', $feature))
                ->select('ai_time_series.feature', 'ai_time_series_records.cost', 'ai_time_series_records.number_calls', DB::raw('ai_time_series_records.prompt_tokens + ai_time_series_records.completion_tokens as tokens'))
                ->get();

            foreach ($records as $record) {
                $features[$record->feature] ??= [
                    'feature'      => $record->feature,
                    'label'        => $this->featureLabel($record->feature),
                    'today'        => 0.0,
                    'week'         => 0.0,
                    'month'        => 0.0,
                    'year'         => 0.0,
                    'calls_month'  => 0,
                    'tokens_month' => 0,
                ];
                $features[$record->feature][$column] = (float) $record->cost;

                if ($column === 'month') {
                    $features[$record->feature]['calls_month']  = (int) $record->number_calls;
                    $features[$record->feature]['tokens_month'] = (int) $record->tokens;
                }
            }
        }

        return collect($features)->sortByDesc('year')->values()->all();
    }

    /**
     * @return array<int, array{model: string, label: string, cost: float, calls: int, tokens: int}>
     */
    public function getModels(?string $feature = null): array
    {
        return DB::table('ai_usages')
            ->where('created_at', '>=', now()->startOfMonth())
            ->when($feature, fn ($query) => $query->where('feature', $feature))
            ->selectRaw("coalesce(model, '?') as model, coalesce(sum(cost), 0) as cost, count(*) as calls, sum(prompt_tokens + completion_tokens) as tokens")
            ->groupBy('model')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($row) => ['model' => $row->model, 'label' => $this->modelLabel($row->model), 'cost' => (float) $row->cost, 'calls' => (int) $row->calls, 'tokens' => (int) $row->tokens])
            ->all();
    }

    public function modelLabel(string $model): string
    {
        $model = preg_replace('/-\d{8}$/', '', Str::afterLast($model, '/'));

        if (str_starts_with($model, 'gpt-')) {
            return 'GPT-'.substr($model, 4);
        }

        return ucfirst(str_replace('-', ' ', $model));
    }

    public function featureDescription(string $feature): ?string
    {
        return match ($feature) {
            'SummarizeChatSession'                   => __('Writes the short summary shown on each chat conversation.'),
            'SummarizeLongEmail'                     => __('Shows agents a few lines saying what a long customer email wants.'),
            'FlagUrgentChatRequest'                  => __('Moves requests to cancel an order or change its delivery address to the front of the inbox.'),
            'ClassifyChatSessionNoise'               => __('Decides whether a stranger\'s first message is a real request or spam, so spam never waits in the queue.'),
            'ClassifyChatTurn'                       => __('Works out what each customer message in a chat is about.'),
            'DraftChatReply'                         => __('Drafts replies for chat agents to review and send.'),
            'SendOutOfHoursReply'                    => __('Tells customers who write while the shop is closed when it opens again.'),
            'VerifyChatImageMessage'                 => __('Checks whether images sent in chat were made with AI.'),
            'ChatGPT5Driver'                         => __('Translates texts (products, webpages, emails) into other languages.'),
            'DetectLanguageWithAI'                   => __('Works out the language a customer writes in.'),
            'Translate'                              => __('Checks each translation made by the cheaper model and has the weak ones redone by a stronger one.'),
            'GenerateRetinaProductBundleDescription' => __('Writes descriptions for the product bundles dropshipping customers build.'),
            'GenerateRetinaProductBundleTitle'       => __('Writes titles for the product bundles dropshipping customers build.'),
            'GetGeneratedProductDescription'         => __('Writes product descriptions for the catalogue.'),
            'GetGeneratedProductTitle'               => __('Writes product titles for the catalogue.'),
            'AskBotVision'                           => __('Writes alt texts for images.'),
            'SuggestMailshotCopy'                    => __('Suggests the subject, preview text and name of a mailshot from its content.'),
            'ProposeSearchSynonyms'                  => __('Proposes synonyms for website searches that found no products.'),
            'ReadStockDeliveryInvoice'               => __('Reads supplier invoices attached to stock deliveries.'),
            'SuggestPartnerShoppingList'             => __('Picks products for partner shopping lists from an instruction.'),
            'GenerateAppDeploymentChangeLog'         => __('Writes the change log of each deployment.'),
            default                                  => null,
        };
    }

    public function featureLabel(string $feature): string
    {
        return match ($feature) {
            'SummarizeChatSession'                   => __('Chat summaries'),
            'SummarizeLongEmail'                     => __('Long email summaries'),
            'FlagUrgentChatRequest'                  => __('Urgent chat flags'),
            'ClassifyChatSessionNoise'               => __('Chat spam check'),
            'ClassifyChatTurn'                       => __('Chat message classification'),
            'DraftChatReply'                         => __('Chat reply drafts'),
            'SendOutOfHoursReply'                    => __('Out of hours replies'),
            'VerifyChatImageMessage'                 => __('Chat image checks'),
            'ChatGPT5Driver'                         => __('Translations'),
            'DetectLanguageWithAI'                   => __('Language detection'),
            'Translate'                              => __('Translation checks'),
            'GenerateRetinaProductBundleDescription' => __('Product bundle descriptions'),
            'GenerateRetinaProductBundleTitle'       => __('Product bundle titles'),
            'GetGeneratedProductDescription'         => __('Product descriptions'),
            'GetGeneratedProductTitle'               => __('Product titles'),
            'AskBotVision'                           => __('Image alt texts'),
            'SuggestMailshotCopy'                    => __('Mailshot copy suggestions'),
            'ProposeSearchSynonyms'                  => __('Search synonyms'),
            'ReadStockDeliveryInvoice'               => __('Supplier invoice reading'),
            'SuggestPartnerShoppingList'             => __('Partner shopping list suggestions'),
            'GenerateAppDeploymentChangeLog'         => __('Deployment change logs'),
            'Other'                                  => __('Other'),
            default                                  => ucfirst(strtolower(preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $feature))),
        };
    }

    /**
     * @return array<int, array{day: string, cost: float, calls: int}>
     */
    public function getDaily(?string $feature = null): array
    {
        return DB::table('ai_time_series_records')
            ->join('ai_time_series', 'ai_time_series.id', 'ai_time_series_records.ai_time_series_id')
            ->where('ai_time_series_records.frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
            ->where('ai_time_series_records.from', '>=', now()->subDays(29)->startOfDay())
            ->when($feature, fn ($query) => $query->where('ai_time_series.feature', $feature))
            ->selectRaw('period as day, sum(cost) as cost, sum(number_calls) as calls')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => ['day' => $row->day, 'cost' => (float) $row->cost, 'calls' => (int) $row->calls])
            ->all();
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-robot',
                        'route' => [
                            'name' => 'grp.ai.dashboard',
                        ],
                        'label' => __('AI Dashboard'),
                    ],
                ],
            ]
        );
    }
}
