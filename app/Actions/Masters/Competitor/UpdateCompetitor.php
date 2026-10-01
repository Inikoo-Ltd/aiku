<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\Competitor;
use Lorisleiva\Actions\ActionRequest;

/**
 * A blank password or feed link keeps the saved one, since the form never shows them back.
 */
class UpdateCompetitor extends OrgAction
{
    use WithMastersEditAuthorisation;
    use WithCompetitorRules;

    public function handle(Competitor $competitor, array $modelData): Competitor
    {
        foreach (['password', 'feed_url'] as $secret) {
            if (array_key_exists($secret, $modelData) && blank($modelData[$secret])) {
                unset($modelData[$secret]);
            }
        }

        if (array_intersect_key($modelData, array_flip(['login_url', 'username', 'password']))) {
            $modelData['cookies'] = null;
        }

        $competitor->update($modelData);

        return $competitor;
    }

    public function rules(): array
    {
        return $this->competitorRules(required: false);
    }

    public function asController(Competitor $competitor, ActionRequest $request): Competitor
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($competitor, $this->validatedData);
    }

    public function action(Competitor $competitor, array $modelData): Competitor
    {
        $this->asAction = true;
        $this->initialisationFromGroup($competitor->group, $modelData);

        return $this->handle($competitor, $this->validatedData);
    }
}
