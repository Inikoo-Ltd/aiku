<?php

namespace App\InertiaTable;

use App\Enums\DateIntervals\DateIntervalEnum;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Response;

class InertiaTable
{
    private string $name = 'default';
    private string $pageName = 'page';
    private array $perPageOptions = [10, 25, 50, 100]; // must be between config/ui.php for min_records_per_page - records_per_page - max_records_per_page
    private Request $request;
    private Collection $columns;
    private Collection $searchInputs;
    private Collection $elementGroups;
    private Collection $radioFilter;
    private array $periodFilters;
    private Collection $filters;
    private string $defaultSort = '';

    private array $title = [];
    private array $betweenDates = [];
    private ?DateIntervalEnum $dateInterval;

    private Collection $emptyState;
    private Collection $modelOperations;
    private Collection $exportLinks;

    private static bool|string $defaultGlobalSearch = false;
    private static array $defaultQueryBuilderConfig = [];

    private array $labelRecord;
    private mixed $footerRows;

    public function __construct(Request $request)
    {
        $this->request         = $request;
        $this->periodFilters   = [];
        $this->columns         = new Collection();
        $this->searchInputs    = new Collection();
        $this->elementGroups   = new Collection();
        $this->radioFilter     = new Collection();
        $this->filters         = new Collection();
        $this->modelOperations = new Collection();
        $this->exportLinks     = new Collection();
        $this->emptyState      = new Collection();
        $this->labelRecord     = [];
        $this->footerRows      = null;
        $this->dateInterval    = null;

        if (static::$defaultGlobalSearch !== false) {
            $this->withGlobalSearch(static::$defaultGlobalSearch);
        }
    }

    /**
     * Set the default global search label for all table instances.
     *
     * @param  bool|string  $label
     *
     * @return void
     * @noinspection PhpUnused
     */
    public static function defaultGlobalSearch(bool|string $label = 'default'): void
    {
        if ($label === 'default') {
            $label = __('Search on table...');
        }

        static::$defaultGlobalSearch = $label !== false ? $label : false;
    }


    private function query(string $key, mixed $default = null): string|array|null
    {
        return $this->request->query(
            $this->name === 'default' ? $key : "{$this->name}_$key",
            $default
        );
    }


    public static function updateQueryBuilderParameters(string $name): void
    {
        if (empty(static::$defaultQueryBuilderConfig)) {
            static::$defaultQueryBuilderConfig = config('query-builder.parameters');
        }

        $newConfig = collect(static::$defaultQueryBuilderConfig)->map(function ($value) use ($name) {
            return "{$name}_$value";
        })->all();

        config(['query-builder.parameters' => $newConfig]);
    }


    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }


    public function pageName(string $pageName): self
    {
        $this->pageName = $pageName;

        return $this;
    }

    public function betweenDates(array $betweenDates): self
    {
        $this->betweenDates = $betweenDates;

        return $this;
    }

    /**
     * Set the date interval to be shown in table columns.
     *
     * @param  DateIntervalEnum  $dateInterval
     *
     * @return self
     * @noinspection PhpUnused
     */
    public function dateInterval(DateIntervalEnum $dateInterval): self
    {
        $this->dateInterval = $dateInterval;

        return $this;
    }

    /**
     * Set the per page options for the table.
     *
     * @param  array  $perPageOptions
     *
     * @return self
     * @noinspection PhpUnused
     */
    public function perPageOptions(array $perPageOptions): self
    {
        $this->perPageOptions = $perPageOptions;

        return $this;
    }


    public function defaultSort(string $defaultSort): self
    {
        $this->defaultSort = $defaultSort;

        return $this;
    }


    protected function getQueryBuilderProps(): array
    {
        return [
            'defaultVisibleToggleableColumns' => $this->columns
                ->reject(function (Column $item) {
                    return $item->hidden;
                })
                ->map(function (Column $item) {
                    return $item->key;
                })
                ->sort()
                ->values(),
            'columns'                         => $this->transformColumns(),
            'hasHiddenColumns'                => $this->columns->filter(function (Column $item) {
                return $item->hidden;
            })->isNotEmpty(),
            'hasToggleableColumns'            => $this->columns->filter(function (Column $item) {
                return $item->canBeHidden;
            })->isNotEmpty(),
            'filters'                         => $this->transformFilters(),
            'hasFilters'                      => $this->filters->isNotEmpty(),
            'hasEnabledFilters'               => $this->filters->filter(function ($filter) {
                return $filter->value;
            })->isNotEmpty(),
            'searchInputs'                    => $searchInputs = $this->transformSearchInputs(),
            'searchInputsWithoutGlobal'       => $searchInputsWithoutGlobal = $searchInputs->where('key', '!=', 'global'),
            'hasSearchInputs'                 => $searchInputsWithoutGlobal->isNotEmpty(),
            'hasSearchInputsWithValue'        => $searchInputsWithoutGlobal->whereNotNull('value')->isNotEmpty(),
            'hasSearchInputsWithoutValue'     => $searchInputsWithoutGlobal->whereNull('value')->isNotEmpty(),
            'globalSearch'                    => $this->searchInputs->firstWhere('key', 'global'),
            'cursor'                          => $this->query('cursor'),
            'sort'                            => $this->query('sort', $this->defaultSort) ?: null,
            'defaultSort'                     => $this->defaultSort,
            'page'                            => Paginator::resolveCurrentPage($this->pageName),
            'pageName'                        => $this->pageName,
            'perPageOptions'                  => $this->perPageOptions,
            'elementGroups'                   => $this->transformElementGroups(),
            'radioFilter'                     => $this->transformRadioFilter(),
            'period_filter'                   => $this->transformPeriodFilters(),
            'modelOperations'                 => $this->modelOperations,
            'exportLinks'                     => $this->exportLinks,
            'emptyState'                      => $this->emptyState,
            'labelRecord'                     => $this->labelRecord,
            'title'                           => $this->title,
            'footerRows'                      => $this->footerRows,
            'betweenDates'                    => $this->betweenDates,
            'dateInterval'                    => $this->dateInterval,
        ];
    }


    protected function transformColumns(): Collection
    {
        $columns = $this->query('columns', []);

        $sort = $this->query('sort', $this->defaultSort);

        return $this->columns->map(function (Column $column) use ($columns, $sort) {
            $key = $column->key;

            if (!empty($columns) && is_array($columns)) {
                $column->hidden = !in_array($key, $columns);
            }

            if ($sort === $key) {
                $column->sorted = 'asc';
            } elseif ($sort === "-$key") {
                $column->sorted = 'desc';
            }

            return $column;
        });
    }


    protected function transformFilters(): Collection
    {
        $filters = $this->filters;

        $queryFilters = $this->query('filter', []);

        if (empty($queryFilters)) {
            return $filters;
        }

        return $filters->map(function (Filter $filter) use ($queryFilters) {
            if (array_key_exists($filter->key, $queryFilters)) {
                $filter->value = $queryFilters[$filter->key];
            }

            return $filter;
        });
    }

    protected function transformElementGroups(): Collection
    {
        $elementGroups = $this->elementGroups;
        $queryElements = $this->query('elements', []);

        if (empty($queryElements)) {
            return $elementGroups;
        }

        return $elementGroups->map(function (ElementGroup $elementGroup) use ($queryElements) {
            if (array_key_exists($elementGroup->key, $queryElements)) {
                $elementGroup->values = explode(',', $queryElements[$elementGroup->key]);
            }

            return $elementGroup;
        });
    }

    protected function transformRadioFilter(): Collection
    {
        $radioFilter   = $this->radioFilter;
        $queryElements = $this->query('radioFilter', '');

        if (empty($queryElements)) {
            return $radioFilter;
        }

        if (is_array($queryElements)) {
            $queryElements = Arr::get($queryElements, 'radio.value', '');
        }

        return $radioFilter->map(function (RadioFilterGroup $elementRadioGroup) use ($queryElements) {
            $elementRadioGroup->value = (string)$queryElements;

            return $elementRadioGroup;
        });
    }

    protected function transformSearchInputs(): Collection
    {
        $filters = $this->query('filter', []);

        if (empty($filters)) {
            return $this->searchInputs;
        }

        return $this->searchInputs->map(function (SearchInput $searchInput) use ($filters) {
            if (array_key_exists($searchInput->key, $filters)) {
                $searchInput->value = $filters[$searchInput->key];
            }

            return $searchInput;
        });
    }

    public function radioFilterGroup(string $key, array $elements, string $default): self
    {
        $this->radioFilter->put(
            $key,
            new RadioFilterGroup(
                key: $key,
                options: $elements,
                value: $default
            )
        );

        return $this;
    }

    public function elementGroup(string $key, array|string $label, array $elements): self
    {
        if (is_string($label)) {
            $label = $label ?: Str::headline($key);
            $key   = $key ?: Str::kebab($label);
        } else {
            $key = $key ?: Str::kebab($label['tooltip']);
        }


        $this->elementGroups->put(
            $key,
            new ElementGroup(
                key: $key,
                label: $label,
                elements: $elements
            )
        );


        return $this;
    }

    public function periodFilters(array $elements): self
    {
        $result = [];

        foreach ($elements as $elementKey => $value) {
            $result[] = new PeriodFilter(
                key: $elementKey,
                label: Arr::get($value, 0),
                date: Arr::get($value, 1)
            );
        }

        $this->periodFilters = $result;

        return $this;
    }

    protected function transformPeriodFilters(): Collection
    {
        $periodFilters = collect($this->periodFilters);
        $queryFilter   = $this->query('period', []);

        if (empty($queryFilter)) {
            return $periodFilters;
        }

        return $periodFilters->map(function (PeriodFilter $periodFilter) use ($queryFilter) {
            if (array_key_exists($periodFilter->key, $queryFilter)) {
                $periodFilter->values = explode(',', $queryFilter[$periodFilter->key]);
            }

            return $periodFilter;
        });
    }

    public function column(
        string $key,
        array|string $label = null,
        string $shortLabel = null,
        array|string $icon = null,
        string $tooltip = null,
        bool $canBeHidden = true,
        bool $hidden = false,
        bool $sortable = false,
        bool $searchable = false,
        string $type = null,
        string $align = null,
        string $className = null,
        bool $isInterval = false
    ): self {
        $this->columns = $this->columns->reject(function (Column $column) use ($key) {
            return $column->key === $key;
        })->push(
            $column = new Column(
                key: $key,
                label: $label,
                shortLabel: $shortLabel,
                icon: $icon,
                tooltip: $tooltip,
                canBeHidden: $canBeHidden,
                hidden: $hidden,
                sortable: $sortable,
                sorted: false,
                type: $type,
                align: $align,
                className: $className,
                isInterval: $isInterval
            )
        )->values();

        if ($searchable) {
            if (is_array($column->label)) {
                if (is_array($column->label['data'])) {
                    $column->label = $column->label['search'];
                } else {
                    $column->label = $column->label['data'];
                }
            }

            $this->searchInput($column->key, $column->label);
        }


        return $this;
    }


    public function withGlobalSearch(string $label = null): self
    {
        return $this->searchInput('global', $label ?: __('Search on table...'));
    }

    public function withModelOperations(array $modelOperations = null): self
    {
        $this->modelOperations = collect($modelOperations);

        return $this;
    }

    public function withExportLinks(array $exportLinks = null): self
    {
        $this->exportLinks = collect($exportLinks);

        return $this;
    }

    public function withEmptyState(array $emptyState = null): self
    {
        $this->emptyState = collect($emptyState);

        return $this;
    }

    public function withFooterRows(mixed $footerRows = null): self
    {
        $this->footerRows = $footerRows;

        return $this;
    }

    public function withLabelRecord(array $labelRecord = null): self
    {
        $this->labelRecord = $labelRecord;

        return $this;
    }

    public function withTitle(string $title, array $leftIcon = null): self
    {
        $this->title = [
            'title'    => $title,
            'leftIcon' => $leftIcon
        ];

        return $this;
    }


    public function searchInput(string $key, string $label = null, string $defaultValue = null): self
    {
        $this->searchInputs = $this->searchInputs->reject(function (SearchInput $searchInput) use ($key) {
            return $searchInput->key === $key;
        })->push(
            new SearchInput(
                key: $key,
                label: $label ?: Str::headline($key),
                value: $defaultValue
            )
        )->values();

        return $this;
    }

    /**
     * Add a select filter to the table.
     *
     * @param  string  $key
     * @param  array  $options
     * @param  string|null  $label
     * @param  string|null  $defaultValue
     * @param  bool  $noFilterOption
     * @param  string|null  $noFilterOptionLabel
     *
     * @return $this
     * @noinspection PhpUnused
     */
    public function selectFilter(string $key, array $options, string $label = null, string $defaultValue = null, bool $noFilterOption = true, string $noFilterOptionLabel = null): self
    {
        $this->filters = $this->filters->reject(function (Filter $filter) use ($key) {
            return $filter->key === $key;
        })->push(
            new Filter(
                key: $key,
                label: $label ?: Str::headline($key),
                options: $options,
                noFilterOption: $noFilterOption,
                noFilterOptionLabel: $noFilterOptionLabel ?: '-',
                type: 'select',
                value: $defaultValue
            )
        )->values();

        return $this;
    }


    public function applyTo(Response $response): Response
    {
        $props = array_merge($response->getQueryBuilderProps(), [
            $this->name => $this->getQueryBuilderProps(),
        ]);

        return $response->with('queryBuilderProps', $props);
    }
}
