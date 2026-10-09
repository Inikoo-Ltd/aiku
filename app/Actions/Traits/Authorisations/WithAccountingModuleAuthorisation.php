<?php

namespace App\Actions\Traits\Authorisations;

use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

trait WithAccountingModuleAuthorisation
{
    /**
     * Lists and exports inside the Accounting module need accounting view. The same actions also
     * serve the shop, customer and fulfilment pages, which keep their own rules.
     */
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || !str_starts_with($request->route()->getName(), 'grp.org.accounting.')) {
            return true;
        }

        $organisation = $request->route('organisation');
        if (!$organisation instanceof Organisation) {
            $organisation = Organisation::where('slug', $organisation)->first();
        }

        return $organisation && $request->user()->authTo("accounting.$organisation->id.view");
    }
}
