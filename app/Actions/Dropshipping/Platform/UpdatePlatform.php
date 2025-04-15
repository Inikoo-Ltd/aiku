<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Platform;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Platform;
use Illuminate\Validation\Rule;

class UpdatePlatform extends OrgAction
{
    use WithActionUpdate;

    public function handle(Platform $platform, array $modelData): Platform
    {
        /** @var Platform $platform */
        $platform = $this->update($platform, $modelData);

        return $platform;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', Rule::unique('platforms', 'code')],
            'name' => ['sometimes', 'required']
        ];
    }

    public function action(Platform $platform, array $modelData): Platform
    {
        $this->initialisationFromGroup($platform->group, $modelData);

        return $this->handle($platform, $modelData);
    }
}
