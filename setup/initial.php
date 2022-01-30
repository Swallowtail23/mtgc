<?php
/* Version:     1.0
    Date:       30/01/22
    Name:       initial.php
    Purpose:    generate a usable password without site access
    Notes:      #### MUST NOT BE SERVER PUBLICLY BY Apache #### 
        
    1.0
                Initial version
*/
include ('../classes/ini.class.php');
$ini = new INI("/opt/mtg/mtg_new.ini");
$ini_array = $ini->data;

//Set password parameters
$Blowfish_Pre = $ini_array['security']['Blowfish_Pre'];
$Blowfish_End = $ini_array['security']['Blowfish_End'];

echo "Running password hash...\n";
$Allowed_Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789./';
$Chars_Len = 63;
$Salt_Length = 21;
$mysql_date = date( 'Y-m-d' );
$salt = "";
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

for($i=0; $i<$Salt_Length; $i++):
    $salt .= $Allowed_Chars[mt_rand(0,$Chars_Len)];
endfor;
$bcrypt_salt = $Blowfish_Pre.$salt.$Blowfish_End;
$hashed_password = crypt($password, $bcrypt_salt);
// $query =   "INSERT INTO users (username, reg_date, email, salt, password, status, groupid, grpinout) VALUES ($username, '$mysql_date', $postemail, '$salt', '$hashed_password', 'chgpwd',1,0) ";
echo "BP: $Blowfish_Pre\n";
echo "S: $salt\n";
echo "BE: $Blowfish_End\n";
echo "BS: $bcrypt_salt\n";
echo "\n";
echo "Username: $username\n";
echo "Hashed password: $hashed_password\n";
echo "Salt: $salt\n";
   