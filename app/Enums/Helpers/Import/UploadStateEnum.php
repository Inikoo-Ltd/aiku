<?php

namespace App\Enums\Helpers\Import;

use App\Enums\EnumHelperTrait;

enum UploadStateEnum: string
{
    use EnumHelperTrait;

    case CHECKING             = 'checking';
    case WAITING_CONFIRMATION = 'waiting_confirmation';
    case IMPORTING            = 'importing';
    case IMPORTED             = 'imported';
    case CANCELLED            = 'cancelled';
    case REFUSED              = 'refused';
}
