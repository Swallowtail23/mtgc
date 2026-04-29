<?php

// Stub error handling to avoid mail/redirect during tests.

function mtgError(
    mixed $number = null,
    mixed $string = null,
    mixed $file = null,
    mixed $line = null,
    mixed $appConfig = null
): bool {
    // Swallow errors during tests
    return true;
}

function mtgException(mixed $err = null, mixed $appConfig = null): bool
{
    // Swallow exceptions during tests
    return true;
}

set_error_handler('mtgError');
set_exception_handler('mtgException');
