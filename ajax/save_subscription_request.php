<?php

require __DIR__ . '/../bootstrap/app.php';
verify_csrf();

$result = SubscriptionService::submitRequest($_POST, $_FILES['proof_file'] ?? []);

json_response($result, $result['success'] ? 200 : 422);
