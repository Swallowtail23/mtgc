<?php
/* Version:     4.0
    Date:       09/12/2023
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
 * 
 *  4.0         09/12/2023
 *              Move all to parameterised queries
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

function newuser($username, $postemail, $password, $dbname = '') 
{
    global $logfile, $db;
    $mysql_date = date( 'Y-m-d' );
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $query = "INSERT INTO users (username, reg_date, email, password, status, groupid, grpinout) 
                VALUES (?, ?, ?, ?, 'chgpwd', 1, 0) 
                ON DUPLICATE KEY UPDATE password=?, status='chgpwd' ";
    $obj = new Message;$obj->MessageTxt('[NOTICE]', $_SERVER['PHP_SELF'], "Function ".__FUNCTION__.": New user query/password update for $username / $postemail from {$_SERVER['REMOTE_ADDR']}", $logfile);
    $stmt = $db->prepare($query);
    if ($stmt):
        $stmt->bind_param("sssss", $username, $mysql_date, $postemail, $hashed_password, $hashed_password);
        if ($stmt->execute()):
            $affected_rows = $stmt->affected_rows;
            $obj = new Message;$obj->MessageTxt('[NOTICE]', $_SERVER['PHP_SELF'], "Function ".__FUNCTION__.": New user query from ".$_SERVER['REMOTE_ADDR']." affected $affected_rows rows", $logfile);
        else:
            trigger_error("[ERROR] admin.php: Adding update notice: failed " . $stmt->error, E_USER_ERROR);
        endif;
        $stmt->close();
    else:
        trigger_error("[ERROR] admin.php: Adding update notice: failed to prepare statement " . $db->error, E_USER_ERROR);
    endif;

    // Retrieve the new user to confirm that it has written OK
    $query_select = "SELECT password, username, usernumber FROM users WHERE email=?";
    $stmt_select = $db->prepare($query_select);
    $stmt_select->bind_param("s", $postemail);

    if ($stmt_select->execute()):
        $stmt_select->store_result();
        $stmt_select->bind_result($db_password, $db_username, $db_usernumber);

        if ($stmt_select->fetch()):
            if (password_verify($password, $db_password)):
                // User has been created OK
                $obj = new Message;$obj->MessageTxt('[NOTICE]', $_SERVER['PHP_SELF'], "Function ".__FUNCTION__.": User creation successful, password matched", $logfile);
                $usersuccess = 1;

                // Create the user's database table
                $mytable = "{$db_usernumber}collection";

                // Does it already exist
                $queryexists = "SHOW TABLES FROM $dbname LIKE '$mytable'";
                $stmt_exists = $db->prepare($queryexists);

                if ($stmt_exists->execute()):
                    $stmt_exists->store_result();
                    $collection_exists = $stmt_exists->num_rows; // $collection_exists now includes the quantity of tables with the collection name
                    $stmt_exists->close();

                    $obj = new Message;$obj->MessageTxt('[DEBUG]', $_SERVER['PHP_SELF'], "Function ".__FUNCTION__.": Collection table check returned $collection_exists rows", $logfile);

                    if ($collection_exists === 0): // No existing collection table
                        $obj = new Message;$obj->MessageTxt('[DEBUG]', $_SERVER['PHP_SELF'], "Function ".__FUNCTION__.": No Collection table, creating...", $logfile);
                        $query_create = "CREATE TABLE `$mytable` LIKE collectionTemplate";
                        $stmt_create = $db->prepare($query_create);

                        if ($stmt_create->execute()):
                            $obj = new Message;$obj->MessageTxt('[NOTICE]', $_SERVER['PHP_SELF'], "Function ".__FUNCTION__.": Collection table copy ok", $logfile);
                            $tablesuccess = 1;
                        else:
                            $obj = new Message;$obj->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function ".__FUNCTION__.": Collection table copy failed", $logfile);
                            $tablesuccess = 5;
                        endif;
                        $stmt_create->close();
                    elseif ($collection_exists == -1):
                        $tablesuccess = 5;
                    else: // There is already a table with this name
                        $tablesuccess = 0;
                    endif;
                else:
                    $obj = new Message;$obj->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function ".__FUNCTION__.": Collection table check failed", $logfile);
                endif;
            else:
                $obj = new Message;$obj->MessageTxt('[ERROR]', $_SERVER['PHP_SELF'], "Function ".__FUNCTION__.": User creation unsuccessful, password check failed, aborting", $logfile);
                $usersuccess = 0;
            endif;
        else:
            $obj = new Message;$obj->MessageTxt('[ERROR]', $_SERVER['PHP_SELF'], "Function ".__FUNCTION__.": User creation unsuccessful", $logfile);
            $usersuccess = 0;
        endif;
        $stmt_select->close();
    else:
        $obj = new Message;$obj->MessageTxt('[ERROR]', $_SERVER['PHP_SELF'], "Function ".__FUNCTION__.": User creation unsuccessful", $logfile);
        $usersuccess = 0;
    endif;

    if (($usersuccess === 1) && ($tablesuccess === 1)):
        return 2;
    elseif (($usersuccess === 1) && ($tablesuccess === 0)):
        return 1;
    elseif (($usersuccess === 1) && ($tablesuccess === 5)):
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