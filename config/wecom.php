<?php

return [

    'archive' => [
        // Tencent ships the chat archive only as a C library, read through FFI. It is not in the
        // repository: download libWeWorkFinanceSdk_C.so from the WeCom developer docs onto each
        // server that runs the queue workers and point this at it.
        'sdk_path' => env('WECOM_FINANCE_SDK_PATH', '/opt/wecom/libWeWorkFinanceSdk_C.so'),

        'timeout' => (int) env('WECOM_FINANCE_SDK_TIMEOUT', 30),

        'batch_size' => 1000,
    ],

];
