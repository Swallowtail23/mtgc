<?php

// Either just use the default below, or set your own in a copy of this file named sessionname.local.php

function startCustomSession() {
    ini_set('session.name', 'Change-to-Your-Own-Value_2024');
    session_start();
}
