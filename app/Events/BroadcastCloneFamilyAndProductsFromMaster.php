<?php

/*
 * Author Louis Perez
 * Created on 29-07-2026-11h-14m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Events;

use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class BroadcastCloneFamilyAndProductsFromMaster implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public User $user;
    public MasterProductCategory $masterFamily;
    public int|float $pendingFamilies;
    public int|float $doneFamilies;
    public int|float $pendingProducts;
    public int|float $doneProducts;

    public function __construct(User $user, MasterProductCategory $masterFamily, int|float $pendingFamilies, int|float $doneFamilies, int|float $pendingProducts, int|float $doneProducts)
    {
        $this->user = $user;
        $this->masterFamily = $masterFamily;
        $this->pendingFamilies = $pendingFamilies;
        $this->doneFamilies = $doneFamilies;
        $this->pendingProducts = $pendingProducts;
        $this->doneProducts = $doneProducts;
    }

    public function broadcastWith(): array
    {
        $familyProgress = [
            'action_type'       => 'add_missing_family',
            'action_id'         => $this->masterFamily->id,
            'number_rows'       => $this->pendingFamilies,
            'number_success'    => $this->doneFamilies,
            'number_fail'       => 0
        ];

        $productProgress = [
            'action_type'       => 'add_missing_product',
            'action_id'         => $this->masterFamily->id,
            'number_rows'       => $this->pendingProducts,
            'number_success'    => $this->doneProducts,
            'number_fail'       => 0
        ];

        $sentData = [
            'master_family'      =>  $this->masterFamily->code,

            'family_progress'    =>  $familyProgress,
            'pending_families'   =>  $this->pendingFamilies,
            'done_families'      =>  $this->doneFamilies,

            'product_progress'   =>  $productProgress,
            'pending_products'   =>  $this->pendingProducts,
            'done_products'      =>  $this->doneProducts,
        ];

        if ($this->doneFamilies == $this->pendingFamilies) {
            $sentData = Arr::except($sentData, [
                'family_progress',
                'pending_families',
                'done_families',
            ]);
        }

        return $sentData;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("grp.personal.{$this->user->id}");
    }

    public function broadcastAs(): string
    {
        return 'clone-family-progress';
    }
}
