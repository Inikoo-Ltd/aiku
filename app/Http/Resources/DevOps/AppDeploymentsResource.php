<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Github: aqordeon
 * Copyright (c) 2026, Vika Aqordi
 */

namespace App\Http\Resources\DevOps;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string|null $semantic_version
 * @property string|null $commit_hash
 * @property string|null $notes
 * @property string|null $change_log
 * @property array<int, array{name: string, email: string, github_username: string|null, avatar: string|null}>|null $committers
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class AppDeploymentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'semantic_version' => $this->semantic_version,
            'commit_hash'      => $this->commit_hash,
            'short_hash'       => $this->commit_hash ? substr($this->commit_hash, 0, 8) : null,
            'notes'            => $this->notes,
            'change_log'       => $this->change_log,
            'committers'       => $this->committers ?? [],
            'created_at'       => $this->created_at,
        ];
    }
}
