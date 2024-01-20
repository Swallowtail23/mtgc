<?php

function startCustomSession() {
    ini_set('session.name', 'value in here');
    session_start();
}