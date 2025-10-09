<?php

namespace App\Actions\Dispatching\Printer;

use App\Actions\OrgAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Rawilk\Printing\Api\PrintNode\Resources\PrintJob;
use App\Actions\Traits\WithPrintNode;
use Illuminate\Support\Facades\Log;

class PrintBarcode extends OrgAction
{
    use WithPrintNode;

    public function handle(array $modelData): PrintJob|RedirectResponse
    {
        $barcode = Arr::get($modelData, 'barcode');
        try {
            $mpdf = new \Mpdf\Mpdf();
            $generator = new BarcodeGeneratorPNG();
            $barcodeImage = $generator->getBarcode($barcode, $generator::TYPE_CODE_128);
            $barcodeBase64 = base64_encode($barcodeImage);
            $html = '
                <div style="
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    width: 100%;
                    text-align: center;
                ">
                    <img src="data:image/png;base64,' . $barcodeBase64 . '" style="max-width: 100%; max-height: 100%; object-fit: contain;" />
                    <div style="margin-top: 10px; font-family: monospace; font-size: 18px; font-weight: bold;">' . htmlspecialchars($barcode) . '</div>
                </div>';
            $mpdf->WriteHTML($html);
            $pdfContent = $mpdf->Output('', 'S');
            $pdfBase64 = base64_encode($pdfContent);
            $res = $this->printPdf(
                title: __('Barcode for :barcode', ['barcode' => $this->get('barcode')]),
                printId: $this->get('printerId'),
                pdfBase64: $pdfBase64
            );
            return $res;
        } catch (\Throwable $e) {
            Log::error('Error printing barcode: ' . $e->getMessage());
            throw ValidationException::withMessages([
                'messages' => __('Error printing barcode'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function afterValidator(Validator $validator): void
    {
        $user      = request()->user();
        $printerId = Arr::get($user->settings, 'preferred_printer_id');
        if (!$printerId) {
            throw ValidationException::withMessages([
                'messages' => __('You must set a preferred printer in your user settings!'),
            ]);
        }

        $printByPrintNode = Arr::get($user->group->settings, 'printnode.print_by_printnode', false);
        if (!$printByPrintNode) {
            throw ValidationException::withMessages([
                'messages' => __('Print by Printnode is not enabled for your group!'),
            ]);
        }


        $this->set('printerId', $printerId);
    }

    public function maya(ActionRequest $request): \Rawilk\Printing\Api\PrintNode\Resources\PrintJob|RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->validatedData);
    }

    public function jsonResponse(PrintJob|RedirectResponse $result): PrintJob|RedirectResponse
    {
        return $result;
    }
}
