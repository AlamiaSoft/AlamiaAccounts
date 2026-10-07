<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;

$user = User::where('email', 'admin@admin.com')->first();
$token = $user->createToken('test_auth_token')->plainTextToken;

echo "Generated Sanctum Token: $token\n";

$controller = app(\App\Http\Controllers\Api\V1\SalesIntegrationController::class);

$req = Request::create('/api/v1/sales', 'GET', ['status' => 'staged']);
$req->headers->set('Authorization', 'Bearer ' . $token);
$req->headers->set('X-Company-Code', 'KAMAL_EXPRESS');

$res = $controller->index($req);

echo "Response Status: " . $res->getStatusCode() . "\n";
echo "Response Body:\n" . json_encode(json_decode($res->getContent()), JSON_PRETTY_PRINT) . "\n";

if ($res->getStatusCode() === 200) {
    echo "\n[PASS] Sanctum Bearer Token successfully authenticated without 401 logout!\n";
    exit(0);
} else {
    echo "\n[FAIL] Unexpected status: " . $res->getStatusCode() . "\n";
    exit(1);
}
