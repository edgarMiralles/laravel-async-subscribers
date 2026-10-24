<?php

declare(strict_types=1);

return [

    /*
     * Namespace prefix => directory. Every *Subscriber.php file under each directory that implements
     * AsyncSubscriber is registered.
     */
    'discover' => [
        'App\\' => app_path(),
    ],

    /*
     * Used when the subscriber has no #[OnQueue] attribute. Null means the connection's own default queue.
     */
    'queue' => env('ASYNC_SUBSCRIBERS_QUEUE'),

    'connection' => env('ASYNC_SUBSCRIBERS_CONNECTION'),

    'tries' => 3,

    'backoff' => 5,

    /*
     * Hold each job until the surrounding database transaction commits, so a subscriber never reads
     * rows that were rolled back.
     */
    'after_commit' => true,

    /*
     * Log each job when it is enqueued, when a worker starts it and when it finishes, tagged with job_uuid:
     * the id Laravel keeps across retries and stores in failed_jobs.
     */
    'log' => env('ASYNC_SUBSCRIBERS_LOG', true),

];
