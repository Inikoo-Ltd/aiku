<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Aug 2025 11:09:18 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Actions\Dispatching\Shipment\GwtShippingDeliveryNoteData;
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
        $modelData = $this->createGlsEsShipment($parent, $shipper);

        return $this->getGlsEsLabel($shipper, $modelData);
    }

    public function getGlsEsLabel(Shipper $shipper, array $modelData): array
    {
        $status    = 'fail';
        $errorData = [];

        $uidClient = $this->getAccessToken($shipper);
        $reference = $modelData['data']['codbarras'];
        $url       = $this->getBaseUrl()."/b2b.asmx?wsdl";

        $xml = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:asm="http://www.asmred.com/">
<soap:Header/>
<soap:Body>
   <asm:EtiquetaEnvioV2>
      <!--Optional:-->
      <uidcliente>'.$uidClient.'</uidcliente>
      <asm:codigo>'.$reference.'</asm:codigo>
      <asm:tipoEtiqueta>PDF</asm:tipoEtiqueta>
   </asm:EtiquetaEnvioV2>
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml; charset=UTF-8"));

        $postResult = curl_exec($ch);

        if (curl_errno($ch)) {
            $errorData = [
                'message' => 'No se pudo llamar al WS de GLS',
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
            $xml->registerXPathNamespace('soap', 'http://www.w3.org/2003/05/soap-envelope');
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

    public function createGlsEsShipment(DeliveryNote|PalletReturn $parent, Shipper $shipper): array
    {
        $modelData = [];

        $url = $this->getBaseUrl()."/b2b.asmx?wsdl";
        $ch  = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getCreateLabelXml($parent, $shipper));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml; charset=UTF-8"));


        $postResult = curl_exec($ch);
        curl_close($ch);

        $xml = simplexml_load_string($postResult, null, null, "http://http://www.w3.org/2003/05/soap-envelope");


        $xml->registerXPathNamespace('asm', 'http://www.asmred.com/');
        $arr = $xml->xpath("//asm:GrabaServiciosResponse/asm:GrabaServiciosResult");


        $ret = $arr[0]->xpath("//Servicios/Envio");


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

    public function getCreateLabelXml(DeliveryNote|PalletReturn $parent, Shipper $shipper): string
    {
        $uidClient = $this->getAccessToken($shipper);

        if ($parent instanceof PalletReturn) {
            $parentResource = ShippingPalletReturnResource::make($parent)->getArray();
        } else {
            $parentResource = GwtShippingDeliveryNoteData::run($parent);
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

        $weight = $parent->effective_weight / 1000;

        $shipmentData                      = array();
        $shipmentData["date"]              = Carbon::now()->format('d/m/Y');
        $shipmentData["service"]           = "1";
        $shipmentData["time"]              = "18";
        $shipmentData["parcels"]           = count($parcels);
        $shipmentData["weight"]            = $weight;
        $shipmentData["reem"]              = "0";
        $shipmentData["from_name"]         = $fromContactName;
        $shipmentData["from_address"]      = trim(Arr::get($parentResource, 'from_address.address_line_1').' '.Arr::get($parentResource, 'from_address.address_line_2'));
        $shipmentData["from_city"]         = Arr::get($parentResource, 'from_address.locality');
        $shipmentData["from_country_code"] = Arr::get($parentResource, 'from_address.country_code');
        $shipmentData["from_postal_code"]  = Arr::get($parentResource, 'from_address.postal_code');

        $shipmentData["to_name"]         = $contactName;
        $shipmentData["to_address"]      = trim(Arr::get($parentResource, 'to_address.address_line_1').' '.Arr::get($parentResource, 'to_address.address_line_2'));
        $shipmentData["to_city"]         = Arr::get($parentResource, 'to_address.locality');
        $shipmentData["to_country_code"] = Arr::get($parentResource, 'to_address.country_code');
        $shipmentData["to_postal_code"]  = Arr::get($parentResource, 'to_address.postal_code');
        $shipmentData["to_phone"]        = Arr::get($parentResource, 'to_phone');
        $shipmentData["to_email"]        = Arr::get($parentResource, 'to_email');
        $shipmentData["notes"]           = $shippingNotes;
        $shipmentData["nif"]             = "";
        $shipmentData["portage"]         = "P";
        $shipmentData["RefC"]            = strtoupper($parent->reference);


        return '<?xml version="1.0" encoding="utf-8"?>
         <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
         <soap12:Body>
         <GrabaServicios  xmlns="http://www.asmred.com/">
         <docIn>
            <Servicios uidcliente="'.$uidClient.'" xmlns="http://www.asmred.com/">
            <Envio>
               <Fecha>'.$shipmentData["date"].'</Fecha>
               <Servicio>'.$shipmentData["service"].'</Servicio>
               <Horario>'.$shipmentData["time"].'</Horario>
               <Bultos>'.$shipmentData["parcels"].'</Bultos>
               <Peso>'.$shipmentData["weight"].'</Peso>
               <Portes>'.$shipmentData["portage"].'</Portes>
               <Importes>
                  <Reembolso>'.$shipmentData["reem"].'</Reembolso>
               </Importes>
               <Remite>
                  <Nombre>'.$shipmentData["from_name"].'</Nombre>
                  <Direccion>'.$shipmentData["from_address"].'</Direccion>
                  <Poblacion>'.$shipmentData["from_city"].'</Poblacion>
                  <Pais>'.$shipmentData["from_country_code"].'</Pais>
                  <CP>'.$shipmentData["from_postal_code"].'</CP>
               </Remite>
               <Destinatario>
                  <Nombre>'.$shipmentData["to_name"].'</Nombre>
                  <Direccion>'.$shipmentData["to_address"].'</Direccion>
                  <Poblacion>'.$shipmentData["to_city"].'</Poblacion>
                  <Pais>'.$shipmentData["to_country_code"].'</Pais>
                  <CP>'.$shipmentData["to_postal_code"].'</CP>
                  <Telefono>'.$shipmentData["to_phone"].'</Telefono>
                  <Email>'.$shipmentData["to_email"].'</Email>
                  <NIF>'.$shipmentData["nif"].'</NIF>
                  <Observaciones>'.$shipmentData["notes"].'</Observaciones>
               </Destinatario>
               <Referencias>
                  <Referencia tipo="C">'.$shipmentData["RefC"].'</Referencia>
               </Referencias>
            </Envio>
            </Servicios>
            </docIn>
         </GrabaServicios>
         </soap12:Body>
         </soap12:Envelope>';
    }


}
