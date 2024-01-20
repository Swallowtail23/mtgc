<?php
/* Version:     2.0
    Date:       18/03/23
    Name:       initial.php
    Purpose:    generate a usable password without site access
    Notes:      #### MUST NOT BE SERVED PUBLICLY BY Apache #### 
        
    1.0
                Initial version
 *  2.0         
 *              Migrate to password_hash
*/
include ('../classes/ini.class.php');
$ini = new INI("/opt/mtg/mtg_new.ini");
$ini_array = $ini->data;

//Set password parameters
if (!isset($argv[0]) OR !isset($argv[1]) OR !isset($argv[2]) OR isset($argv[3])):
    echo "Incorrect number of arguments (Should be 2: username and password), quitting";
    die;
endif;
$argument_loop = 1;
foreach($argv as $value):
    if ($argument_loop === 1):
        // do nothing, this is the filename
    elseif ($argument_loop === 2):
        $username = $value; 
    elseif ($argument_loop === 3):
        $password = $value;
    else:
        echo "Incorrect number of arguments (Should just be username and password), quitting";
        die;
    endif;
    $argument_loop = $argument_loop + 1;
endforeach;

$hashed_password = password_hash("$password", PASSWORD_DEFAULT);
echo "Username: $username\n";
echo "Hashed password: $hashed_password\n";