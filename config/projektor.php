<?php

return [
    /*
     * The name shown when the owner has not set a company name.
     */
    'name' => env('APP_NAME', 'Projektor'),

    /*
     * The largest file that can be uploaded, in megabytes. PHP's own
     * `upload_max_filesize` and `post_max_size` must allow at least this.
     */
    'max_upload_mb' => (int) env('MAX_UPLOAD_MB', 100),
];
