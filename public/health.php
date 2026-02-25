<?php
http_response_code(200);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'status' => 'ok',
    'service' => 'epco-app',
    'time' => date('c')
], JSON_UNESCAPED_SLASHES);
