<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Lead;

use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\CRM\Prospect;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $website
 */
class ProspectResource extends JsonResource
{
    use HasSelfCall;
    public function toArray($request): array
    {
        /** @var Prospect $prospect */
        $prospect = $this;

        return [
            'slug'                   => $prospect->slug,
            'name'                   => $prospect->name,
            'email'                  => $prospect->email,
            'phone'                  => $prospect->phone,
            'contact_website'        => $prospect->contact_website,
            'address'                => AddressResource::make($prospect->address),
            'customer'               => $prospect->customer_id ? CustomerResource::make($prospect->customer) : null,
            'created_at'             => $prospect->created_at,
            'updated_at'             => $prospect->updated_at,
            'state'                  => $prospect->state,
            'state_label'            => $prospect->state->labels()[$prospect->state->value],
            'contacted_state'        => $prospect->contacted_state,
            'contacted_state_label'  => $prospect->contacted_state->labels()[$prospect->contacted_state->value],
            'fail_status'            => $prospect->fail_status,
            'fail_status_label'      => $prospect->fail_status->labels()[$prospect->fail_status->value],
            'success_status'         => $prospect->success_status,
            'success_status_label'   => $prospect->success_status->labels()[$prospect->success_status->value],
            'dont_contact_me'        => $prospect->dont_contact_me,
            'is_opt_in'              => $prospect->is_opt_in,
            'can_contact_by_email'   => $prospect->can_contact_by_email,
            'can_contact_by_phone'   => $prospect->can_contact_by_phone,
            'can_contact_by_address' => $prospect->can_contact_by_address,
            'last_contacted_at'      => $prospect->last_contacted_at,
            'last_opened_at'         => $prospect->last_opened_at,
            'last_clicked_at'        => $prospect->last_clicked_at,
            'dont_contact_me_at'     => $prospect->dont_contact_me_at,
            'failed_at'              => $prospect->failed_at,
            'registered_at'          => $prospect->registered_at,
            'invoiced_at'            => $prospect->invoiced_at,
            'last_soft_bounced_at'   => $prospect->last_soft_bounced_at,


        ];
    }
}
