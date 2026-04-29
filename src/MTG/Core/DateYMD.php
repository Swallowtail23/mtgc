<?php

/*
Version:     1.3
Date:        29/04/26
Name:        DateYMD.php
Purpose:     Simple date class for date format as required by admin pages.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Core;

class DateYMD
{
    public string $datetoday = '';

    public function getToday(): string
    {
        $datearray = getdate();
        $datearray['mon'] = str_pad($datearray['mon'], 2, "0", STR_PAD_LEFT);
        $datearray['mday'] = str_pad($datearray['mday'], 2, "0", STR_PAD_LEFT);
        $this->datetoday = $datearray['year'] . '-' . $datearray['mon'] . '-' . $datearray['mday'];
        return $this->datetoday;
    }

    public function __toString(): string
    {
        return $this->getToday();
    }
}
