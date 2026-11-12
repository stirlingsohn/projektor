<?php

return [
    /*
     * The largest file that can be uploaded, in megabytes. PHP's own
     * `upload_max_filesize` and `post_max_size` must allow at least this.
     */
    'max_upload_mb' => (int) env('MAX_UPLOAD_MB', 100),
];
