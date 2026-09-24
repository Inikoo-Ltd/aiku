<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use App\Models\SysAdmin\McpChange;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string|null $username
 * @property string|null $reverted_by_username
 */
class McpChangesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var McpChange $mcpChange */
        $mcpChange = $this->resource;

        return [
            'id'                   => $mcpChange->id,
            'created_at'           => $mcpChange->created_at,
            'username'             => $this->username,
            'type'                 => $mcpChange->type->value,
            'type_label'           => $mcpChange->type->labels()[$mcpChange->type->value],
            'label'                => $mcpChange->label,
            'request_text'         => $mcpChange->request_text,
            'shops'                => $mcpChange->data['shops'] ?? [],
            'before_text'          => $mcpChange->data['before_text'] ?? null,
            'after_text'           => $mcpChange->data['after_text'] ?? null,
            'reverted_at'          => $mcpChange->reverted_at,
            'reverted_by_username' => $this->reverted_by_username,
            'can_revert'           => $mcpChange->canBeRevertedBy($request->user()),
            'revert_route'         => [
                'name'       => 'grp.models.mcp_change.revert',
                'parameters' => ['mcpChange' => $mcpChange->id],
                'method'     => 'post',
            ],
        ];
    }
}
