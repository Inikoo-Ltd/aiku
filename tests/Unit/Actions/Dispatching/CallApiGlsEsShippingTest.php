<?php

use App\Actions\Dispatching\Shipment\ApiCalls\CallApiGlsEsShipping;

it('splits every international multi parcel shipment regardless of weight', function (?string $country, int $parcels, bool $expected) {
    expect((new CallApiGlsEsShipping())->requiresPerParcelShipments($country, $parcels))->toBe($expected);
})->with([
    'FR 2 light parcels'  => ['FR', 2, true],
    'DE 3 parcels'        => ['DE', 3, true],
    'FR single parcel'    => ['FR', 1, false],
    'ES multi parcel'     => ['ES', 3, false],
    'PT multi parcel'     => ['PT', 2, false],
    'missing country'     => [null, 2, true],
]);

function glsEsLabelResponse(array $base64Labels): string
{
    $etiquetas = implode('', array_map(fn ($label) => '<Etiqueta tipo="PDF">'.$label.'</Etiqueta>', $base64Labels));

    return '<?xml version="1.0" encoding="utf-8"?>'
        .'<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
        .'<soap:Body><EtiquetaEnvioV2Response xmlns="http://www.asmred.com/"><EtiquetaEnvioV2Result>'
        .'<Etiquetas xmlns="">'.$etiquetas.'</Etiquetas>'
        .'</EtiquetaEnvioV2Result></EtiquetaEnvioV2Response></soap:Body></soap:Envelope>';
}

function parseGlsEsLabelResponse(string $response): array
{
    $reflection = new ReflectionMethod(CallApiGlsEsShipping::class, 'parseLabelResponse');
    $reflection->setAccessible(true);

    return $reflection->invoke(new CallApiGlsEsShipping(), $response, ['data' => ['codbarras' => 'X']]);
}

function tinyPdfBase64(string $text): string
{
    $pdf = new \Mpdf\Mpdf();
    $pdf->WriteHTML('<h1>'.$text.'</h1>');

    return base64_encode($pdf->Output('', 'S'));
}

it('merges every returned label instead of keeping only the last one', function () {
    $result = parseGlsEsLabelResponse(glsEsLabelResponse([tinyPdfBase64('Parcel 1'), tinyPdfBase64('Parcel 2'), tinyPdfBase64('Parcel 3')]));

    expect($result['status'])->toBe('success')
        ->and($result['modelData']['number_parcels'])->toBe(3);

    $tempFile = tempnam(sys_get_temp_dir(), 'gls_labels_');
    file_put_contents($tempFile, base64_decode($result['modelData']['label']));

    try {
        $inspector = new \Mpdf\Mpdf();
        expect($inspector->setSourceFile($tempFile))->toBe(3);
    } finally {
        @unlink($tempFile);
    }
});

it('stores a single returned label untouched', function () {
    $label  = tinyPdfBase64('Only parcel');
    $result = parseGlsEsLabelResponse(glsEsLabelResponse([$label]));

    expect($result['status'])->toBe('success')
        ->and($result['modelData']['number_parcels'])->toBe(1)
        ->and($result['modelData']['label'])->toBe($label);
});

it('fails cleanly when the response holds no labels', function () {
    $result = parseGlsEsLabelResponse(glsEsLabelResponse([]));

    expect($result['status'])->toBe('fail')
        ->and($result['errorData']['message'])->toBe('No se encontraron etiquetas');
});

it('escapes customer data so the SOAP body stays valid XML', function () {
    $action     = new CallApiGlsEsShipping();
    $reflection = new ReflectionMethod(CallApiGlsEsShipping::class, 'xmlEscape');
    $reflection->setAccessible(true);

    $escaped = $reflection->invoke($action, [
        'to_name'    => 'Boulangerie & Fils <SARL>',
        'to_address' => "12 Rue de l'Église",
        'to_city'    => 'Besançon',
        'weight'     => 8.5,
    ]);

    $xml = simplexml_load_string('<Envio><Nombre>'.$escaped['to_name'].'</Nombre><Direccion>'.$escaped['to_address'].'</Direccion><Poblacion>'.$escaped['to_city'].'</Poblacion><Peso>'.$escaped['weight'].'</Peso></Envio>');

    expect($xml)->not->toBeFalse()
        ->and((string)$xml->Nombre)->toBe('Boulangerie & Fils <SARL>')
        ->and((string)$xml->Direccion)->toBe("12 Rue de l'Église")
        ->and((string)$xml->Poblacion)->toBe('Besançon')
        ->and((string)$xml->Peso)->toBe('8.5');
});

it('merges PDF strings preserving orientation and template size', function () {
    $pdf1 = new \Mpdf\Mpdf();
    $pdf1->WriteHTML('<h1>Page 1</h1>');
    $pdfString1 = $pdf1->Output('', 'S');

    $pdf2 = new \Mpdf\Mpdf();
    $pdf2->WriteHTML('<h1>Page 2</h1>');
    $pdfString2 = $pdf2->Output('', 'S');

    $action = new CallApiGlsEsShipping();
    $reflection = new ReflectionClass(CallApiGlsEsShipping::class);
    $method = $reflection->getMethod('mergePdfStrings');
    $method->setAccessible(true);

    $mergedBase64 = $method->invoke($action, [$pdfString1, $pdfString2]);
    $mergedPdf = base64_decode($mergedBase64);

    $tempFile = tempnam(sys_get_temp_dir(), 'test_merge_verify_');
    file_put_contents($tempFile, $mergedPdf);

    try {
        $inspector = new \Mpdf\Mpdf();
        $pageCount = $inspector->setSourceFile($tempFile);

        expect($pageCount)->toBe(2);

        $tplId1 = $inspector->importPage(1);
        $size1 = $inspector->getTemplateSize($tplId1);
        expect($size1['width'])->toBeGreaterThan(0);
        expect($size1['height'])->toBeGreaterThan(0);

        $tplId2 = $inspector->importPage(2);
        $size2 = $inspector->getTemplateSize($tplId2);
        expect($size2['width'])->toBeGreaterThan(0);
        expect($size2['height'])->toBeGreaterThan(0);
    } finally {
        @unlink($tempFile);
    }
});
