<?php

\B13\Config::get()
    ->initializeRedisCaching(
        null,
        'ddev-' . getenv('DDEV_PROJECT') . '-redis'
    );
