<?php

namespace App\Actions\Dispatching\Printer\Json;

use App\Actions\Dispatching\Printer\WithPrintNode;
use App\Actions\OrgAction;
use App\Models\Dispatching\Shipment;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Rawilk\Printing\Api\PrintNode\PendingPrintJob;
use Rawilk\Printing\Api\PrintNode\Resources\Computer;
use Rawilk\Printing\Api\PrintNode\Resources\PrintJob;
use Rawilk\Printing\Api\PrintNode\Enums\ContentType;
use Rawilk\Printing\Api\PrintNode\Resources\Printer;
use Rawilk\Printing\Facades\Printing;

class GetPrinters extends OrgAction
{

    use WithPrintNode;

    /**
     * Get all printers and computers from PrintNode API
     *
     * @return array
     * @throws Exception
     */
    public function handle()
    {
        $this->ensureClientInitialized();

        $options = [
            'limit' => 50,
            'dir' => 'desc',
        ];

        if ($after = $this->get('after')) {
            $options['after'] = $after;
        }

        return Printer::all($options);
    }

    public function jsonResponse(Collection $printers)
    {
        return $printers;
    }

    public function rules(): array
    {
        return [
            'after' => ['sometimes', 'integer'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('after', $request->get('after'));
    }



    public function asController(ActionRequest $request)
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle();
    }
}
