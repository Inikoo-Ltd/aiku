<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Aug 2025 11:09:18 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CallApiGlsESShipping extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function getAccessToken(Shipper $shipper): array
    {
        if (app()->environment('production')) {
            return Arr::get($shipper->settings, 'access_token');
        } else {
            return json_decode(config('app.sandbox.shipper_gls_es_token'), true);
        }
    }

    public function getBaseUrl(): string
    {
        return 'https://wsclientes.asmred.com';
    }


    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shippe)
    {
        $uidCliente = "";

        $URL = "https://wsclientes.asmred.com/b2b.asmx?wsdl";

        $shipmentData                  = array();
        $shipmentData["fecha"]         = "02/04/2017";
        $shipmentData["servicio"]      = "96";
        $shipmentData["horario"]       = "18";
        $shipmentData["bultos"]        = "1";
        $shipmentData["peso"]          = "1";
        $shipmentData["reem"]          = "0";
        $shipmentData["nombreOrg"]     = "from name";
        $shipmentData["direccionOrg"]  = "from address";
        $shipmentData["poblacionOrg"]  = "from city";
        $shipmentData["codPaisOrg"]    = "ES";
        $shipmentData["cpOrg"]         = "08100";
        $shipmentData["nombreDst"]     = "consignee name";
        $shipmentData["direccionDst"]  = "consignee address";
        $shipmentData["poblacionDst"]  = "consignee city";
        $shipmentData["codPaisDst"]    = "ES";
        $shipmentData["cpDst"]         = "28004";
        $shipmentData["tfnoDst"]       = "935936688";
        $shipmentData["emailDst"]      = "test@test.com";
        $shipmentData["observaciones"] = "transport notes";
        $shipmentData["nif"]           = "11223344F";
        $shipmentData["portes"]        = "P";
        $shipmentData["RefC"]          = "1234561002x2";


        $XML = '<?xml version="1.0" encoding="utf-8"?>
         <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
         <soap12:Body>
         <GrabaServicios  xmlns="http://www.asmred.com/">
         <docIn>
            <Servicios uidcliente="' . $uidCliente . '" xmlns="http://www.asmred.com/">
            <Envio>
               <Fecha>' . $shipmentData["fecha"] . '</Fecha>
               <Servicio>' . $shipmentData["servicio"] . '</Servicio>
               <Horario>' . $shipmentData["horario"] . '</Horario>
               <Bultos>' . $shipmentData["bultos"] . '</Bultos>
               <Peso>' . $shipmentData["peso"] . '</Peso>
               <Portes>' . $shipmentData["portes"] . '</Portes>
               <Importes>
                  <Reembolso>'. $shipmentData["reem"] .'</Reembolso>
               </Importes>
               <Remite>
                  <Nombre>' . $shipmentData["nombreOrg"] . '</Nombre>
                  <Direccion>' . $shipmentData["direccionOrg"] . '</Direccion>
                  <Poblacion>' . $shipmentData["poblacionOrg"] . '</Poblacion>
                  <Pais>' . $shipmentData["codPaisOrg"] . '</Pais>
                  <CP>' . $shipmentData["cpOrg"] . '</CP>
               </Remite>
               <Destinatario>
                  <Nombre>' . $shipmentData["nombreDst"] . '</Nombre>
                  <Direccion>' . $shipmentData["direccionDst"] . '</Direccion>
                  <Poblacion>' . $shipmentData["poblacionDst"] . '</Poblacion>
                  <Pais>' . $shipmentData["codPaisDst"]. '</Pais>
                  <CP>' . $shipmentData["cpDst"] . '</CP>
                  <Telefono>' . $shipmentData["tfnoDst"] . '</Telefono>
                  <Email>' . $shipmentData["emailDst"] . '</Email>
                  <NIF>' . $shipmentData["nif"] . '</NIF>
                  <Observaciones>' . $shipmentData["observaciones"] . '</Observaciones>
               </Destinatario>
               <Referencias>
                  <Referencia tipo="C">' . $shipmentData["RefC"] . '</Referencia>
               </Referencias>
            </Envio>
            </Servicios>
            </docIn>
         </GrabaServicios>
         </soap12:Body>
         </soap12:Envelope>';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_URL, $URL);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $XML);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml; charset=UTF-8"));

        //  echo 'xml: ' . $XML . '<br><br>';

        $postResult = curl_exec($ch);
        curl_close($ch);
        echo 'postResult: ' . $postResult . '<br><br>';

        $xml = simplexml_load_string($postResult, null, null, "http://http://www.w3.org/2003/05/soap-envelope");
        $xml->registerXPathNamespace('asm', 'http://www.asmred.com/');
        $arr = $xml->xpath("//asm:GrabaServiciosResponse/asm:GrabaServiciosResult");




        $ret = $arr[0]->xpath("//Servicios/Envio");

        $return = $ret[0]->xpath("//Servicios/Envio/Resultado/@return");

        print_r($return);

        //   echo 'Return: ' . $return[0] . '<br/><br/>';

        $cb = $ret[0]->xpath("//Servicios/Envio/@codbarras");
        print_r($cb);

        //  echo 'Codigo barras: ' . $cb[0]["codbarras"];

        $uid = $ret[0]->xpath("//Servicios/Envio/@uid");

        print_r($uid);

        //  echo '<br/>uid: ' . $uid[0]["uid"];

    }



    public function getCommandSignature(): string
    {
        return 'gls_es_shipping:call';
    }

    public function asCommand()
    {
        /** @var DeliveryNote $deliveryNote */

        $shipper = Shipper::where('id', 1)->first();

        $deliveryNote = DeliveryNote::find(1);
        $this->handle($deliveryNote, $shipper);
    }

}
