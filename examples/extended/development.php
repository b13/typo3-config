<?php

\B13\Config::get()
    ->allowNoCacheQueryParameter()
    ->initializeRedisCaching();
