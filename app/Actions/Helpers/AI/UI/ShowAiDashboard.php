<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\AI\UI;

use App\Actions\Chat\Reports\GetChatAiAccountUsage;
use App\Actions\Helpers\AI\GetOpenRouterBalance;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\AiTimeSeries;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
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

    /**
     * @var array{today: float, week: float, month: float, year: float, calls_month: int, tokens_month: int}
     */
    private array $totals;

    private const array SPEND_COLUMNS = [
        'today' => TimeSeriesFrequencyEnum::DAILY,
        'week'  => TimeSeriesFrequencyEnum::WEEKLY,
        'month' => TimeSeriesFrequencyEnum::MONTHLY,
        'year'  => TimeSeriesFrequencyEnum::YEARLY,
    ];

    public function handle(Group $group): LengthAwarePaginator
    {
        $query = QueryBuilder::for(AiTimeSeries::class)
            ->join('ai_time_series_records', 'ai_time_series_records.ai_time_series_id', 'ai_time_series.id')
            ->where(function ($where) {
                foreach (self::SPEND_COLUMNS as $frequency) {
                    $where->orWhere(function ($current) use ($frequency) {
                        $current->where('ai_time_series_records.frequency', $frequency->singleLetter())
                            ->where('ai_time_series_records.period', TimeSeriesPeriodCalculator::resolvePeriodFromDate(now(), $frequency)['period']);
                    });
                }
            });

        $totals       = (clone $query)->toBase()->selectRaw($this->spendSql())->first();
        $this->totals = [
            'today'        => (float) $totals->today,
            'week'         => (float) $totals->week,
            'month'        => (float) $totals->month,
            'year'         => (float) $totals->year,
            'calls_month'  => (int) $totals->calls_month,
            'tokens_month' => (int) $totals->tokens_month,
        ];

        return $query
            ->select('ai_time_series.feature')
            ->selectRaw($this->spendSql())
            ->groupBy('ai_time_series.feature')
            ->defaultSort('-year')
            ->allowedSorts(['today', 'week', 'month', 'year', 'calls_month', 'tokens_month'])
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    private function spendSql(): string
    {
        $sql = [];
        foreach (self::SPEND_COLUMNS as $column => $frequency) {
            $sql[] = "coalesce(sum(ai_time_series_records.cost) filter (where ai_time_series_records.frequency = '{$frequency->singleLetter()}'), 0) as $column";
        }
        $monthly = TimeSeriesFrequencyEnum::MONTHLY->singleLetter();
        $sql[]   = "coalesce(sum(ai_time_series_records.number_calls) filter (where ai_time_series_records.frequency = '$monthly'), 0) as calls_month";
        $sql[]   = "coalesce(sum(ai_time_series_records.prompt_tokens + ai_time_series_records.completion_tokens) filter (where ai_time_series_records.frequency = '$monthly'), 0) as tokens_month";

        return implode(', ', $sql);
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table->withLabelRecord([__('feature'), __('features')])
                ->withEmptyState(['title' => __('No AI calls recorded yet')])
                ->withFooterRows(['data' => [['label' => __('Total'), ...$this->totals]]]);

            $table->column(key: 'label', label: __('Used for'), canBeHidden: false);
            $table->column(key: 'today', label: __('Today'), canBeHidden: false, sortable: true, type: 'number');
            $table->column(key: 'week', label: __('This week'), canBeHidden: false, sortable: true, type: 'number');
            $table->column(key: 'month', label: __('This month'), canBeHidden: false, sortable: true, type: 'number');
            $table->column(key: 'year', label: __('This year'), canBeHidden: false, sortable: true, type: 'number');
            $table->column(key: 'calls_month', label: __('Calls this month'), sortable: true, type: 'number');
            $table->column(key: 'tokens_month', label: __('Tokens this month'), sortable: true, type: 'number');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function htmlResponse(LengthAwarePaginator $features, ActionRequest $request): Response
    {
        $features->getCollection()->transform(fn ($row) => [
            'feature'      => $row->feature,
            'label'        => $this->featureLabel($row->feature),
            'today'        => (float) $row->today,
            'week'         => (float) $row->week,
            'month'        => (float) $row->month,
            'year'         => (float) $row->year,
            'calls_month'  => (int) $row->calls_month,
            'tokens_month' => (int) $row->tokens_month,
        ]);

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
                'features'    => JsonResource::collection($features),
                'totals'      => $this->totals,
                'daily'       => $this->getDaily(),
                'models'      => $this->getModels(),
                'claudeUsage' => GetChatAiAccountUsage::run($this->group),
            ]
        )->table($this->tableStructure());
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
            'DetectLanguageWithJev'                  => __('Works out the language a customer writes in, choosing between the likely ones.'),
            'Translate'                              => __('Checks each translation made by the cheaper model and has the weak ones redone by a stronger one.'),
            'GenerateRetinaProductBundleDescription' => __('Writes descriptions for the product bundles dropshipping customers build.'),
            'GenerateRetinaProductBundleTitle'       => __('Writes titles for the product bundles dropshipping customers build.'),
            'GetGeneratedProductDescription'         => __('Writes product descriptions for the catalogue.'),
            'GetGeneratedProductTitle'               => __('Writes product titles for the catalogue.'),
            'AskBotVision'                           => __('Writes alt texts for images.'),
            'SuggestMailshotCopy'                    => __('Suggests the subject, preview text and name of a mailshot from its content.'),
            'CheckWritingQuality'                    => __('Points at generic, padded or unnatural wording in descriptions, newsletters and emails when staff press Check writing.'),
            'PolishWriting'                          => __('Corrects the grammar and flow of a reply staff wrote when they press Polish, then checks nothing was added, lost or changed.'),
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
            'DetectLanguageWithJev'                  => __('Language detection (Jev)'),
            'Translate'                              => __('Translation checks'),
            'GenerateRetinaProductBundleDescription' => __('Product bundle descriptions'),
            'GenerateRetinaProductBundleTitle'       => __('Product bundle titles'),
            'GetGeneratedProductDescription'         => __('Product descriptions'),
            'GetGeneratedProductTitle'               => __('Product titles'),
            'AskBotVision'                           => __('Image alt texts'),
            'SuggestMailshotCopy'                    => __('Mailshot copy suggestions'),
            'CheckWritingQuality'                    => __('Writing checks'),
            'PolishWriting'                          => __('Polished replies'),
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
