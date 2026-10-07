<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use AlamiaSoft\AlamiaAccounts\Services\CustomVoucherTypeService;

$service = app(CustomVoucherTypeService::class);

echo "--- ALL FOR KAMAL_EXPRESS ---\n";
$kamal = $service->getAllVoucherTypes('KAMAL_EXPRESS');
echo json_encode($kamal, JSON_PRETTY_PRINT) . "\n";

echo "--- ALL FOR OTHER_COMPANY ---\n";
$other = $service->getAllVoucherTypes('OTHER_COMPANY');
echo json_encode($other, JSON_PRETTY_PRINT) . "\n";
