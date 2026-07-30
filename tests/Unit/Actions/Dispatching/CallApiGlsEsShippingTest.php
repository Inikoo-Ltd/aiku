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
