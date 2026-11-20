<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-54m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Dispatching\Picking;

use Symfony\Component\HttpKernel\Exception\HttpException;

class PickingRefusedException extends HttpException
{
    public const string NOTHING_LEFT_TO_PICK = 'over_required';
    public const string LOCATION_EMPTY = 'location_empty';
    public const string NOT_ENOUGH_STOCK = 'not_enough_stock';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct(422, $message);
    }
}
