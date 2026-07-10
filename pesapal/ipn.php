<?php

http_response_code(200);

$data = file_get_contents("php://input");

file_put_contents(
    __DIR__.'/ipn.log',
    date('Y-m-d H:i:s') . PHP_EOL .
    $data . PHP_EOL .
    "--------------------" . PHP_EOL,
    FILE_APPEND
);


echo json_encode([
    "success" => true
]);
