<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Support;

use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * An agent organisation shows one page per supplier or supplier product, whatever the number of
 * organisations that buy it. The page holds the buying organisation the user picked (?organisation=es)
 * or none (All), the org rows of every buying organisation, and the row the page reads per-organisation
 * fields from: the selected organisation's, or in All the first buying organisation's (AW).
 */
readonly class AgentOrganisationSelection
{
    public const string ATTRIBUTE = 'agentOrganisationSelection';

    /**
     * @param  Collection<int, Model>  $rows
     */
    public function __construct(
        public Collection $rows,
        public Model $pageRow,
        public ?Organisation $selected,
        public string $slug,
    ) {
    }

    public static function fromRequest(?Request $request = null): ?self
    {
        $selection = ($request ?? request())->attributes->get(self::ATTRIBUTE);

        return $selection instanceof self ? $selection : null;
    }

    /**
     * @return Collection<int, Model>
     */
    public function selectedRows(): Collection
    {
        return $this->selected ? Collection::make([$this->pageRow]) : $this->rows;
    }

    /**
     * @return Collection<int, Organisation>
     */
    public function organisations(): Collection
    {
        return Collection::make($this->rows->pluck('organisation')->unique('id')->values()->all());
    }

    /**
     * @return array<string, string>
     */
    public function query(): array
    {
        return $this->selected ? ['organisation' => $this->selected->slug] : [];
    }

    /**
     * A route the browser follows with route(): the selected organisation travels as ?organisation=,
     * which can not be a plain parameter because the route already has {organisation}.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{name: string, parameters: array<string, mixed>}
     */
    public function route(string $name, array $parameters): array
    {
        return [
            'name'       => $name,
            'parameters' => $this->selected ? array_merge($parameters, ['_query' => $this->query()]) : $parameters,
        ];
    }

    public function label(string $label): string
    {
        return $label.' ('.$this->pageRow->organisation->code.')';
    }

    /**
     * @return array{current: string, options: array<int, array{value: string, label: string}>}
     */
    public function selector(): array
    {
        return [
            'current' => $this->selected?->slug ?? 'all',
            'options' => [
                ['value' => 'all', 'label' => __('All')],
                ...$this->organisations()->map(fn (Organisation $organisation) => ['value' => $organisation->slug, 'label' => $organisation->code])->all(),
            ],
        ];
    }
}
