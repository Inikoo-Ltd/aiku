<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Nov 2023 02:29:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Services\ScoutElasticSearch;

use App\Models\Helpers\UniversalSearch;
use IteratorAggregate;
use Laravel\Scout\Builder;
use Traversable;

class EloquentHitsIteratorAggregate implements IteratorAggregate
{
    private array $results;

    /**
     * @var callable|null
     */
    private $callback;

    public function __construct(array $results, ?callable $callback = null)
    {
        $this->results = $results;
        $this->callback = $callback;
    }

    public function getIterator(): Traversable
    {

        $hits = collect();
        if ($this->results['hits']['total']) {
            $hits = $this->results['hits']['hits'];
            $models = collect($hits)->groupBy('_source.__class_name')
                ->map(function ($results, $class) {
                    /** @var UniversalSearch $model */
                    $model = new $class;
                    $model->setKeyType('string');
                    $builder = new Builder($model, '');
                    if (! empty($this->callback)) {
                        $builder->query($this->callback);
                    }

                    return $model->getScoutModelsByIds(
                        $builder,
                        $results->pluck('_id')->all()
                    );
                })
                ->flatten()->keyBy(function ($model) {
                    return get_class($model).'::'.$model->getScoutKey();
                });
            $hits = collect($hits)->map(function ($hit) use ($models) {
                $key = $hit['_source']['__class_name'].'::'.$hit['_id'];

                return isset($models[$key]) ? $models[$key] : null;
            })->filter()->all();
        }

        return new \ArrayIterator((array) $hits);
    }
}
