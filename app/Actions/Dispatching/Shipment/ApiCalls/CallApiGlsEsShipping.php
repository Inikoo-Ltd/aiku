<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Aug 2025 11:09:18 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Actions\Dispatching\Shipment\GetShippingDeliveryNoteData;
use App\Actions\OrgAction;
use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
use App\Http\Resources\Dispatching\ShippingPalletReturnResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CallApiGlsEsShipping extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function getAccessToken(Shipper $shipper): string
    {
        if (app()->environment('production')) {
            return Arr::get($shipper->settings, 'guid');
        } else {
            return config('app.sandbox.shipper_gls_es_token');
        }
    }

    public function getBaseUrl(): string
    {
        return 'https://wsclientes.asmred.com';
    }


    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shipper): array
    {
        if ($parent instanceof PalletReturn) {
            $parentResource = ShippingPalletReturnResource::make($parent)->getArray();
        } else {
            $parentResource = GetShippingDeliveryNoteData::run($parent);
        }

        $countryCode = Arr::get($parentResource, 'to_address.country_code');
        $limit = 31.5;
        $totalWeight = $parent->effective_weight / 1000;
        $totalParcel = count($parent->parcels ?? []);

        if ($totalWeight > $limit && ($countryCode !== 'ES' && $totalParcel > 1)) {
            return $this->splitByWeightLimit($parent, $shipper, $limit, $totalWeight);
        }

        $modelData = $this->createGlsEsShipment($parent, $shipper);
        if (Arr::has($modelData, 'error')) {
            return [
                'status'    => 'fail',
                'errorData' => [
                    'message' => Arr::get($modelData, 'error')
                ],
                'modelData' => []
            ];
        }

        return $this->getGlsEsLabel($shipper, $modelData);
    }

    public function splitByWeightLimit(DeliveryNote|PalletReturn $parent, Shipper $shipper, float $limit, float $totalWeight)
    {
        $parcels = $parent->parcels ?? [];
        $parcelWeights = [];
        foreach ($parcels as $parcel) {
            $parcelWeights[] = (float)Arr::get($parcel, 'weight', 1.0);
        }
        if (empty($parcelWeights)) {
            $parcelWeights[] = $totalWeight;
        }

        $weightsToShip = [];
        foreach ($parcelWeights as $w) {
            if ($w > $limit) {
                return [
                    'status'    => 'fail',
                    'errorData' => [
                        'message' => 'International shipping max weight per parcel is ' . $limit
                    ],
                    'modelData' => []
                ];
            } else {
                $weightsToShip[] = $w;
            }
        }

        $xmlPayloads = [];
        foreach ($weightsToShip as $index => $splitWeight) {
            $xmlPayloads[] = $this->getCreateLabelXml($parent, $shipper, $splitWeight, $index);
        }

        $headers = [
            "Content-Type: text/xml; charset=utf-8",
            "SOAPAction: \"http://www.asmred.com/GrabaServicios\""
        ];
        $url = $this->getBaseUrl() . "/b2b.asmx";
        $createResults = $this->executeParallelCurl($xmlPayloads, $url, $headers);

        $trackings = [];
        $soapResults = [];
        foreach ($createResults as $index => $res) {
            if (!$res['success'] || empty($res['content'])) {
                return [
                    'status'    => 'fail',
                    'errorData' => [
                        'message' => 'Split shipment failed (connection issue): ' . ($res['error'] ?? 'No response')
                    ],
                    'modelData' => []
                ];
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($res['content'], null, null, "http://schemas.xmlsoap.org/soap/envelope/");
            if ($xml === false) {
                return [
                    'status'    => 'fail',
                    'errorData' => [
                        'message' => 'Split shipment failed: Invalid XML response'
                    ],
                    'modelData' => []
                ];
            }

            $xml->registerXPathNamespace('asm', 'http://www.asmred.com/');
            $arr = $xml->xpath("//asm:GrabaServiciosResponse/asm:GrabaServiciosResult");
            if (empty($arr)) {
                return [
                    'status'    => 'fail',
                    'errorData' => [
                        'message' => 'Split shipment failed: GrabaServiciosResult not found'
                    ],
                    'modelData' => []
                ];
            }

            $ret = $arr[0]->xpath("//Servicios/Envio");
            if (empty($ret)) {
                return [
                    'status'    => 'fail',
                    'errorData' => [
                        'message' => 'Split shipment failed: Envio not found'
                    ],
                    'modelData' => []
                ];
            }

            $error = (string)$ret[0]->Errores->Error;
            if ($error) {
                return [
                    'status'    => 'fail',
                    'errorData' => [
                        'message' => 'Split shipment failed: ' . $error
                    ],
                    'modelData' => []
                ];
            }

            $codExp = $ret[0]->xpath("//Servicios/Envio/@codexp");
            $cb = $ret[0]->xpath("//Servicios/Envio/@codbarras");
            $uid = $ret[0]->xpath("//Servicios/Envio/@uid");

            $codbarras = (string)$cb[0]["codbarras"];
            $trackings[] = $codbarras;

            $soapResults[$index] = [
                'reference' => $codbarras,
                'trackings' => [$codbarras],
                'tracking' => $codbarras,
                'data' => [
                    'codexp' => (string)$codExp[0]["codexp"],
                    'codbarras' => $codbarras,
                    'uid' => (string)$uid[0]["uid"],
                ]
            ];
        }

        $uidClient = $this->getAccessToken($shipper);
        $labelXmlPayloads = [];
        foreach ($trackings as $reference) {
            $labelXmlPayloads[] = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
   <EtiquetaEnvioV2 xmlns="http://www.asmred.com/">
      <uidcliente>' . $uidClient . '</uidcliente>
      <codigo>' . $reference . '</codigo>
      <tipoEtiqueta>PDF</tipoEtiqueta>
   </EtiquetaEnvioV2>
</soap:Body>
</soap:Envelope>';
        }

        $labelHeaders = [
            "Content-Type: text/xml; charset=utf-8",
            "SOAPAction: \"http://www.asmred.com/EtiquetaEnvioV2\""
        ];
        $labelResults = $this->executeParallelCurl($labelXmlPayloads, $url, $labelHeaders);

        $labels = [];
        $lastModelData = [];
        foreach ($labelResults as $index => $res) {
            if (!$res['success'] || empty($res['content'])) {
                return [
                    'status'    => 'fail',
                    'errorData' => [
                        'message' => 'Failed to retrieve label for split shipment (connection issue): ' . ($res['error'] ?? 'No response')
                    ],
                    'modelData' => []
                ];
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($res['content']);
            if ($xml === false) {
                return [
                    'status'    => 'fail',
                    'errorData' => [
                        'message' => 'Failed to retrieve label for split shipment: Invalid XML response'
                    ],
                    'modelData' => []
                ];
            }

            $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('asm', 'http://www.asmred.com/');
            $result = $xml->xpath('//soap:Body/asm:EtiquetaEnvioV2Response/asm:EtiquetaEnvioV2Result/Etiquetas/Etiqueta');

            if ($result === false || empty($result)) {
                return [
                    'status'    => 'fail',
                    'errorData' => [
                        'message' => 'Failed to retrieve label for split shipment: No label found'
                    ],
                    'modelData' => []
                ];
            }

            $labelBase64 = (string)$result[0];
            $labels[] = base64_decode($labelBase64);

            $modelData = $soapResults[$index];
            $modelData['label'] = $labelBase64;
            $modelData['label_type'] = ShipmentLabelTypeEnum::PDF;
            $modelData['number_parcels'] = 1;
            $lastModelData = $modelData;
        }

        try {
            $mergedLabelBase64 = $this->mergePdfStrings($labels);
        } catch (\Throwable $e) {
            return [
                'status'    => 'fail',
                'errorData' => [
                    'message' => 'Failed to merge PDF labels: ' . $e->getMessage()
                ],
                'modelData' => []
            ];
        }

        $finalModelData = $lastModelData;
        $finalModelData['tracking'] = implode(',', $trackings);
        $finalModelData['trackings'] = $trackings;
        $finalModelData['label'] = $mergedLabelBase64;
        $finalModelData['number_parcels'] = count($trackings);

        return [
            'status'    => 'success',
            'modelData' => $finalModelData,
            'errorData' => []
        ];
    }

    public function getGlsEsLabel(Shipper $shipper, array $modelData): array
    {
        $status    = 'fail';
        $errorData = [];

        $uidClient = $this->getAccessToken($shipper);
        $reference = $modelData['data']['codbarras'];
        $url       = $this->getBaseUrl() . "/b2b.asmx";

        $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
   <EtiquetaEnvioV2 xmlns="http://www.asmred.com/">
      <uidcliente>' . $uidClient . '</uidcliente>
      <codigo>' . $reference . '</codigo>
      <tipoEtiqueta>PDF</tipoEtiqueta>
   </EtiquetaEnvioV2>
</soap:Body>
</soap:Envelope>';


        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: text/xml; charset=utf-8",
            "SOAPAction: \"http://www.asmred.com/EtiquetaEnvioV2\""
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $postResult = curl_exec($ch);

        if (curl_errno($ch)) {
            $errorData = [
                'message' => 'No se pudo llamar al WS de GLS: ' . curl_error($ch),
            ];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($postResult);

        $numberParcels = 0;

        if ($xml === false) {
            $errorData = [
                'message' => 'No se encontraron etiquetas',
            ];
        } else {
            $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('asm', 'http://www.asmred.com/');

            $result = $xml->xpath('//soap:Body/asm:EtiquetaEnvioV2Response/asm:EtiquetaEnvioV2Result/Etiquetas/Etiqueta');

            if ($result === false) {
                $errorData = [
                    'message' => 'Error en la consulta XPath'
                ];
            } elseif (empty($result)) {
                $errorData = [
                    'message' => 'No se encontraron etiquetas'
                ];
            } else {
                $status        = 'success';
                $numberParcels = count($result);
                for ($i = 0; $i < count($result); $i++) {
                    $modelData['label'] = (string)$result[$i];
                }
            }
        }
        data_set($modelData, 'label_type', ShipmentLabelTypeEnum::PDF);
        data_set($modelData, 'number_parcels', $numberParcels);

        return [
            'status'    => $status,
            'modelData' => $modelData,
            'errorData' => $errorData,
        ];
    }

    public function createGlsEsShipment(DeliveryNote|PalletReturn $parent, Shipper $shipper, ?float $splitWeight = null): array
    {
        $modelData = [];

        $url = $this->getBaseUrl() . "/b2b.asmx";
        $ch  = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getCreateLabelXml($parent, $shipper, $splitWeight));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: text/xml; charset=utf-8",
            "SOAPAction: \"http://www.asmred.com/GrabaServicios\""
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $postResult = curl_exec($ch);
        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            $modelData['error'] = 'No se pudo llamar al WS de GLS: ' . $errorMsg;

            return $modelData;
        }
        curl_close($ch);

        if ($postResult === false || $postResult === '') {
            $modelData['error'] = 'No response from GLS Spain API';

            return $modelData;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($postResult, null, null, "http://schemas.xmlsoap.org/soap/envelope/");

        if ($xml === false) {
            $modelData['error'] = 'Invalid XML response from GLS Spain API';

            return $modelData;
        }

        $xml->registerXPathNamespace('asm', 'http://www.asmred.com/');
        $arr = $xml->xpath("//asm:GrabaServiciosResponse/asm:GrabaServiciosResult");

        if (empty($arr)) {
            $modelData['error'] = 'GrabaServiciosResult not found in response';

            return $modelData;
        }

        $ret = $arr[0]->xpath("//Servicios/Envio");

        if (empty($ret)) {
            $modelData['error'] = 'Envio not found in response';

            return $modelData;
        }

        $error = (string)$ret[0]->Errores->Error;


        if ($error) {
            $modelData['error'] = $error;

            return $modelData;
        }


        $codExp                      = $ret[0]->xpath("//Servicios/Envio/@codexp");
        $modelData['data']['codexp'] = (string)$codExp[0]["codexp"];

        $cb                             = $ret[0]->xpath("//Servicios/Envio/@codbarras");
        $modelData['data']['codbarras'] = (string)$cb[0]["codbarras"];

        $uid                      = $ret[0]->xpath("//Servicios/Envio/@uid");
        $modelData['data']['uid'] = (string)$uid[0]["uid"];


        $modelData['reference']   = $modelData['data']['codbarras'];
        $modelData['trackings'][] = $modelData['data']['codbarras'];
        $modelData['tracking']    = $modelData['data']['codbarras'];

        return $modelData;
    }

    public function getCreateLabelXml(DeliveryNote|PalletReturn $parent, Shipper $shipper, ?float $splitWeight = null, ?int $suffix = null): string
    {
        $uidClient = $this->getAccessToken($shipper);

        if ($parent instanceof PalletReturn) {
            $parentResource = ShippingPalletReturnResource::make($parent)->getArray();
        } else {
            $parentResource = GetShippingDeliveryNoteData::run($parent);
        }
        $parcels = $parent->parcels;


        $fromContactName = Str::limit(Arr::get($parentResource, 'from_company_name'), 60);
        if (!$fromContactName) {
            $fromContactName = Str::limit(Arr::get($parentResource, 'from_contact_name'), 60);
        }
        if (!$fromContactName) {
            $fromContactName = 'Seller';
        }

        $contactName = Str::limit(Arr::get($parentResource, 'to_contact_name'), 60);
        if (!$contactName) {
            $contactName = Str::limit(Arr::get($parentResource, 'to_company_name'), 60);
        }
        if (!$contactName) {
            $contactName = 'anonymous';
        }

        $shippingNotes = $parent->shipping_notes ?? '';
        $shippingNotes = Str::limit(preg_replace("/[^A-Za-z0-9 \-]/", '', strip_tags($shippingNotes), 60));

        $weight = $splitWeight ?? ($parent->effective_weight / 1000);

        $countryCode = Arr::get($parentResource, 'to_address.country_code');
        if ($countryCode == 'ES'  || $countryCode == 'PT') {
            $service = '1';
        } else {
            $service = '74';
        }

        $shipmentData                      = array();
        $shipmentData["date"]              = Carbon::now()->format('d/m/Y');
        $shipmentData["service"]           = $service;
        $shipmentData["time"]              = "18";
        $shipmentData["parcels"]           = $splitWeight ? 1 : count($parcels);
        $shipmentData["weight"]            = $weight;
        $shipmentData["reem"]              = "0";
        $shipmentData["from_name"]         = $fromContactName;
        $shipmentData["from_address"]      = trim(Arr::get($parentResource, 'from_address.address_line_1') . ' ' . Arr::get($parentResource, 'from_address.address_line_2'));
        $shipmentData["from_city"]         = Arr::get($parentResource, 'from_address.locality');
        $shipmentData["from_country_code"] = Arr::get($parentResource, 'from_address.country_code');
        $shipmentData["from_postal_code"]  = Arr::get($parentResource, 'from_address.postal_code');

        $shipmentData["to_name"]         = $contactName;
        $shipmentData["to_address"]      = trim(Arr::get($parentResource, 'to_address.address_line_1') . ' ' . Arr::get($parentResource, 'to_address.address_line_2'));
        $shipmentData["to_city"]         = Arr::get($parentResource, 'to_address.locality');
        $shipmentData["to_country_code"] = Arr::get($parentResource, 'to_address.country_code');
        $shipmentData["to_postal_code"]  = Arr::get($parentResource, 'to_address.postal_code');
        $shipmentData["to_phone"]        = Arr::get($parentResource, 'to_phone');
        $shipmentData["to_email"]        = Arr::get($parentResource, 'to_email');
        $shipmentData["notes"]           = $shippingNotes;
        $shipmentData["nif"]             = "";
        $shipmentData["portage"]         = "P";

        if (app()->environment('local')) {
            $shipmentData["RefC"] = 'test+' . rand(1000, 9999) . ' ' . strtoupper($parent->reference) . ' V2';
        } else {
            $shipmentData["RefC"] = strtoupper($parent->reference) . ' V2' . ($suffix !== null ? '-b-' . ($suffix + 1) : '');
        }


        if (Arr::get($parentResource, 'cash_on_delivery') && ($suffix === null || $suffix === 0)) {
            $amount               = Arr::get($parentResource, 'cash_on_delivery.amount');
            $amount               = str_replace('.', ',', (string)$amount);
            $shipmentData["reem"] = $amount;
        }

        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
<GrabaServicios xmlns="http://www.asmred.com/">
<docIn>
   <Servicios uidcliente="' . $uidClient . '" xmlns="http://www.asmred.com/">
      <Envio>
         <Fecha>' . $shipmentData["date"] . '</Fecha>
         <Servicio>' . $shipmentData["service"] . '</Servicio>
         <Horario>' . $shipmentData["time"] . '</Horario>
         <Bultos>' . $shipmentData["parcels"] . '</Bultos>
         <Peso>' . $shipmentData["weight"] . '</Peso>
         <Portes>' . $shipmentData["portage"] . '</Portes>
         <Importes>
            <Reembolso>' . $shipmentData["reem"] . '</Reembolso>
         </Importes>
         <Remite>
            <Nombre>' . $shipmentData["from_name"] . '</Nombre>
            <Direccion>' . $shipmentData["from_address"] . '</Direccion>
            <Poblacion>' . $shipmentData["from_city"] . '</Poblacion>
            <Pais>' . $shipmentData["from_country_code"] . '</Pais>
            <CP>' . $shipmentData["from_postal_code"] . '</CP>
         </Remite>
         <Destinatario>
            <Nombre>' . $shipmentData["to_name"] . '</Nombre>
            <Direccion>' . $shipmentData["to_address"] . '</Direccion>
            <Poblacion>' . $shipmentData["to_city"] . '</Poblacion>
            <Pais>' . $shipmentData["to_country_code"] . '</Pais>
            <CP>' . $shipmentData["to_postal_code"] . '</CP>
            <Telefono>' . $shipmentData["to_phone"] . '</Telefono>
            <Email>' . $shipmentData["to_email"] . '</Email>
            <NIF>' . $shipmentData["nif"] . '</NIF>
            <Observaciones>' . $shipmentData["notes"] . '</Observaciones>
         </Destinatario>
         <Referencias>
            <Referencia tipo="C">' . $shipmentData["RefC"] . '</Referencia>
         </Referencias>
      </Envio>
   </Servicios>
</docIn>
</GrabaServicios>
</soap:Body>
</soap:Envelope>';
    }

    private function mergePdfStrings(array $pdfStrings): string
    {
        $mpdf = new \Mpdf\Mpdf();
        foreach ($pdfStrings as $pdfString) {
            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_merge_');
            file_put_contents($tempFile, $pdfString);
            try {
                $pageCount = $mpdf->setSourceFile($tempFile);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $tplId = $mpdf->importPage($i);
                    $size = $mpdf->getTemplateSize($tplId);
                    $orientation = ($size['height'] > $size['width']) ? 'P' : 'L';
                    $mpdf->AddPageByArray([
                        'orientation' => $orientation,
                        'newformat' => [$size['width'], $size['height']],
                        'mgl' => 0,
                        'mgr' => 0,
                        'mgt' => 0,
                        'mgb' => 0,
                    ]);
                    $mpdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);
                }
            } finally {
                @unlink($tempFile);
            }
        }
        return base64_encode($mpdf->Output('', 'S'));
    }

    public function executeParallelCurl(array $xmlPayloads, string $url, array $headers): array
    {
        $mh = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($xmlPayloads as $key => $xml) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            } else {
                usleep(100);
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        foreach ($handles as $key => $ch) {
            $error = curl_error($ch);
            $content = curl_multi_getcontent($ch);
            if ($error) {
                $results[$key] = [
                    'success' => false,
                    'error' => $error,
                    'content' => null,
                ];
            } else {
                $results[$key] = [
                    'success' => true,
                    'error' => null,
                    'content' => $content,
                ];
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);

        return $results;
    }
}
