<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jan 2024 00:56:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions;

use App\Actions\Traits\WithTab;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GrpAction
{
    use AsAction;
    use WithAttributes;
    use WithTab;

    protected Group $group;

    protected bool $asAction = false;

    protected bool $canEdit = false;

    protected bool $canDelete = false;

    public int $hydratorsDelay = 0;

    protected bool $strict = true;

    protected array $validatedData;

    public function initialisation(Group $group, ActionRequest|array $request): static
    {
        $this->group = $group;
        if (is_array($request)) {
            $this->setRawAttributes($request);
        } else {
            $this->fillFromRequest($request);

        }
        $this->validatedData = $this->validateAttributes();

        return $this;
    }
}
