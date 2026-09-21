<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Tue, 16 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Http\Resources\Production;

use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $name
 * @property string $state
 * @property mixed $published_at
 * @property mixed $updated_at
 * @property array $layout
 * @property string $artefact_code
 * @property string $artefact_name
 * @property string $artefact_slug
 * @property string|null $artwork_mime_type
 */
class ArtefactLabelsResource extends JsonResource
{
    public function toArray($request): array
    {
        $state = $this->state instanceof ArtefactLabelStateEnum ? $this->state->value : $this->state;

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'state'         => ArtefactLabelStateEnum::stateIcon()[$state],
            'state_label'   => ArtefactLabelStateEnum::labels()[$state],
            'artefact_code' => $this->artefact_code,
            'artefact_name' => $this->artefact_name,
            'artefact_slug' => $this->artefact_slug,
            'grid'          => $this->getGrid(),
            'sources'       => $this->getSources(),
            'artwork'       => $this->artwork_mime_type
                ? ($this->artwork_mime_type === 'application/pdf' ? 'pdf' : 'image')
                : null,
            'published_at'  => $this->published_at,
            'updated_at'    => $this->updated_at,
        ];
    }

    private function getGrid(): string
    {
        $columns = data_get($this->layout, 'columns');
        $rows    = data_get($this->layout, 'rows');

        return $columns && $rows ? $columns.' × '.$rows : '-';
    }

    /**
     * The variable texts the label prints, so the index shows at a glance which labels carry a
     * batch code, an expiry date or a barcode.
     *
     * @return array<int, string>
     */
    private function getSources(): array
    {
        return array_values(array_unique(array_map(
            fn (array $field) => $field['source'] ?? 'batch_code',
            data_get($this->layout, 'fields', []) ?? []
        )));
    }
}
