<?php
/* Version:     3.0
    Date:       26/03/23
    Name:       admin/adminfunctions.php
    Purpose:    Functions for admin pages
    Notes:      {none}
    To do:      Move Admin IPs to ini file, and/or set admin access method?
    
    1.0
                Initial version
 *  2.0
 *              Move from writelog to Message class
 *  3.0
 *              PHP 8.1 compatibility
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

function newuser($username, $postemail, $password, $dbname = '') 
{
    global $logfile, $db;
    $mysql_date = date( 'Y-m-d' );
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $query = "INSERT INTO users (username, reg_date, email, password, status, groupid, grpinout) ".
        "VALUES ($username, '$mysql_date', $postemail, '$hashed_password', 'chgpwd',1,0) "
        . "ON DUPLICATE KEY UPDATE password='$hashed_password', status='chgpwd' ";
    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": New user query/password update for $username / $postemail from {$_SERVER['REMOTE_ADDR']}",$logfile);
    if($db->query($query) === TRUE):
        $affected_rows = $db->affected_rows;
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": New user query from ".$_SERVER['REMOTE_ADDR']." affected $affected_rows rows",$logfile);
    else:
        $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": New user query unsuccessful",$logfile);
    endif;

    // Retrieve the new user to confirm that it has written OK

    if($row = $db->select_one ('password, username, usernumber', 'users', "WHERE email=$postemail")):
        $db_password = $row['password'];
        if (password_verify($password, $db_password)):
            // User has been created OK
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": User creation successful, password matched",$logfile);
            $usersuccess = 1;
            // Create the user's database table
            $mytable = "{$row['usernumber']}collection";
            // Does it already exist
            $queryexists = "SHOW TABLES FROM $dbname LIKE '$mytable'";
            $stmt = $db->prepare($queryexists);
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Checking if collection table exists: $queryexists",$logfile);
            $exec = $stmt->execute();
            if ($exec === false):
                $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Collection table check failed",$logfile);
            else:
                $stmt->store_result();
                $collection_exists = $stmt->num_rows; //$collection_exists now includes the quantity of tables with the collection name
                $stmt->close();
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Collection table check returned $collection_exists rows",$logfile);
                if($collection_exists === 0): //No existing collection table
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": No Collection table, creating...",$logfile);
                    $querycreate = "CREATE TABLE `$mytable` LIKE collectionTemplate";
                    $stmt = $db->prepare($querycreate);
                    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Copying collection table: $querycreate",$logfile);
                    $exec = $stmt->execute();
                    if ($exec === false):
                        $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Collection table copy failed",$logfile);
                        $tablesuccess = 5;
                    else:
                        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Collection table copy ok",$logfile);
                        $tablesuccess = 1;
                    endif;
                elseif($collection_exists == -1):
                    $tablesuccess = 5;
                else: // There is already a table with this name
                    $tablesuccess = 0;
                endif;
            endif;
        else:
            $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": User creation unsuccessful, password check failed, aborting",$logfile);
            $usersuccess = 0;
        endif;
    else:
        $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": User creation unsuccessful",$logfile);
        $usersuccess = 0;
    endif;
    
    if (($usersuccess === 1) AND ($tablesuccess === 1)):
        return 2;
    elseif (($usersuccess === 1) AND ($tablesuccess === 0)):
        return 1;
    elseif (($usersuccess === 1) AND ($tablesuccess === 5)):
        return 5;
    else:
        return 0;
    endif;
}

function setmtcemode($toggle)
{
    global $db, $logfile;
    if ($toggle == 'off'):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Setting maintenance mode off",$logfile);
        $mtcequery = 0;
    elseif ($toggle == 'on'):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Setting maintenance mode on",$logfile);
        $mtcequery = 1;
    endif;
        $query = 'UPDATE admin SET mtce=?';
        $stmt = $db->prepare($query);
        $stmt->bind_param('i', $mtcequery);
        if ($stmt === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $db->error, E_USER_ERROR);
        endif;
        $exec = $stmt->execute();
        if ($exec === false):
            $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Setting mtce mode to $mtcequery failed",$logfile);
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Set mtce mode to $mtcequery",$logfile);
        endif;
}