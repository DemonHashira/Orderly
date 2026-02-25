<?php

return [
    'import' => [
        'max_rows' => (int) env('PRODUCT_IMPORT_MAX_ROWS', 5000),
        'max_file_kb' => (int) env('PRODUCT_IMPORT_MAX_FILE_KB', 10240),
    ],
];
