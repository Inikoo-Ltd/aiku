<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 08:26:02 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Imports\CRM;

use App\Actions\CRM\Prospect\StoreProspect;
use App\Actions\CRM\Prospect\UpdateProspect;
use App\Imports\WithImport;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
use App\Models\Helpers\Upload;
use App\Models\Helpers\UploadRecord;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;

class ProspectImport implements ToCollection, WithEvents
{
    use WithImport;

    /**
     * The headers each field is recognised by, written the way normaliseHeader() leaves them.
     *
     * @var array<string, array<int, string>>
     */
    public const array HEADER_ALIASES = [
        'id_prospect_key' => ['id_prospect_key', 'prospect_key', 'prospect_id', 'id'],
        'company_name'    => ['company_name', 'company', 'business_name', 'business', 'organisation', 'organization'],
        'contact_name'    => ['contact_name', 'contact', 'full_name', 'name', 'customer_name'],
        'email'           => ['email', 'email_address', 'e_mail', 'e_mail_address', 'mail'],
        'phone'           => ['phone', 'phone_number', 'telephone', 'tel', 'mobile', 'mobile_number'],
    ];

    protected Shop $scope;

    public function __construct(Shop $scope, Upload $upload)
    {
        $this->scope  = $scope;
        $this->upload = $upload;
    }

    public function collection(Collection $collection): void
    {
        $columns   = $this->columnsByField($collection->first() ?? collect());
        $hasKeyColumn = array_key_exists('id_prospect_key', $columns);
        $rowNumber = 2;

        foreach ($collection->skip(1) as $row) {
            $values = $this->valuesByField($row, $columns);

            if (array_filter($values, fn ($value) => $value !== null) !== []) {
                $this->storeModel($values, $this->createUploadRecord(collect($values), $rowNumber), $hasKeyColumn);
            }

            $rowNumber++;
        }
    }

    public static function normaliseHeader(mixed $header): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string) $header))), '_');
    }

    /**
     * Which column each field lives in, read from the header row: the first header matching one
     * of a field's aliases wins, so the columns may come in any order and under any of the names.
     *
     * @return array<string, int>
     */
    protected function columnsByField(Collection $headerRow): array
    {
        $headers = $headerRow->map(fn ($header) => self::normaliseHeader($header))->all();
        $columns = [];

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $position = array_search($alias, $headers, true);
                if ($position !== false && !in_array($position, $columns, true)) {
                    $columns[$field] = $position;
                    break;
                }
            }
        }

        return $columns;
    }

    /**
     * @param array<string, int> $columns
     *
     * @return array<string, string|null>
     */
    protected function valuesByField(Collection $row, array $columns): array
    {
        $values = [];

        foreach (array_keys(self::HEADER_ALIASES) as $field) {
            $value          = array_key_exists($field, $columns) ? $row->get($columns[$field]) : null;
            $value          = is_scalar($value) ? trim((string) $value) : null;
            $values[$field] = $value === '' ? null : $value;
        }

        return $values;
    }

    /**
     * A sheet without a key column only adds prospects; with one, each row says "new" or names the
     * prospect it updates.
     *
     * @param array<string, string|null> $values
     */
    protected function storeModel(array $values, UploadRecord $uploadRecord, bool $hasKeyColumn): void
    {
        try {
            $prospectKey      = Arr::get($values, 'id_prospect_key');
            $existingProspect = is_numeric($prospectKey) ? $this->scope->prospects()->where('id', (int) $prospectKey)->first() : null;
            $isNew            = !$hasKeyColumn || strtolower((string) $prospectKey) === 'new';

            if (!$existingProspect && !$isNew) {
                throw new Exception(__('Prospect key not found'));
            }

            $validator = Validator::make($values, $this->rules($existingProspect));
            if ($validator->fails()) {
                $this->setRecordAsFailed($uploadRecord, $validator->errors()->all());

                return;
            }

            $modelData = Arr::only($validator->validated(), ['company_name', 'contact_name', 'email', 'phone']);

            if ($existingProspect) {
                UpdateProspect::run($existingProspect, $modelData);
            } else {
                StoreProspect::run($this->scope, $modelData);
            }

            $this->setRecordAsCompleted($uploadRecord);
        } catch (Exception $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(?Prospect $existingProspect = null): array
    {
        return [
            'id_prospect_key' => ['nullable'],
            'company_name'    => ['nullable', 'string', 'max:255'],
            'contact_name'    => ['required', 'string', 'max:255'],
            'email'           => [
                'nullable',
                'email',
                'max:500',
                Rule::unique('prospects', 'email')->where('shop_id', $this->scope->id)->ignore($existingProspect?->id),
            ],
            'phone'           => [
                'nullable',
                'string',
                Rule::unique('prospects', 'phone')->where('shop_id', $this->scope->id)->ignore($existingProspect?->id),
            ],
        ];
    }
}
