<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\SysAdmin\McpChange\GetMcpChangeSnapshot;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\SysAdmin\McpChange;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;

/**
 * Every MCP write runs through recordChange, so no tool can change data without leaving the
 * before and after needed to revert it.
 */
trait WithMcpChangeLog
{
    protected ?McpChange $mcpChange = null;

    protected function recordChange(Request $request, McpChangeTypeEnum $type, string $label, array $target, callable $change, array $data = []): mixed
    {
        $snapshot = GetMcpChangeSnapshot::make();

        $before = $snapshot->handle($type, $target);
        $result = $change();
        $after  = $snapshot->handle($type, $target);

        if ($before != $after) {
            $user = $request->user();

            $this->mcpChange = McpChange::create([
                'group_id'     => $user->group_id,
                'user_id'      => $user->id,
                'type'         => $type,
                'tool'         => $this->name(),
                'label'        => Str::limit($label, 250),
                'request_text' => $request->get('request_text'),
                'before'       => $before,
                'after'        => $after,
                'data'         => array_merge($data, [
                    'target'      => $target,
                    'before_text' => $snapshot->describe($type, $target, $before),
                    'after_text'  => $snapshot->describe($type, $target, $after),
                ]),
            ]);
        }

        return $result;
    }
}
