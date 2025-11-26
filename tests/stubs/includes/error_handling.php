<?php

// Stub error handling to avoid mail/redirect during tests.

function mtg_error()
{
    return true;
}

function mtg_exception()
{
    return true;
}

set_error_handler('mtg_error');
set_exception_handler('mtg_exception');
