<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Apr 2023 11:32:21 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SysAdmin\OrganisationStats
 *
 * @property int $id
 * @property int $organisation_id
 * @property bool $has_fulfilment
 * @property bool $has_dropshipping
 * @property bool $has_production
 * @property bool $has_agents
 * @property int $number_images
 * @property int $filesize_images
 * @property int $number_attachments
 * @property int $filesize_attachments
 * @property int $number_uploads
 * @property int $number_upload_records
 * @property int $number_queries
 * @property int $number_static_queries is_static=true
 * @property int $number_dynamic_queries is_static=false
 * @property int $number_audits
 * @property int $number_audits_event_created
 * @property int $number_audits_event_updated
 * @property int $number_audits_event_deleted
 * @property int $number_audits_event_restored
 * @property int $number_audits_event_customer_note
 * @property int $number_audits_event_migrated
 * @property int $number_audits_event_other
 * @property int $number_audits_user_type_system
 * @property int $number_audits_user_type_user
 * @property int $number_audits_user_type_web_user
 * @property int $number_audits_user_type_other
 * @property int $number_audits_user_type_system_event_created
 * @property int $number_audits_user_type_system_event_updated
 * @property int $number_audits_user_type_system_event_deleted
 * @property int $number_audits_user_type_system_event_restored
 * @property int $number_audits_user_type_system_event_customer_note
 * @property int $number_audits_user_type_system_event_migrated
 * @property int $number_audits_user_type_system_event_other
 * @property int $number_audits_user_type_user_event_created
 * @property int $number_audits_user_type_user_event_updated
 * @property int $number_audits_user_type_user_event_deleted
 * @property int $number_audits_user_type_user_event_restored
 * @property int $number_audits_user_type_user_event_customer_note
 * @property int $number_audits_user_type_user_event_other
 * @property int $number_audits_user_type_web_user_event_created
 * @property int $number_audits_user_type_web_user_event_updated
 * @property int $number_audits_user_type_web_user_event_deleted
 * @property int $number_audits_user_type_web_user_event_restored
 * @property int $number_audits_user_type_web_user_event_customer_note
 * @property int $number_audits_user_type_web_user_event_other
 * @property int $number_audits_user_type_other_event_created
 * @property int $number_audits_user_type_other_event_updated
 * @property int $number_audits_user_type_other_event_deleted
 * @property int $number_audits_user_type_other_event_restored
 * @property int $number_audits_user_type_other_event_customer_note
 * @property int $number_audits_user_type_other_event_other
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationStats query()
 *
 * @mixin \Eloquent
 */
class OrganisationStats extends Model
{
    protected $table = 'organisation_stats';

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
