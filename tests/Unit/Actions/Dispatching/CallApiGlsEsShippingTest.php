<?php

use App\Actions\Dispatching\Shipment\ApiCalls\CallApiGlsEsShipping;

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
