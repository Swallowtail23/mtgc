<?php

/*
Version:     1.1
Date:        25/11/25
Name:        dateymd.class.php
Purpose:     Simple date class for date format as required by admin pages.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0 23/10/16 Initial version
    1.1 25/11/25 Standard tidy-up
*/

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace, PSR1.Files.SideEffects.FoundWithSymbols
if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

class DateYMD
{
    public $datetoday;

    public function getToday()
    {
        $datearray = getdate();
        $datearray['mon'] = str_pad($datearray['mon'], 2, "0", STR_PAD_LEFT);
        $this->datetoday = $datearray['year'] . '-' . $datearray['mon'] . '-' . $datearray['mday'];
        return $this->datetoday;
    }

    public function __toString()
    {
        return $this->getToday();
    }
}
// phpcs:enable
