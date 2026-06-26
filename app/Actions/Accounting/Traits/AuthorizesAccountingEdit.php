<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jun 2026 22:07:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Traits;

use Lorisleiva\Actions\ActionRequest;

trait AuthorizesAccountingEdit
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("accounting.{$this->organisation->id}.edit");
    }
}
