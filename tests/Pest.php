<?php

require_once __DIR__ . '/wp-stubs.php';

pest()->beforeEach(function () {
    wp_stubs_reset();
    $_SERVER = [];
})->in('Unit');
