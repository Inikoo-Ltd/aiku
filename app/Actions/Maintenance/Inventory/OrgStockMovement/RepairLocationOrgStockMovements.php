<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Mar 2026 13:24:35 Central Indonesia Time, Plane Bali-KL
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairLocationOrgStockMovements
{
    use AsAction;


    public function handle(?int $locationId, ?int $orgStockId, ?Command $command = null): void
    {
        if (!$locationId || !$orgStockId) {
            return;
        }
        $location = Location::withTrashed()->find($locationId);
        if (!$location) {
            return;
        }
        $orgStock = OrgStock::withTrashed()->find($orgStockId);
        if (!$orgStock) {
            return;
        }

        $totalMovements = DB::table('org_stock_movements')
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('location_id', $locationId)->where('org_stock_id', $orgStockId)->count();


        $isCurrent = DB::table('location_org_stocks')->where('org_stock_id', $orgStock->id)
            ->where('location_id', $location->id)->exists();

        if ($command) {
            $msg = '  >>  org-stock ('.$orgStock->id.'): '.$orgStock->slug;
            if ($orgStock->trashed()) {
                $msg .= ' 🗑️ ';
            } elseif ($orgStock->state == OrgStockStateEnum::DISCONTINUED) {
                $msg .= ' ⛔️ ';
            }

            $msg .= ' Location ('.$location->id.'): '.$location->slug;
            if ($location->trashed()) {
                $msg .= ' 🗑️ ';
            }


            if ($isCurrent) {
                $msg .= ' ✨';
            }


            $msg .= ' M:'.$totalMovements;

            $command->info($msg);
        }

        $errors = $this->checkForErrors($location, $orgStock, $totalMovements, $isCurrent);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $errorType = $error['type'];
                $command?->error($error['type']);
                if ($errorType == 'error_first_associate') {
                    $this->fixFirstAssociate($location, $orgStock, $error['data'], $command);
                    //exit;
                } elseif ($errorType == 'error_last_disassociate') {
                    $this->fixLastDisassociate($location, $orgStock, $error['data'], $command);
                    //exit;
                }
            }
        }
    }

    public function checkForErrors(Location $location, OrgStock $orgStock, int $totalMovements, bool $isCurrent): array
    {
        $errors = [];
        if ($errorData = $this->checkFirstAssociate($location, $orgStock, $totalMovements)) {
            $errors[] = [
                'type' => 'error_first_associate',
                'data' => $errorData
            ];
        }

        if (!$isCurrent || $orgStock->trashed() || $location->trashed()) {
            if ($errorData = $this->checkLastIsDisassociate($location, $orgStock, $totalMovements)) {
                $errors[] = [
                    'type' => 'error_last_disassociate',
                    'data' => $errorData
                ];
            }
        }

        return $errors;
    }


    public function checkFirstAssociate(Location $location, OrgStock $orgStock, int $totalMovements): ?array
    {
        if ($totalMovements == 0) {
            return null;
        }
        $movement = DB::table('org_stock_movements')->select('type', 'date')
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)->orderByRaw('date,id')->first();


        $countMovementsAssociatedDate = DB::table('org_stock_movements')
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)->where('date', $movement->date)->count();
        if ($movement->type != OrgStockMovementTypeEnum::ASSOCIATE->value || $countMovementsAssociatedDate != 1) {
            return [
                'date' => $movement->date,
            ];
        }

        return null;
    }

    public function checkLastIsDisassociate(Location $location, OrgStock $orgStock, int $totalMovements): ?array
    {
        if ($totalMovements == 0) {
            return null;
        }

        $movement = DB::table('org_stock_movements')->select('type', 'date')
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)->orderByRaw('date desc, id desc')->first();


        $countMovementsDisassociatedDate = DB::table('org_stock_movements')
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)->where('date', $movement->date)->count();
        if ($movement->type != OrgStockMovementTypeEnum::DISASSOCIATE->value || $countMovementsDisassociatedDate != 1) {
            return [
                'date' => $movement->date,
            ];
        }

        return null;
    }

    public function fixLastDisassociate(Location $location, OrgStock $orgStock, array $errorData, ?Command $command = null): void
    {
        $movements = DB::table('org_stock_movements')->select('date', 'id', 'quantity', 'type', 'class')
            ->where('location_id', $location->id)->where('org_stock_id', $orgStock->id)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('date', Arr::get($errorData, 'date'))
            ->orderByRaw('source_id desc,date desc,id desc')
            ->get();

        $numberDisassociates = 0;
        $sumAdjustments      = 0;
        $numberAdjustments   = 0;

        foreach ($movements as $movement) {
            print_r($movement);
            if ($movement->type == OrgStockMovementTypeEnum::ADJUSTMENT->value) {
                $sumAdjustments += $movement->quantity;
                $numberAdjustments++;
            } elseif ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                $numberDisassociates++;
            } elseif ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                //                DB::table('org_stock_movements')->where('id', $movement->id)
                //                    ->update(
                //                        [
                //                            'date'  => Carbon::parse($movement->date)->addMicroseconds(2000)->format('Y-m-d H:i:s.u'),
                //                            'fixed' => true
                //                        ]
                //                    );
                //                $command?->warn("Move disassociate forward  $movement->id");
            } elseif ($movement->type == OrgStockMovementTypeEnum::AUDIT->value) {
                if ($movement->quantity == 0) {
                    DB::table('org_stock_movements')->where('id', $movement->id)
                        ->update(
                            [
                                'class' => OrgStockMovementClassEnum::GARBAGE->value,
                                'fixed' => true
                            ]
                        );
                    $command?->warn("Audit zero value at association  $movement->id fixed as Garbage");
                } else {
                    //                    DB::table('org_stock_movements')->where('id', $movement->id)
                    //                        ->update(
                    //                            [
                    //                                'date'  => Carbon::parse($movement->date)->addMicroseconds(500)->format('Y-m-d H:i:s.u'),
                    //                                'fixed' => true
                    //                            ]
                    //                        );
                    //                    $command?->warn("Move audit up forward  $movement->id");
                }
            } elseif ($movement->class == OrgStockMovementClassEnum::MOVEMENT->value) {
                //                DB::table('org_stock_movements')->where('id', $movement->id)
                //                    ->update(
                //                        [
                //                            'date'  => Carbon::parse($movement->date)->addMicroseconds(1000)->format('Y-m-d H:i:s.u'),
                //                            'fixed' => true
                //                        ]
                //                    );
                //                $command?->warn("Edit date of  $movement->id");
            }
        }

        if ($numberAdjustments > 0) {
            if ($sumAdjustments == 0) {
                foreach ($movements as $movement) {
                    if ($movement->type == OrgStockMovementTypeEnum::ADJUSTMENT->value) {
                        DB::table('org_stock_movements')->where('id', $movement->id)
                            ->update(
                                [
                                    'class' => OrgStockMovementClassEnum::GARBAGE->value,
                                    'fixed' => true
                                ]
                            );
                        $command?->warn("Useless Adjustment   $movement->id set as Garbage");
                    }
                }
            } else {
                foreach ($movements as $movement) {
                    if ($movement->type == OrgStockMovementTypeEnum::ADJUSTMENT->value) {
                        //                        DB::table('org_stock_movements')->where('id', $movement->id)
                        //                            ->update(
                        //                                [
                        //                                    'date'  => Carbon::parse($movement->date)->addMicroseconds(1000)->format('Y-m-d H:i:s.u'),
                        //                                    'fixed' => true
                        //                                ]
                        //                            );
                        //                        $command?->warn("Edit date of ** $movement->id");
                    }
                }
            }
        }

        if ($numberDisassociates == 0) {
            StoreOrgStockMovement::make()->action(
                $orgStock,
                $location,
                [
                    'quantity'         => 0,
                    'audited_quantity' => 0,
                    'org_amount'       => 0,
                    'date'             => Carbon::parse(Arr::get($errorData, 'date'))->addMicroseconds(4000)->format('Y-m-d H:i:s.u'),
                    'type'             => OrgStockMovementTypeEnum::DISASSOCIATE->value,
                    'fixed'            => true,
                ]
            );
            $command?->warn('add last disassociate');
        } elseif ($numberDisassociates == 1) {
            foreach ($movements as $movement) {
                if ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                    DB::table('org_stock_movements')->where('id', $movement->id)
                        ->update(
                            [
                                'date'  => Carbon::parse($movement->date)->addMicroseconds(4000)->format('Y-m-d H:i:s.u'),
                                'fixed' => true
                            ]
                        );
                    $command?->warn("Move disassociate date forward ** $movement->id");
                }
            }
        } elseif ($numberDisassociates > 1) {
            $garbageMultipleDisassociates = false;
            foreach ($movements as $movement) {
                if ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                    if ($garbageMultipleDisassociates) {
                        DB::table('org_stock_movements')->where('id', $movement->id)
                            ->update(
                                [
                                    'class' => OrgStockMovementClassEnum::GARBAGE->value,
                                    'fixed' => true
                                ]
                            );
                        $command?->warn("Garbage Duplicate disassociates   $movement->id ");
                    } else {
                        $garbageMultipleDisassociates = true;
                    }
                }
            }
        }
    }


    public function fixFirstAssociate(Location $location, OrgStock $orgStock, array $errorData, ?Command $command = null): void
    {
        $movements = DB::table('org_stock_movements')->select('date', 'id', 'quantity', 'type', 'class')
            ->where('location_id', $location->id)->where('org_stock_id', $orgStock->id)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('date', Arr::get($errorData, 'date'))
            ->orderByRaw('source_id,date,id')
            ->get();

        $numberAssociates  = 0;
        $sumAdjustments    = 0;
        $numberAdjustments = 0;

        foreach ($movements as $movement) {
            print_r($movement);
            if ($movement->type == OrgStockMovementTypeEnum::ADJUSTMENT->value) {
                $sumAdjustments += $movement->quantity;
                $numberAdjustments++;
            } elseif ($movement->type == OrgStockMovementTypeEnum::ASSOCIATE->value) {
                $numberAssociates++;
            } elseif ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                DB::table('org_stock_movements')->where('id', $movement->id)
                    ->update(
                        [
                            'date'  => Carbon::parse($movement->date)->addMicroseconds(2000)->format('Y-m-d H:i:s.u'),
                            'fixed' => true
                        ]
                    );
                $command?->warn("Move disassociate forward  $movement->id");
            } elseif ($movement->type == OrgStockMovementTypeEnum::AUDIT->value) {
                if ($movement->quantity == 0) {
                    DB::table('org_stock_movements')->where('id', $movement->id)
                        ->update(
                            [
                                'class' => OrgStockMovementClassEnum::GARBAGE->value,
                                'fixed' => true
                            ]
                        );
                    $command?->warn("Audit zero value at association  $movement->id fixed as Garbage");
                } else {
                    DB::table('org_stock_movements')->where('id', $movement->id)
                        ->update(
                            [
                                'date'  => Carbon::parse($movement->date)->addMicroseconds(500)->format('Y-m-d H:i:s.u'),
                                'fixed' => true
                            ]
                        );
                    $command?->warn("Move audit up forward  $movement->id");
                }
            } elseif ($movement->class == OrgStockMovementClassEnum::MOVEMENT->value) {
                DB::table('org_stock_movements')->where('id', $movement->id)
                    ->update(
                        [
                            'date'  => Carbon::parse($movement->date)->addMicroseconds(1000)->format('Y-m-d H:i:s.u'),
                            'fixed' => true
                        ]
                    );
                $command?->warn("Edit date of  $movement->id");
            }
        }

        if ($numberAdjustments > 0) {
            if ($sumAdjustments == 0) {
                foreach ($movements as $movement) {
                    if ($movement->type == OrgStockMovementTypeEnum::ADJUSTMENT->value) {
                        DB::table('org_stock_movements')->where('id', $movement->id)
                            ->update(
                                [
                                    'class' => OrgStockMovementClassEnum::GARBAGE->value,
                                    'fixed' => true
                                ]
                            );
                        $command?->warn("Useless Adjustment   $movement->id set as Garbage");
                    }
                }
            } else {
                foreach ($movements as $movement) {
                    if ($movement->type == OrgStockMovementTypeEnum::ADJUSTMENT->value) {
                        DB::table('org_stock_movements')->where('id', $movement->id)
                            ->update(
                                [
                                    'date'  => Carbon::parse($movement->date)->addMicroseconds(1000)->format('Y-m-d H:i:s.u'),
                                    'fixed' => true
                                ]
                            );
                        $command?->warn("Edit date of ** $movement->id");
                    }
                }
            }
        }

        if ($numberAssociates == 0) {
            StoreOrgStockMovement::make()->action(
                $orgStock,
                $location,
                [
                    'quantity'         => 0,
                    'audited_quantity' => 0,
                    'org_amount'       => 0,
                    'date'             => Arr::get($errorData, 'date'),
                    'type'             => OrgStockMovementTypeEnum::ASSOCIATE->value,
                    'fixed'            => true,
                ]
            );

            $command?->warn('add first associate');
        } elseif ($numberAssociates > 1) {
            $garbageMultipleAssociates = false;
            foreach ($movements as $movement) {
                if ($movement->type == OrgStockMovementTypeEnum::ASSOCIATE->value) {
                    if ($garbageMultipleAssociates) {
                        DB::table('org_stock_movements')->where('id', $movement->id)
                            ->update(
                                [
                                    'class' => OrgStockMovementClassEnum::GARBAGE->value,
                                    'fixed' => true
                                ]
                            );
                        $command?->warn("Garbage Duplicate associates   $movement->id ");
                    } else {
                        $garbageMultipleAssociates = true;
                    }
                }
            }
        }
    }


    public function getCommandSignature(): string
    {
        return 'repair_location_org_stock_movements {location} {orgStock}';
    }

    public function asCommand(Command $command): int
    {
        $location = Location::withTrashed()->where('slug', $command->argument('location'))->firstOrFail();
        $orgStock = OrgStock::withTrashed()->where('slug', $command->argument('orgStock'))->firstOrFail();
        $this->handle($location->id, $orgStock->id, $command);

        return 0;
    }

}
