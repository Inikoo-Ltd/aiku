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
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementFlowEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
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

        $this->checkForErrors($location, $orgStock, $totalMovements, $isCurrent, $command);
    }

    public function checkForErrors(Location $location, OrgStock $orgStock, int $totalMovements, bool $isCurrent, ?Command $command): void
    {
        if ($totalMovements == 0) {
            return;
        }

        if ($errorData = $this->checkFirstAssociate($location, $orgStock, $totalMovements)) {
            $command?->error('error_first_associate');
            $this->fixFirstAssociate($location, $orgStock, $errorData, $command);
        }


        if (!$isCurrent || $orgStock->trashed() || $location->trashed()) {
            if ($errorData = $this->checkLastIsDisassociate($location, $orgStock, $totalMovements)) {
                $command?->error('error_last_disassociate');
                $this->fixLastDisassociate($location, $orgStock, $errorData, $command);
            }
        }

        $shouldHaveLastDisassociate = false;
        if (!$isCurrent || $orgStock->trashed() || $location->trashed()) {
            $shouldHaveLastDisassociate = true;
        }

        $internalDisassociates = [];
        $internalAssociates    = [];

        if ($errorData = $this->checkForInternalDisassociates($location, $orgStock, $shouldHaveLastDisassociate)) {
            $command?->error('error_internal_disassociates');
            $internalDisassociates = $errorData['movements'];
            $this->fixInternalDisassociates($errorData['movements'], $command);
        }

        if ($errorData = $this->checkForInternalAssociates($location, $orgStock)) {
            $command?->error('error_internal_associates');
            $internalAssociates = $errorData['movements'];
            $this->fixInternalAssociates($errorData['movements'], $command);
        }

        if (count($internalDisassociates) > 0) {
            foreach ($internalDisassociates as $movementId) {
                $movement = OrgStockMovement::find($movementId);
                if ($movement) {
                    $this->fixDisassociatePostTransactions($movement, $command);
                }
            }
        }


        $firstMovement = DB::table('org_stock_movements')->select('id', 'type', 'date', 'fixed_internal_helper')
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)
            ->orderByRaw('date, source_id,id')->first();


        if ($firstMovement) {
            if ($firstMovement->type != OrgStockMovementTypeEnum::ASSOCIATE->value) {
                $command?->error('error first associate should be fixed by now');
                return;

            }
            if (!$firstMovement->fixed_internal_helper) {
                array_unshift($internalAssociates, $firstMovement->id);
            }
        }


        if (count($internalAssociates) > 0) {
            foreach ($internalAssociates as $movementId) {
                $movement = OrgStockMovement::find($movementId);
                if ($movement) {
                    $this->fixAssociatePostTransactions($movement, $command);
                    //  exit;
                }
            }
        }

        if ($errorData = $this->checkForHelpersContinuity($location, $orgStock, $command)) {
            $this->fixHelpersContinuity($location, $orgStock, $errorData, $command);
        }
    }

    public function checkForInternalAssociates(Location $location, OrgStock $orgStock): ?array
    {
        $firstMovement = DB::table('org_stock_movements')->select('id', 'type', 'date')
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)->orderByRaw('date, source_id,id')->first();


        if ($firstMovement->type != OrgStockMovementTypeEnum::ASSOCIATE->value) {
            print "Error last disassociate should be fixed by now  $firstMovement->id  $firstMovement->type  $firstMovement->date ";
            return null;
        }


        $query = DB::table('org_stock_movements')->select('id')
            ->where('type', OrgStockMovementTypeEnum::ASSOCIATE->value)
            ->where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)
            ->whereNull('fixed_internal_helper')
            ->where('id', '!=', $firstMovement->id)
            ->orderByRaw('date, source_id,id');

        $movements = $query->pluck('id')->toArray();


        if (count($movements) > 0) {
            return [
                'movements' => $movements,
            ];
        }

        return null;
    }

    public function checkForInternalDisassociates(Location $location, OrgStock $orgStock, bool $shouldHaveLastDisassociate): ?array
    {
        $lastMovement = null;
        if ($shouldHaveLastDisassociate) {
            $lastMovement = DB::table('org_stock_movements')->select('id', 'type', 'date')
                ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
                ->where('location_id', $location->id)
                ->where('org_stock_id', $orgStock->id)->orderByRaw('date desc, id desc')->first();


            if ($lastMovement->type != OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                print "Error last disassociate should be fixed by now  $lastMovement->id  $lastMovement->type  $lastMovement->date ";
                return null;

            }
        }

        $query = DB::table('org_stock_movements')->select('id')
            ->where('type', OrgStockMovementTypeEnum::DISASSOCIATE->value)
            ->where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)
            ->whereNull('fixed_internal_helper')
            ->orderByRaw('date');

        if ($lastMovement) {
            $query->where('id', '!=', $lastMovement->id);
        }


        $movements = $query->pluck('id')->toArray();

        if (count($movements) > 0) {
            return [
                'movements' => $movements,
            ];
        }

        return null;
    }

    public function fixInternalDisassociates(array $movements, ?Command $command = null): void
    {
        foreach ($movements as $movementId) {
            $movement = OrgStockMovement::find($movementId);
            if ($movement) {
                $this->fixDisassociate($movement, $command);
            }
        }
    }

    public function fixInternalAssociates(array $movements, ?Command $command = null): void
    {
        foreach ($movements as $movementId) {
            $movement = OrgStockMovement::find($movementId);
            if ($movement) {
                $this->fixAssociate($movement, $command);
            }
        }
    }

    public function fixHelpersContinuity(Location $location, OrgStock $orgStock, array $errorData, ?Command $command = null): void
    {
        $movements = DB::table('org_stock_movements')->select('date', 'id', 'quantity', 'audited_quantity', 'type', 'class')
            ->where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->orderByRaw('date,source_id,id')
            ->get();

        $isIn             = false;
        $expectedTypes    = [OrgStockMovementTypeEnum::ASSOCIATE->value];
        $previousMovement = null;

        foreach ($movements as $movementKey => $movement) {
            //  print_r($movement);
            $ok = true;
            if (!in_array($movement->type, $expectedTypes)) {
                $command?->warn("Movement type $movement->type should be in ".count($expectedTypes)." $movement->id");


                if ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                    if ($previousMovement && $previousMovement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                        DB::table('org_stock_movements')->where('id', $movement->id)
                            ->update(
                                [
                                    'class' => OrgStockMovementClassEnum::GARBAGE->value,
                                    'fixed' => true
                                ]
                            );
                        $command?->warn("Garbage Duplicate disassociates   $movement->id ");
                    }
                } elseif ($movement->class == OrgStockMovementClassEnum::MOVEMENT->value) {
                    $nextMovement = Arr::get($movements, $movementKey + 1);

                    if ($previousMovement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value && $nextMovement && $nextMovement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                        DB::table('org_stock_movements')->where('id', $previousMovement->id)
                            ->update(
                                [
                                    'class' => OrgStockMovementClassEnum::GARBAGE->value,
                                    'fixed' => true
                                ]
                            );
                        $command?->warn("Garbage duplicated  disassociates   $movement->id ");
                    }
                } elseif ($movement->type == OrgStockMovementTypeEnum::AUDIT->value && !$isIn) {
                }
            }

            if ($movement->type == OrgStockMovementTypeEnum::ASSOCIATE->value) {
                $isIn          = true;
                $expectedTypes = [
                    OrgStockMovementTypeEnum::PURCHASE->value,
                    OrgStockMovementTypeEnum::RETURN_DISPATCH->value,
                    OrgStockMovementTypeEnum::RETURN_PICKED->value,
                    OrgStockMovementTypeEnum::RETURN_CONSUMPTION->value,
                    OrgStockMovementTypeEnum::PICKED->value,
                    OrgStockMovementTypeEnum::LOCATION_TRANSFER->value,
                    OrgStockMovementTypeEnum::FOUND->value,
                    OrgStockMovementTypeEnum::CONSUMPTION->value,
                    OrgStockMovementTypeEnum::WRITE_OFF->value,
                    OrgStockMovementTypeEnum::ADJUSTMENT->value,
                    OrgStockMovementTypeEnum::DISASSOCIATE->value,
                    OrgStockMovementTypeEnum::AUDIT->value

                ];
            } elseif ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                $isIn          = false;
                $expectedTypes = [
                    OrgStockMovementTypeEnum::ASSOCIATE->value,


                ];
            }


            $previousMovement = $movement;
        }
    }

    public function checkForHelpersContinuity(Location $location, OrgStock $orgStock, ?Command $command = null): ?array
    {
        $movements     = DB::table('org_stock_movements')->select('type', 'date')
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)->orderByRaw('date,id')->get();
        $errors        = [];
        $expectedTypes = [OrgStockMovementTypeEnum::ASSOCIATE->value];
        foreach ($movements as $movement) {
            if (!in_array($movement->type, $expectedTypes)) {
                $errors[] = $movement;
            }


            if ($movement->type == OrgStockMovementTypeEnum::ASSOCIATE->value) {
                $isIn          = true;
                $expectedTypes = [
                    OrgStockMovementTypeEnum::PURCHASE->value,
                    OrgStockMovementTypeEnum::RETURN_DISPATCH->value,
                    OrgStockMovementTypeEnum::RETURN_PICKED->value,
                    OrgStockMovementTypeEnum::RETURN_CONSUMPTION->value,
                    OrgStockMovementTypeEnum::PICKED->value,
                    OrgStockMovementTypeEnum::LOCATION_TRANSFER->value,
                    OrgStockMovementTypeEnum::FOUND->value,
                    OrgStockMovementTypeEnum::CONSUMPTION->value,
                    OrgStockMovementTypeEnum::WRITE_OFF->value,
                    OrgStockMovementTypeEnum::ADJUSTMENT->value,
                    OrgStockMovementTypeEnum::DISASSOCIATE->value,
                    OrgStockMovementTypeEnum::AUDIT->value

                ];
            } elseif ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                $isIn          = false;
                $expectedTypes = [
                    OrgStockMovementTypeEnum::ASSOCIATE->value,


                ];
            }
        }

        if (count($errors) > 0) {
            return [
                'errors' => $errors,
            ];
        }

        return null;
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

    public function fixDisassociate(OrgStockMovement $orgStockMovement, ?Command $command = null): void
    {
        $command?->warn("Fix disassociate $orgStockMovement->id ".$orgStockMovement->date->format('Y-m-d H:i:s.u'));

        $movements = DB::table('org_stock_movements')->select('date', 'id', 'quantity', 'audited_quantity', 'type', 'class')
            ->where('location_id', $orgStockMovement->location_id)->where('org_stock_id', $orgStockMovement->org_stock_id)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('date', $orgStockMovement->date->format('Y-m-d H:i:s.u'))
            ->orderByRaw('source_id desc,date desc,id desc')
            ->get();

        $numberDisassociates = 0;
        $sumAdjustments      = 0;
        $numberAdjustments   = 0;

        foreach ($movements as $movement) {
            // print_r($movement);
            if ($movement->type == OrgStockMovementTypeEnum::ADJUSTMENT->value) {
                $sumAdjustments += $movement->quantity;
                $numberAdjustments++;
            } elseif ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                $numberDisassociates++;
            } elseif ($movement->type == OrgStockMovementTypeEnum::AUDIT->value) {
                if ($movement->audited_quantity == 0) {
                    DB::table('org_stock_movements')->where('id', $movement->id)
                        ->update(
                            [
                                'class' => OrgStockMovementClassEnum::GARBAGE->value,
                                'fixed' => true
                            ]
                        );
                    $command?->warn("Audit zero value at association  $movement->id fixed as Garbage");
                }
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
            }
        }

        if ($numberDisassociates > 1) {
            foreach ($movements as $movement) {
                if ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value && $movement->id != $orgStockMovement->id) {
                    DB::table('org_stock_movements')->where('id', $movement->id)
                        ->update(
                            [
                                'class' => OrgStockMovementClassEnum::GARBAGE->value,
                                'fixed' => true
                            ]
                        );
                    $command?->warn("Garbage Duplicate disassociates   $movement->id ");
                }
            }
        }


        if (!$orgStockMovement->fixed_internal_helper) {
            $orgStockMovement->update([
                'date'                  => $orgStockMovement->date->addMicroseconds(4000),
                'fixed_internal_helper' => true,
            ]);
            $command?->warn("Move forward  disassociate $orgStockMovement->id a little bit (mark as fixed_internal_helper)");
        }
    }


    public function fixAssociatePostTransactions(OrgStockMovement $orgStockMovement, ?Command $command = null): void
    {
        $command?->warn("Fix associate post transactions $orgStockMovement->id ".$orgStockMovement->date->format('Y-m-d H:i:s.u'));


        $numberValidMovements     = 0;
        $nextDisassociateMovement = null;
        $dateFirstValidMovement   = null;

        $nextMovements = DB::table('org_stock_movements')->select('date', 'id', 'quantity', 'audited_quantity', 'type', 'class')
            ->where('location_id', $orgStockMovement->location_id)
            ->where('org_stock_id', $orgStockMovement->org_stock_id)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('date', '>', $orgStockMovement->date->format('Y-m-d H:i:s.u'))
            ->orderByRaw('date,source_id,id')
            ->get();
        foreach ($nextMovements as $movement) {
            if ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                $nextDisassociateMovement = $movement;
                break;
            }


            if ($movement->type == OrgStockMovementTypeEnum::ASSOCIATE->value) {
                print_r($movement);
                DB::table('org_stock_movements')->where('id', $movement->id)
                    ->update(
                        [
                            'type'             => OrgStockMovementTypeEnum::AUDIT->value,
                            'class'            => OrgStockMovementClassEnum::HELPER->value,
                            'fixed'            => true,
                            'audited_quantity' => 0,
                            'quantity'         => 0,
                        ]
                    );
                $command?->warn("Transform associate to audit   $movement->id ");
            }
        }

        if (!$orgStockMovement->fixed_internal_helper) {
            $orgStockMovement->update([
                'fixed_internal_helper' => true,
            ]);
        }


        //   if ($numberValidMovements > 0 and $dateFirstValidMovement) {
        // dd($dateFirstValidMovement, $nextDisassociateMovement);
        //   }
    }


    public function fixDisassociatePostTransactions(OrgStockMovement $orgStockMovement, ?Command $command = null): void
    {
        $command?->warn("Fix disassociate post transactions $orgStockMovement->id ".$orgStockMovement->date->format('Y-m-d H:i:s.u'));


        $numberValidMovements   = 0;
        $nextAssociateMovement  = null;
        $dateFirstValidMovement = null;

        $nextMovements = DB::table('org_stock_movements')->select('date', 'id', 'quantity', 'audited_quantity', 'type', 'class')
            ->where('location_id', $orgStockMovement->location_id)
            ->where('org_stock_id', $orgStockMovement->org_stock_id)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('date', '>', $orgStockMovement->date->format('Y-m-d H:i:s.u'))
            ->orderByRaw('date,source_id,id')
            ->get();
        foreach ($nextMovements as $movement) {
            if ($movement->type == OrgStockMovementTypeEnum::ASSOCIATE->value) {
                $nextAssociateMovement = $movement;
                break;
            }

            if ($movement->type == OrgStockMovementTypeEnum::AUDIT->value && $movement->audited_quantity == 0) {
                DB::table('org_stock_movements')->where('id', $movement->id)
                    ->update(
                        [
                            'class' => OrgStockMovementClassEnum::GARBAGE->value,
                            'fixed' => true
                        ]
                    );
                $command?->warn("## Garbage post disassociate audits   $movement->id ");
            }
            if ($movement->type == OrgStockMovementTypeEnum::ADJUSTMENT->value && $movement->quantity == 0) {
                DB::table('org_stock_movements')->where('id', $movement->id)
                    ->update(
                        [
                            'class' => OrgStockMovementClassEnum::GARBAGE->value,
                            'fixed' => true
                        ]
                    );
                $command?->warn("## Garbage post disassociate adjustment   $movement->id ");
            } elseif ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                DB::table('org_stock_movements')->where('id', $movement->id)
                    ->update(
                        [
                            'class' => OrgStockMovementClassEnum::GARBAGE->value,
                            'fixed' => true
                        ]
                    );
                $command?->warn("## Garbage post disassociate disassociates   $movement->id ");
            } else {
                $numberValidMovements++;
                if ($dateFirstValidMovement == null) {
                    $dateFirstValidMovement = $movement->date;
                }
            }
        }

        if ($numberValidMovements > 0 and $dateFirstValidMovement) {
            $orgStock = OrgStock::withTrashed()->find($orgStockMovement->org_stock_id);
            $location = Location::withTrashed()->find($orgStockMovement->location_id);

            StoreOrgStockMovement::make()->action(
                $orgStock,
                $location,
                [
                    'quantity'         => 0,
                    'audited_quantity' => 0,
                    'org_amount'       => 0,
                    'date'             => Carbon::parse($dateFirstValidMovement)->subMicroseconds(1000)->format('Y-m-d H:i:s.u'),
                    'type'             => OrgStockMovementTypeEnum::ASSOCIATE->value,
                    'class'            => OrgStockMovementClassEnum::HELPER->value,
                    'flow'             => OrgStockMovementFlowEnum::AUDIT->value,
                    'fixed'            => true,
                ]
            );
            $command?->warn("## Fix disassociate post transactions add associate  $orgStockMovement->id ");
        }
    }


    public function fixAssociate(OrgStockMovement $orgStockMovement, ?Command $command = null): void
    {
        $command?->warn("Fix associate $orgStockMovement->id ".$orgStockMovement->date->format('Y-m-d H:i:s.u'));

        $movements = DB::table('org_stock_movements')->select('date', 'id', 'quantity', 'audited_quantity', 'type', 'class')
            ->where('location_id', $orgStockMovement->location_id)->where('org_stock_id', $orgStockMovement->org_stock_id)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('date', $orgStockMovement->date->format('Y-m-d H:i:s.u'))
            ->orderByRaw('source_id,id')
            ->get();

        $numberAssociates  = 0;
        $sumAdjustments    = 0;
        $numberAdjustments = 0;

        foreach ($movements as $movement) {
            //print_r($movement);
            if ($movement->type == OrgStockMovementTypeEnum::ADJUSTMENT->value) {
                $sumAdjustments += $movement->quantity;
                $numberAdjustments++;
            } elseif ($movement->type == OrgStockMovementTypeEnum::ASSOCIATE->value) {
                $numberAssociates++;
            } elseif ($movement->type == OrgStockMovementTypeEnum::AUDIT->value) {
                if ($movement->audited_quantity == 0) {
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
                $command?->warn("Edit movement date of  $movement->id");
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
                        $command?->warn("Move forward Adjustment   $movement->id ");
                    }
                }
            }
        }

        if ($numberAssociates > 1) {
            foreach ($movements as $movement) {
                if ($movement->type == OrgStockMovementTypeEnum::ASSOCIATE->value && $movement->id != $orgStockMovement->id) {
                    DB::table('org_stock_movements')->where('id', $movement->id)
                        ->update(
                            [
                                'class' => OrgStockMovementClassEnum::GARBAGE->value,
                                'fixed' => true
                            ]
                        );
                    $command?->warn("Garbage Duplicate disassociates   $movement->id ");
                }
            }
        }

        if (!$orgStockMovement->fixed_internal_helper) {
            $orgStockMovement->update([
                'fixed_internal_helper' => true,
            ]);
            $command?->warn("Mark internal associate $orgStockMovement->id a (mark as fixed_internal_helper)");
        }
    }


    public function fixLastDisassociate(Location $location, OrgStock $orgStock, array $errorData, ?Command $command = null): void
    {
        $movements = DB::table('org_stock_movements')->select('date', 'id', 'quantity', 'audited_quantity', 'type', 'class')
            ->where('location_id', $location->id)->where('org_stock_id', $orgStock->id)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO])
            ->where('date', Arr::get($errorData, 'date'))
            ->orderByRaw('source_id desc,date desc,id desc')
            ->get();

        $numberDisassociates = 0;
        $sumAdjustments      = 0;
        $numberAdjustments   = 0;

        foreach ($movements as $movement) {
            //print_r($movement);
            if ($movement->type == OrgStockMovementTypeEnum::ADJUSTMENT->value) {
                $sumAdjustments += $movement->quantity;
                $numberAdjustments++;
            } elseif ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                $numberDisassociates++;
            } elseif ($movement->type == OrgStockMovementTypeEnum::AUDIT->value) {
                if ($movement->audited_quantity == 0) {
                    DB::table('org_stock_movements')->where('id', $movement->id)
                        ->update(
                            [
                                'class' => OrgStockMovementClassEnum::GARBAGE->value,
                                'fixed' => true
                            ]
                        );
                    $command?->warn("Audit zero value at association  $movement->id fixed as Garbage");
                }
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
                    'class'            => OrgStockMovementClassEnum::HELPER->value,
                    'flow'             => OrgStockMovementFlowEnum::AUDIT->value,
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
        $movements = DB::table('org_stock_movements')->select('date', 'id', 'quantity', 'audited_quantity', 'type', 'class')
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
                if ($movement->audited_quantity == 0) {
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
                $command?->warn("Edit movement date of  $movement->id");
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
                    'class'            => OrgStockMovementClassEnum::HELPER->value,
                    'flow'             => OrgStockMovementFlowEnum::AUDIT->value,
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
