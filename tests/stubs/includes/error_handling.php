<?php

// Stub error handling to avoid mail/redirect during tests.

function mtgError($number = null, $string = null, $file = null, $line = null, $context = null)
{
    // Swallow errors during tests
    return true;
}

function mtgException($err = null)
{
    // Swallow exceptions during tests
    return true;
}

set_error_handler('mtgError');
set_exception_handler('mtgException');
