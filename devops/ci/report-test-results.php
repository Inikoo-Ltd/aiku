<?php

/*
 * Sends the totals of a PHPUnit/Pest JUnit report to the aiku DevOps dashboard.
 * Usage (CI): php report-test-results.php junit.xml
 * Needs GITHUB_RUN_ID and DEVOPS_TOKEN; TEST_RESULTS_URL overrides the endpoint.
 * Never fails the build: any problem is printed and it exits 0.
 */

$report = $argv[1] ?? 'junit.xml';
$runId  = getenv('GITHUB_RUN_ID');
$token  = getenv('DEVOPS_TOKEN');

if (!$runId || !$token) {
    fwrite(STDERR, "report-test-results: no GITHUB_RUN_ID or DEVOPS_TOKEN, skipping\n");
    exit(0);
}

$xml = is_readable($report) ? @simplexml_load_file($report) : false;

if ($xml === false) {
    $results = ['missing' => true];
} else {
    $suites  = $xml->getName() === 'testsuites' ? $xml->testsuite : [$xml];
    $results = ['tests' => 0, 'assertions' => 0, 'failures' => 0, 'errors' => 0, 'skipped' => 0, 'seconds' => 0.0];
    foreach ($suites as $suite) {
        foreach (['tests', 'assertions', 'failures', 'errors', 'skipped'] as $counter) {
            $results[$counter] += (int) $suite[$counter];
        }
        $results['seconds'] += (float) $suite['time'];
    }

    $results['failed'] = [];
    foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $testcase) {
        if (count($results['failed']) >= 25) {
            break;
        }
        $message = (string) ($testcase->failure ?? $testcase->error);
        $lines   = array_values(array_filter(array_map('trim', explode("\n", $message)), fn ($line) => $line !== '' && $line !== trim((string) $testcase['name'])));
        preg_match('~(tests/\S+\.php:\d+)~', $message, $location);
        $results['failed'][] = [
            'test'    => trim(((string) $testcase['class'] ?: (string) $testcase['file']).' › '.$testcase['name']),
            'message' => mb_substr(implode("\n", array_unique([...array_slice(array_filter($lines, fn ($line) => !str_starts_with($line, '/')), 0, 6), ...($location[1] ?? null ? [$location[1]] : [])])), 0, 500),
        ];
    }
}

$response = @file_get_contents(getenv('TEST_RESULTS_URL') ?: 'https://aiku.io/devops/test-results', false, stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\nAccept: application/json\r\nX-DEVOPS-TOKEN: $token\r\n",
        'content'       => json_encode(['run_id' => (int) $runId, ...$results]),
        'timeout'       => 10,
        'ignore_errors' => true,
    ],
]));

echo 'report-test-results: '.($http_response_header[0] ?? 'no response').' '.json_encode(array_diff_key($results, ['failed' => true]))."\n";
exit(0);
