<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Integrations/IBSng/IBSngClient.php';


$cases = [
    [
        'name' => 'expired account resets baseline and zero gift remains zero',
        'input' => [true, 999, 30, 0],
        'expected' => ['relative_expiration' => 30, 'reset_first_login' => true],
    ],
    [
        'name' => 'active account preserves remaining days and adds plan plus gift',
        'input' => [false, 5, 30, 2],
        'expected' => ['relative_expiration' => 37, 'reset_first_login' => false],
    ],
    [
        'name' => 'active account with zero gift receives plan validity only',
        'input' => [false, 0, 30, 0],
        'expected' => ['relative_expiration' => 30, 'reset_first_login' => false],
    ],
];

foreach ($cases as $case) {
    $actual = \RouteBox\Integrations\IBSng\IBSngRenewal::calculateRelativeExpiration(...$case['input']);
    if ($actual !== $case['expected']) {
        fwrite(STDERR, $case['name'] . " failed: " . json_encode($actual) . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $case['name'] . PHP_EOL;
}


$now = strtotime('2027-01-01 00:00:00 UTC');
$notStarted = \RouteBox\Integrations\IBSng\IBSngClient::normalizeRenewalState(
    '101',
    null,
    '30 Days',
    null,
    $now
);
if ($notStarted['expired'] !== false || $notStarted['remaining_days'] !== 30 || $notStarted['first_login'] !== null) {
    fwrite(STDERR, "Not-yet-started IBSng account state was not preserved." . PHP_EOL);
    exit(1);
}
echo "PASS: not-yet-started account keeps relative duration without resetting First Login" . PHP_EOL;

$active = \RouteBox\Integrations\IBSng\IBSngClient::normalizeRenewalState(
    '102',
    '2026-12-30 00:00:00',
    '30 Days',
    '2027-01-03 00:00:00 UTC',
    $now
);
if ($active['expired'] !== false || $active['remaining_days'] !== 2) {
    fwrite(STDERR, "Active IBSng account remaining days were calculated incorrectly." . PHP_EOL);
    exit(1);
}
echo "PASS: active account remaining days are derived from nearest expiry" . PHP_EOL;

$expired = \RouteBox\Integrations\IBSng\IBSngClient::normalizeRenewalState(
    '103',
    '2026-12-01 00:00:00',
    '30 Days',
    '2026-12-31 23:59:59 UTC',
    $now
);
if ($expired['expired'] !== true || $expired['remaining_days'] !== 0) {
    fwrite(STDERR, "Expired IBSng account was not detected." . PHP_EOL);
    exit(1);
}
echo "PASS: expired account state is detected" . PHP_EOL;


$fixture = <<<'HTML'
<table>
  <tr>
    <td class="Form_Content_Row_Left"><input type="checkbox" name="attr_edit_checkbox_1" value="rel_exp"></td>
    <td class="Form_Content_Row_Right"><input type="text" value="30 Days"></td>
  </tr>
  <tr>
    <td class="Form_Content_Row_Left"><input type="checkbox" name="attr_edit_checkbox_2" value="first_login"></td>
    <td class="Form_Content_Row_Right"><input type="text" value="2026-12-30 00:00"></td>
  </tr>
  <tr>
    <td>Nearest Expiration Date:</td>
    <td class="Form_Content_Row_Right">2027-01-03 00:00</td>
  </tr>
</table>
HTML;

$client = new \RouteBox\Integrations\IBSng\IBSngClient('127.0.0.1', 'admin', 'unused');
$attributeParser = new ReflectionMethod(\RouteBox\Integrations\IBSng\IBSngClient::class, 'attributeValue');
$attributeParser->setAccessible(true);
$relativeValue = $attributeParser->invoke($client, $fixture, 'rel_exp');
$firstLoginValue = $attributeParser->invoke($client, $fixture, 'first_login');
$labelParser = new ReflectionMethod(\RouteBox\Integrations\IBSng\IBSngClient::class, 'displayedLabelValue');
$labelParser->setAccessible(true);
$nearestValue = $labelParser->invoke($client, $fixture, 'Nearest Expiration Date');

if ($relativeValue !== '30 Days' || $firstLoginValue !== '2026-12-30 00:00' || $nearestValue !== '2027-01-03 00:00') {
    fwrite(STDERR, "IBSng A1.24 attribute and nearest-expiry parsing fixture failed." . PHP_EOL);
    exit(1);
}
echo "PASS: IBSng A1.24 relative, First Login and nearest-expiry fields are parsed" . PHP_EOL;
unset($client);

try {
    \RouteBox\Integrations\IBSng\IBSngRenewal::calculateRelativeExpiration(false, -1, 30, 0);
    fwrite(STDERR, "Invalid negative remaining days were accepted." . PHP_EOL);
    exit(1);
} catch (RuntimeException $e) {
    echo "PASS: invalid renewal duration values are rejected" . PHP_EOL;
}
