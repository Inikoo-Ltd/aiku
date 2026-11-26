<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-10h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBeUnique;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A ping for the staff app screen showing this record: the app reloads it the normal way. It carries no data, and
 * a burst of changes to one record becomes one ping while the first is still waiting on the queue.
 */
class BroadcastMobileRecordChanged implements ShouldBroadcast, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithSockets;

    public bool $afterCommit = true;

    public int $uniqueFor = 60;

    /**
     * @var array<string, array{0: string, 1: string}> model => record type, column with the record id
     */
    public const array RECORDS = [
        'DeliveryNote'           => ['delivery_note', 'id'],
        'DeliveryNoteItem'       => ['delivery_note', 'delivery_note_id'],
        'PickingSession'         => ['picking_session', 'id'],
        'ReturnDeliveryNote'     => ['return', 'id'],
        'ReturnDeliveryNoteItem' => ['return', 'return_delivery_note_id'],
        'PalletReturn'           => ['pallet_return', 'id'],
        'PalletReturnItem'       => ['pallet_return', 'pallet_return_id'],
        'StockDelivery'          => ['stock_delivery', 'id'],
        'StockDeliveryItem'      => ['stock_delivery', 'stock_delivery_id'],
    ];

    public function __construct(public string $type, public int $id)
    {
    }

    public static function channelName(string $type, int $id): string
    {
        return 'grp.mobile.'.$type.'.'.$id;
    }

    public static function dispatchFor(?Model $model): void
    {
        [$type, $column] = $model ? (self::RECORDS[class_basename($model)] ?? [null, null]) : [null, null];
        $id              = $type ? (int) $model->getAttribute($column) : 0;

        if ($id > 0) {
            rescue(fn () => self::dispatch($type, $id));
        }
    }

    public function uniqueId(): string
    {
        return $this->type.':'.$this->id;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channelName($this->type, $this->id))];
    }

    public function broadcastAs(): string
    {
        return 'record-changed';
    }

    public function broadcastWith(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}
