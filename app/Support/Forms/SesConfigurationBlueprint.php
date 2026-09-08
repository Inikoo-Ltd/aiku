<?php

namespace App\Support\Forms;

use App\Enums\Comms\Ses\SesRegionEnum;
use Illuminate\Support\Arr;

class SesConfigurationBlueprint
{
    /**
     * @param  array<int, string>  $types
     * @return array<string, array<string, mixed>>
     */
    public static function make(array $settings, array $types): array
    {
        return collect($types)
            ->mapWithKeys(fn (string $type) => [
                $type => [
                    'type'         => 'field_group',
                    'label'        => self::label($type),
                    'noTitle'      => true,
                    'noSaveButton' => true,
                    'fields'       => self::fields($settings, $type),
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function fields(array $settings, string $type): array
    {
        return [
            self::key($type, 'access_id') => [
                'type'  => 'input',
                'label' => __('Access ID'),
                'value' => Arr::get($settings, self::settingsPath($type, 'access_id'), ''),
            ],
            self::key($type, 'access_key') => [
                'type'  => 'input',
                'label' => __('Access Key'),
                'value' => Arr::get($settings, self::settingsPath($type, 'access_key'), ''),
            ],
            self::key($type, 'region') => [
                'type'    => 'select',
                'searchable' => true,
                'label'   => __('Region'),
                'options' => SesRegionEnum::options(),
                'value'   => Arr::get($settings, self::settingsPath($type, 'region'), ''),
            ],
        ];
    }

    protected static function label(string $type): string
    {
        return __(ucfirst(str_replace('_', ' ', $type)));
    }

    protected static function settingsPath(string $type, string $field): string
    {
        return "email.provider.$type.$field";
    }

    protected static function key(string $type, string $field): string
    {
        return $type === 'failover' ? $field : "{$type}_{$field}";
    }
}
