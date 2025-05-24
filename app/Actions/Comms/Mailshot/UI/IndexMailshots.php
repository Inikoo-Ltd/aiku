<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Models\Comms\OrgPostRoom;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexMailshots extends OrgAction
{
    use HasUIMailshots;
    use WithCatalogueAuthorisation;
    use WithIndexMailshots;

    public Outbox|PostRoom|OrgPostRoom|Organisation $parent;

    public function handle(Outbox|PostRoom|OrgPostRoom|Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        return $this->handleMailshot(null, $parent, $prefix);

    }



}
