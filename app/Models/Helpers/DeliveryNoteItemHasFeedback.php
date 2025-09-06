<?php

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int|null $delivery_note_item_id original transaction
 * @property int|null $post_delivery_note_item_id Associated replacement transaction
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeliveryNoteItemHasFeedback newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeliveryNoteItemHasFeedback newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeliveryNoteItemHasFeedback query()
 * @mixin \Eloquent
 */
class DeliveryNoteItemHasFeedback extends Model
{
}
