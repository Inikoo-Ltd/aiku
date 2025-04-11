<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Dec 2024 00:37:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Database\Eloquent\SoftDeletes;

trait WithHydrateCommand
{
    use AsAction;

    private string $model;
    private ?string $restriction = null;

    protected function getOrganisationsIds(Command $command): array
    {
        $query = Organisation::query()->whereIn('type', [OrganisationTypeEnum::SHOP->value, OrganisationTypeEnum::DIGITAL_AGENCY->value]);

        $organisationSlugs = $command->argument('organisations');
        if (is_string($organisationSlugs)) {
            $organisationSlugs = [$organisationSlugs];
        }
        if (count($organisationSlugs) > 0) {
            $query->whereIn('slug', $organisationSlugs);
        }

        return $query->get()->pluck('id')->toArray();
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $tableName = (new $this->model())->getTable();

        $query = $this->prepareQuery($tableName, $command);


        $count = $query->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $query->chunk(
            1000,
            function (Collection $modelsData) use ($bar, $command) {
                foreach ($modelsData as $modelId) {
                    $model = (new $this->model());
                    if ($this->hasSoftDeletes($model)) {
                        $instance = $model->withTrashed()->find($modelId->id);
                    } else {
                        $instance = $model->find($modelId->id);
                    }
                    try {
                        $this->handle($instance);
                    } catch (Exception $e) {
                        $command->error($e->getMessage());
                    }
                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $command->info("");

        return 0;
    }

    public function prepareQuery(string $tableName, Command $command): Builder
    {
        $query = DB::table($tableName)->select('id')->orderBy('id');

        if ($command->hasOption('shop') && $command->option('shop')) {
            $shop = Shop::where('slug', $command->option('shop'))->first();
            if ($shop) {
                $query->where('shop_id', $shop->id);
            }
        }


        if ($command->hasOption('slug') && $command->option('slug')) {
            $query->where('slug', $command->option('slug'));
        }
        if ($command->hasArgument('organisations') && $command->argument('organisations')) {
            $this->getOrganisationsIds($command);
            $query->whereIn('organisation_id', $this->getOrganisationsIds($command));
        }

        if ($this->restriction == 'department') {
            $query->where('type', ProductCategoryTypeEnum::DEPARTMENT);
        } elseif ($this->restriction == 'family') {
            $query->where('type', ProductCategoryTypeEnum::FAMILY);
        } elseif ($this->restriction == 'sub_department') {
            $query->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT);
        }

        return $query;
    }


    public function hasSoftDeletes($model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }

}
