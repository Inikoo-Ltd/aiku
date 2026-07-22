<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 18:49:00 Central Indonesia Time
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement\Traits;

use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait CanRepairOrgStockMovements
{
    public function fixForAuditsInPairs(Location $location, OrgStock $orgStock, ?Command $command = null): ?array
    {
        $movements = DB::table('org_stock_movements')
            ->select('id', 'date', 'type', 'audited_quantity')
            ->where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO->value])
            ->whereIn('type', [OrgStockMovementTypeEnum::PURCHASE->value, OrgStockMovementTypeEnum::AUDIT->value])
            ->orderByRaw('date, type')
            ->get();

        $movementsByDate = [];
        foreach ($movements as $movement) {
            $dateKey = $movement->date;
            if (!isset($movementsByDate[$dateKey])) {
                $movementsByDate[$dateKey] = [];
            }
            $movementsByDate[$dateKey][] = $movement;
        }

        $pairs = [];
        foreach ($movementsByDate as $date => $dateMovements) {
            $hasPurchase      = false;
            $hasAuditWithZero = false;
            $purchaseId       = null;
            $auditId          = null;

            foreach ($dateMovements as $movement) {
                if ($movement->type == OrgStockMovementTypeEnum::PURCHASE->value) {
                    $hasPurchase = true;
                    $purchaseId  = $movement->id;
                }
                if ($movement->type == OrgStockMovementTypeEnum::AUDIT->value && $movement->audited_quantity == 0) {
                    $hasAuditWithZero = true;
                    $auditId          = $movement->id;
                }
            }

            if ($hasPurchase && $hasAuditWithZero) {
                $pairs[] = [
                    'date'        => $date,
                    'purchase_id' => $purchaseId,
                    'audit_id'    => $auditId
                ];
            }
        }

        if (count($pairs) > 0) {
            foreach ($pairs as $pair) {
                DB::table('org_stock_movements')->where('id', $pair['audit_id'])
                    ->update(
                        [
                            'class' => OrgStockMovementClassEnum::GARBAGE->value,
                            'fixed' => true
                        ]
                    );
                $command?->warn("Garbage audit paired with purchase {$pair['audit_id']} ");
            }

            return [
                'pairs' => $pairs,
            ];
        }

        return null;
    }

    public function fixForPurchaseAndAssociatePairs(Location $location, OrgStock $orgStock, ?Command $command = null): ?array
    {
        $movements = DB::table('org_stock_movements')
            ->select('id', 'date', 'type')
            ->where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO->value])
            ->whereIn('type', [OrgStockMovementTypeEnum::PURCHASE->value, OrgStockMovementTypeEnum::ASSOCIATE->value])
            ->orderByRaw('date, type')
            ->get();

        $movementsByDate = [];
        foreach ($movements as $movement) {
            $dateKey = $movement->date;
            if (!isset($movementsByDate[$dateKey])) {
                $movementsByDate[$dateKey] = [];
            }
            $movementsByDate[$dateKey][] = $movement;
        }

        $pairs = [];
        foreach ($movementsByDate as $date => $dateMovements) {
            $hasPurchase    = false;
            $hasAssociate   = false;
            $purchaseId     = null;
            $associateId    = null;

            foreach ($dateMovements as $movement) {
                if ($movement->type == OrgStockMovementTypeEnum::PURCHASE->value) {
                    $hasPurchase = true;
                    $purchaseId  = $movement->id;
                }
                if ($movement->type == OrgStockMovementTypeEnum::ASSOCIATE->value) {
                    $hasAssociate = true;
                    $associateId  = $movement->id;
                }
            }

            if ($hasPurchase && $hasAssociate) {
                $pairs[] = [
                    'date'         => $date,
                    'purchase_id'  => $purchaseId,
                    'associate_id' => $associateId
                ];
            }
        }

        if (count($pairs) > 0) {
            foreach ($pairs as $pair) {
                $newDate = \Illuminate\Support\Carbon::parse($pair['date'])->subMilliseconds(100);
                DB::table('org_stock_movements')->where('id', $pair['associate_id'])
                    ->update(
                        [
                            'date'  => $newDate->format('Y-m-d H:i:s.u'),
                            'fixed' => true
                        ]
                    );
                $command?->warn("Associate movement {$pair['associate_id']} moved 100ms before purchase {$pair['purchase_id']} ");
            }

            return [
                'pairs' => $pairs,
            ];
        }

        return null;
    }

    public function fixForPrePurchaseAssociates(Location $location, OrgStock $orgStock, ?Command $command = null): void
    {
        $purchases = OrgStockMovement::where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::PURCHASE->value)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO->value])
            ->orderBy('date')
            ->get();

        foreach ($purchases as $purchase) {
            $lastAssociationMovement = DB::table('org_stock_movements')
                ->select('type')
                ->where('location_id', $location->id)
                ->where('org_stock_id', $orgStock->id)
                ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO->value])
                ->whereIn('type', [OrgStockMovementTypeEnum::ASSOCIATE->value, OrgStockMovementTypeEnum::DISASSOCIATE->value])
                ->where('date', '<', $purchase->date->format('Y-m-d H:i:s.u'))
                ->orderByDesc('date')
                ->orderByDesc('source_id')
                ->orderByDesc('id')
                ->first();

            if ($lastAssociationMovement?->type == OrgStockMovementTypeEnum::ASSOCIATE->value) {
                continue;
            }

            StoreOrgStockMovement::make()->action(
                $orgStock,
                $location,
                [
                    'quantity'         => 0,
                    'audited_quantity' => 0,
                    'org_amount'       => 0,
                    'date'             => Carbon::parse($purchase->date)->subMilliseconds(50)->format('Y-m-d H:i:s.u'),
                    'type'             => OrgStockMovementTypeEnum::ASSOCIATE,
                    'fixed'            => true,
                ]
            );

            $command?->warn("Added missing associate 50ms before purchase {$purchase->id}");
        }
    }


    public function fixForPostPurchaseAssociates(Location $location, OrgStock $orgStock, ?Command $command = null): void
    {
        $purchases = OrgStockMovement::where('location_id', $location->id)
            ->where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::PURCHASE->value)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO->value])
            ->orderBy('date')
            ->get();

        foreach ($purchases as $purchase) {
            $this->removePostAssociatesAfterPurchase($purchase, $command);
        }
    }

    public function removePostAssociatesAfterPurchase(OrgStockMovement $orgStockMovement, ?Command $command = null): void
    {
        $command?->warn("Fix associate post transactions $orgStockMovement->id ".$orgStockMovement->date->format('Y-m-d H:i:s.u'));

        $nextMovements = DB::table('org_stock_movements')->select('date', 'id', 'quantity', 'audited_quantity', 'type', 'class')
            ->where('location_id', $orgStockMovement->location_id)
            ->where('org_stock_id', $orgStockMovement->org_stock_id)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO->value])
            ->where('date', '>', $orgStockMovement->date->format('Y-m-d H:i:s.u'))
            ->orderByRaw('date,source_id,id')
            ->get();

        $associateWasRemoved = false;

        foreach ($nextMovements as $movement) {
            if ($movement->type == OrgStockMovementTypeEnum::DISASSOCIATE->value) {
                if ($associateWasRemoved) {
                    DB::table('org_stock_movements')->where('id', $movement->id)
                        ->update(
                            [
                                'class'            => OrgStockMovementClassEnum::GARBAGE->value,
                                'fixed'            => true,
                                'audited_quantity' => 0,
                                'quantity'         => 0,
                            ]
                        );
                    $command?->warn("Transform disassociate to garbage   $movement->id ");
                }

                break;
            }

            if ($movement->type == OrgStockMovementTypeEnum::ASSOCIATE->value) {
                DB::table('org_stock_movements')->where('id', $movement->id)
                    ->update(
                        [
                            'class'            => OrgStockMovementClassEnum::GARBAGE->value,
                            'fixed'            => true,
                            'audited_quantity' => 0,
                            'quantity'         => 0,
                        ]
                    );
                $associateWasRemoved = true;
                $command?->warn("Transform associate to garbage   $movement->id ");
            }
        }

        if (!$orgStockMovement->fixed_internal_helper) {
            $orgStockMovement->update([
                'fixed_internal_helper' => true,
            ]);
        }
    }

}
