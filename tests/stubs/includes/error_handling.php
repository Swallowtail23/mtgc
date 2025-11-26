<?php

// Stub error handling to avoid mail/redirect during tests.

function mtg_error($number = null, $string = null, $file = null, $line = null, $context = null)
{
    // Swallow errors during tests
    return true;
}

function mtg_exception($err = null)
{
    // Swallow exceptions during tests
    return true;
}

set_error_handler('mtg_error');
set_exception_handler('mtg_exception');
