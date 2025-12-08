<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sat, 17 Sept 2022 02:10:19 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions;

use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class InertiaAction
{
    use AsAction;
    use WithAttributes;

    protected ?string $routeName = null;

    protected array $originalParameters = [];

    protected ?string $tab = null;

    protected array $elementGroups = [];

    protected bool $canEdit = false;

    protected bool $canDelete = false;

    protected int $perPage = 50;

    private array $rawInputs;

    public function initialisation(ActionRequest $request): static
    {
        $this->routeName = $request->route()->getName();
        $this->originalParameters = $request->route()->originalParameters();
        $this->rawInputs = $request->all();
        $request->validate();

        return $this;
    }

    public function withTab(array $tabs): static
    {
        $tab = Arr::get($this->rawInputs, 'tab', Arr::first($tabs));

        if (! in_array($tab, $tabs)) {
            abort(404);
        }
        $this->tab = $tab;

        return $this;
    }

    /**
     * @throws \Exception
     *
     * @noinspection PhpUnused
     */
    public function getValidationFailure(): void
    {
        abort(422);
    }
}
