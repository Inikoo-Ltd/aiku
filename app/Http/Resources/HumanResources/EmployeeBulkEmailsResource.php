<?php

namespace App\Http\Resources\HumanResources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $subject
 * @property string $body
 * @property int $number_recipients
 * @property array<int, array{path: string, name: string}>|null $attachments
 * @property string|null $sender_name
 * @property \Illuminate\Support\Carbon $created_at
 */
class EmployeeBulkEmailsResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'subject'           => $this->subject,
            'body'              => $this->body,
            'number_recipients' => $this->number_recipients,
            'attachments'       => array_column($this->attachments ?? [], 'name'),
            'sender_name'       => $this->sender_name,
            'created_at'        => $this->created_at,
        ];
    }
}
