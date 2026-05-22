<?php

namespace App\Actions\Catalogue\Review;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateReviewStats;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateReviewStats;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateReviewStats;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateReviewStats;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateReviewStats;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateReviewStats;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class HydrateReviewStats
{
    use AsAction;

    public string $commandSignature = 'hydrate:review-stats
        {scopes?* : Optional scopes: group, shop, product, product-category, master-product-category, master-asset}
        {--group : Hydrate only group review stats}
        {--shop : Hydrate only shop review stats}
        {--product : Hydrate only product review stats}
        {--product-category : Hydrate only product category review stats}
        {--master-product-category : Hydrate only master product category review stats}
        {--master-asset : Hydrate only master asset review stats}
        {--chunk=500 : Chunk size per iteration}';

    public string $commandDescription = 'Recount all review stats across group, shop, product and category scopes';

    public function asCommand(Command $command): int
    {
        $chunkSize = max((int) $command->option('chunk'), 1);
        $selectedScopes = $this->selectedScopes($command);
        $processedPerScope = [];

        if (in_array('group', $selectedScopes, true)) {
            $processedPerScope['group'] = $this->hydrateGroups($command, $chunkSize);
        }

        if (in_array('shop', $selectedScopes, true)) {
            $processedPerScope['shop'] = $this->hydrateShops($command, $chunkSize);
        }

        if (in_array('product', $selectedScopes, true)) {
            $processedPerScope['product'] = $this->hydrateProducts($command, $chunkSize);
        }

        if (in_array('product-category', $selectedScopes, true)) {
            $processedPerScope['product-category'] = $this->hydrateProductCategories($command, $chunkSize);
        }

        if (in_array('master-product-category', $selectedScopes, true)) {
            $processedPerScope['master-product-category'] = $this->hydrateMasterProductCategories($command, $chunkSize);
        }

        if (in_array('master-asset', $selectedScopes, true)) {
            $processedPerScope['master-asset'] = $this->hydrateMasterAssets($command, $chunkSize);
        }

        $command->newLine();
        $command->info('Review stats hydration completed.');
        foreach ($processedPerScope as $scope => $processed) {
            $command->line(sprintf('  - %s: %d processed', $scope, $processed));
        }

        return 0;
    }

    private function selectedScopes(Command $command): array
    {
        $availableScopes = [
            'group',
            'shop',
            'product',
            'product-category',
            'master-product-category',
            'master-asset',
        ];

        $selectedFromOptions = array_values(array_filter(
            $availableScopes,
            fn (string $scope): bool => (bool) $command->option($scope)
        ));

        $requestedScopes = collect((array) $command->argument('scopes'))
            ->map(fn ($scope): string => strtolower((string) $scope))
            ->intersect($availableScopes)
            ->values()
            ->all();

        $selectedScopes = array_values(array_unique(array_merge($selectedFromOptions, $requestedScopes)));

        if ($selectedScopes === []) {
            return $availableScopes;
        }

        $invalidScopes = array_values(array_diff(
            collect((array) $command->argument('scopes'))
                ->map(fn ($scope): string => strtolower((string) $scope))
                ->all(),
            $availableScopes
        ));

        if ($invalidScopes !== []) {
            $command->warn('Ignoring invalid scopes: '.implode(', ', $invalidScopes));
        }

        return $selectedScopes;
    }

    private function hydrateGroups(Command $command, int $chunkSize): int
    {
        return $this->hydrateScope(
            command: $command,
            title: 'Hydrating group review stats',
            query: Group::query()->select('id'),
            chunkSize: $chunkSize,
            hydrate: function (int $id): void {
                GroupHydrateReviewStats::run($id);
            }
        );
    }

    private function hydrateShops(Command $command, int $chunkSize): int
    {
        return $this->hydrateScope(
            command: $command,
            title: 'Hydrating shop review stats',
            query: Shop::query()->select('id'),
            chunkSize: $chunkSize,
            hydrate: function (int $id): void {
                ShopHydrateReviewStats::run($id);
            }
        );
    }

    private function hydrateProducts(Command $command, int $chunkSize): int
    {
        return $this->hydrateScope(
            command: $command,
            title: 'Hydrating product review stats',
            query: Product::query()->select('id'),
            chunkSize: $chunkSize,
            hydrate: function (int $id): void {
                ProductHydrateReviewStats::run($id);
            }
        );
    }

    private function hydrateProductCategories(Command $command, int $chunkSize): int
    {
        return $this->hydrateScope(
            command: $command,
            title: 'Hydrating product category review stats',
            query: ProductCategory::query()->select('id'),
            chunkSize: $chunkSize,
            hydrate: function (int $id): void {
                ProductCategoryHydrateReviewStats::run($id);
            }
        );
    }

    private function hydrateMasterProductCategories(Command $command, int $chunkSize): int
    {
        return $this->hydrateScope(
            command: $command,
            title: 'Hydrating master product category review stats',
            query: MasterProductCategory::query()->select('id'),
            chunkSize: $chunkSize,
            hydrate: function (int $id): void {
                MasterProductCategoryHydrateReviewStats::run($id);
            }
        );
    }

    private function hydrateMasterAssets(Command $command, int $chunkSize): int
    {
        return $this->hydrateScope(
            command: $command,
            title: 'Hydrating master asset review stats',
            query: MasterAsset::query()->select('id'),
            chunkSize: $chunkSize,
            hydrate: function (int $id): void {
                MasterAssetHydrateReviewStats::run($id);
            }
        );
    }

    private function hydrateScope(Command $command, string $title, Builder $query, int $chunkSize, callable $hydrate): int
    {
        $total = (clone $query)->count();
        $command->line(sprintf('%s (%d records)', $title, $total));

        if ($total === 0) {
            return 0;
        }

        ProgressBar::setFormatDefinition(
            'aiku_review_hydrate',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s% | %message%'
        );

        $progress = new ProgressBar($command->getOutput(), $total);
        $progress->setFormat('aiku_review_hydrate');
        $progress->setMessage('starting');
        $progress->start();

        $processed = 0;
        (clone $query)->orderBy('id')->chunkById($chunkSize, function ($models) use ($hydrate, $progress, &$processed): void {
            foreach ($models as $model) {
                $hydrate((int) $model->id);
                $processed++;
                $progress->setMessage('ID '.$model->id);
                $progress->advance();
            }
        });

        $progress->finish();
        $command->newLine();

        return $processed;
    }
}
