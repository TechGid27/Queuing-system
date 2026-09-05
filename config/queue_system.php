<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queue Capacity Limit
    |--------------------------------------------------------------------------
    | Maximum number of students allowed in the queue (waiting + serving)
    | at any given time. Set to 0 to disable the limit.
    |
    */
    'max_capacity' => (int) env('QUEUE_MAX_CAPACITY', 50),

];
