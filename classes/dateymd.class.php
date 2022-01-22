<?php
/* Version:     1.0
    Date:       23/10/16
    Name:       dateymd.class.php
    Purpose:    Simple date class for date format as required by admin pages.
    Notes:      - 
    To do:      -
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2016 Simon Wilson
    
 *  1.0
                Initial version
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class DateYMD {

    public $datetoday;

    public function getToday() {
        $datearray = getdate();
        $datearray['mon'] = str_pad($datearray['mon'], 2, "0", STR_PAD_LEFT);
        $this->datetoday = $datearray['year'] . '-' . $datearray['mon'] . '-' . $datearray['mday'];
        return $this->datetoday;
    }

    public function __toString() {
        return $this->getToday();
    }

}
