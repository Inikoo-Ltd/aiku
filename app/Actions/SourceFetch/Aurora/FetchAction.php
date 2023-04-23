<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 05 Sept 2022 00:35:52 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\SourceFetch\Aurora;

use App\Actions\WithTenantsArgument;
use App\Actions\WithTenantSource;
use App\Models\Marketing\Shop;
use App\Models\Tenancy\Tenant;
use App\Services\Tenant\SourceTenantService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class FetchAction
{
    use AsAction;
    use WithTenantsArgument;
    use WithTenantSource;

    protected int $counter = 0;

    protected ?ProgressBar $progressBar;
    protected ?Shop $shop;
    protected array $with;
    protected bool $onlyNew = false;
    private ?Tenant $tenant;

    public function __construct()
    {
        $this->progressBar = null;
        $this->shop        = null;
        $this->with        = [];
    }

    public function handle(SourceTenantService $tenantSource, int $tenantSourceId): ?Model
    {
        return null;
    }

    public function getModelsQuery(): ?Builder
    {
        return null;
    }
    public function fetchAll(SourceTenantService $tenantSource, Command $command = null): void
    {
        $this->getModelsQuery()->chunk(10000, function ($chunkedData) use ($command, $tenantSource) {
            foreach ($chunkedData as $auroraData) {
                if ($command && $command->getOutput()->isDebug()) {
                    $command->line("Fetching: ".$auroraData->{'source_id'});
                }
                $model = $this->handle($tenantSource, $auroraData->{'source_id'});
                unset($model);
                $this->progressBar?->advance();
            }
        });
    }

    public function fetchSome(SourceTenantService $tenantSource, array $tenantIds): void
    {
        foreach ($tenantIds as $sourceId) {
            $this->handle($tenantSource, $sourceId);
        }
    }

    public function count(): ?int
    {
        return null;
    }


    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Exception
     */
    public function asCommand(Command $command): int
    {
        $tenants  = $this->getTenants($command);
        $exitCode = 0;


        foreach ($tenants as $tenant) {
            $result = (int)$tenant->execute(function () use ($command, $tenant) {
                if (in_array($command->getName(), ['fetch:customers', 'fetch:web-users', 'fetch:products']) and $command->option('shop')) {
                    $this->shop = Shop::where('slug', $command->option('shop'))->firstOrFail();
                }


                if (in_array($command->getName(), ['fetch:stocks','fetch:orders', 'fetch:invoices', 'fetch:customers', 'fetch:delivery-notes'])) {
                    $this->onlyNew = (bool)$command->option('only_new');
                }


                //with
                if ($command->getName() == 'fetch:customers') {
                    $this->with = $command->option('with');
                }


                $tenantSource =$this->getTenantSource($tenant);
                $tenantSource->initialisation(app('currentTenant'));
                $command->info('');

                if ($command->option('source_id')) {
                    $this->handle($tenantSource, $command->option('source_id'));
                } else {
                    if (!$command->option('quiet') and !$command->getOutput()->isDebug()) {
                        $info = '✊ '.$command->getName().' '.$tenant->code;
                        if ($this->shop) {
                            $info .= ' shop:'.$this->shop->slug;
                        }

                        $command->line($info);
                        $this->progressBar = $command->getOutput()->createProgressBar($this->count() ?? 0);
                        $this->progressBar->setFormat('debug');
                        $this->progressBar->start();
                    } else {
                        $command->line('Steps '.number_format($this->count()));
                    }

                    $this->fetchAll($tenantSource, $command);
                    $this->progressBar?->finish();
                }
            });

            if ($result !== 0) {
                $exitCode = $result;
            }
        }

        return $exitCode;
    }

    /*
    public function asJob(SourceTenantService $tenantSource, ?array $tenantIds = null): void
    {
        if (is_array($tenantIds)) {
            $this->fetchSome($tenantSource, $tenantIds);
        } else {
            $this->getModelsQuery()
                ->chunk(100, function ($tenantIds) use ($tenantSource) {
                    $this->dispatch($tenantSource, $tenantIds->pluck('source_id')->all());
                });
        }
    }

    public function getJobMiddleware(): array
    {
        return [new InitialiseSourceTenant('currentTenant')];
    }

    public function configureJob(JobDecorator $job): void
    {
        $job->onQueue('fetches')
            ->setTries(5)
            ->setMaxExceptions(3)
            ->setTimeout(1800);
    }

    */

    public function authorize(ActionRequest $request): bool
    {
        if ($request->user()->userable_type == 'Tenant') {
            $this->tenant = $request->user()->tenant;

            if ($this->tenant->id and $request->user()->tokenCan('aurora')) {
                return true;
            }
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'id' => ['sometimes'],
        ];
    }


    public function asController(ActionRequest $request)
    {
        $validatedData = $request->validated();

        return $this->tenant->execute(
            /**
             * @throws \Exception
             */
            function (Tenant $tenant) use ($validatedData) {
                $tenantSource = $this->getTenantSource($tenant);
                $tenantSource->initialisation(app('currentTenant'));

                return $this->handle($tenantSource, Arr::get($validatedData, 'id'));
            }
        );
    }

    public function jsonResponse($model): array
    {
        if ($model) {
            return [
                'model'     => $model->getMorphClass(),
                'id'        => $model->id,
                'source_id' => $model->source_id,
            ];
        } else {
            return [
                'error' => 'model not returned'
            ];
        }
    }
}
