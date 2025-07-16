<?php

namespace App\Actions\CRM\Poll\UI;

use App\Models\CRM\Poll;

use Lorisleiva\Actions\Concerns\AsObject;

class GetPollShowcase
{
    use AsObject;

    public function handle(Poll $poll): array
    {
        return [];
    }
}
