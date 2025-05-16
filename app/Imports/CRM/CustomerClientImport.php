<?php
/*
 * author Arya Permana - Kirin
 * created on 16-05-2025-08h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Imports\CRM;

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Imports\WithImport;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Helpers\Country;
use App\Rules\Phone;
use App\Models\Helpers\Upload;
use App\Rules\ValidAddress;
use Exception;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CustomerClientImport implements ToCollection, WithHeadingRow, SkipsOnFailure, WithValidation
{
    use WithImport;

    protected Customer $customer;
    protected Platform $platform;
    
    public function __construct(Customer $customer, Platform $platform, Upload $upload)
    {
        $this->customer  = $customer;
        $this->platform  = $platform;
        $this->upload = $upload;
    }

    public function storeModel($row, $uploadRecord): void
    {
        $fields = array_merge(
                array_keys(
                    $this->rules()
                )
            );

        $modelData = $row->only($fields)->all();
        $country = Country::where('code', Arr::pull($modelData, 'country_code'))->first();
        $addressData = [
            'address_line_1'        => $modelData['address_line_1'],
            'address_line_2'        => $modelData['address_line_2'] ?? null,
            'postal_code'           => $modelData['postal_code'],
            'locality'              => $modelData['locality'],
            'country_code'          => $country->code,
            'country_id'            => $country->id
        ];

        $phone = (string) Arr::pull($modelData, 'phone');
        
        data_set($modelData, 'address', $addressData);
        data_set($modelData, 'phone', $phone);

        data_set($modelData, 'data.bulk_import', [
            'id'   => $this->upload->id,
            'type' => 'Upload',
        ]);

        data_set($modelData, 'platform_id', $this->platform->id);

        try {
            StoreCustomerClient::make()->action(
                $this->customer,
                $modelData
            );

            $this->setRecordAsCompleted($uploadRecord);
        } catch (Exception $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        } catch (\Throwable $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }


    public function rules(): array
    {
        return [
            'contact_name'          => ['required', 'string', 'max:255'],
            'company_name'          => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email'],
            'phone'                 => ['required'],
            'address_line_1'        => ['required'],
            'address_line_2'        => ['nullable'],
            'postal_code'           => ['required'],
            'locality'              => ['required'],
            'country_code'          => ['required'],
        ];
    }
}
