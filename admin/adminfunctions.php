<?php
/* Version:     2.0
    Date:       11/01/20
    Name:       admin/adminfunctions.php
    Purpose:    Functions for admin pages
    Notes:      {none}
    To do:      Move Admin IPs to ini file, and/or set admin access method?
    
    1.0
                Initial version
 *  2.0
 *              Move from writelog to Message class
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

function newuser($username, $postemail, $password) 
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
            $mytable = $row['usernumber']."collection"; 
            $query2 = "CREATE TABLE `$mytable` LIKE collectionTemplate";
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Copying collection table: $query2",$logfile);
            if($db->query($query2) === TRUE):
                $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Collection table copy successful",$logfile);
                $tablesuccess = 1;
            else:
                $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Collection table copy failed",$logfile);
                $tablesuccess = 0;
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
    else:
        return 0;
    endif;
}

function setmtcemode($toggle)
{
    global $db, $logfile;
    if ($toggle == 'off'):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Setting maintenance mode off",$logfile);
        $data = array(
            'mtce' => 0
        );
    elseif ($toggle == 'on'):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Setting maintenance mode on",$logfile);
        $data = array(
            'mtce' => 1
        );
    endif;
    if($db->update('admin', $data) === TRUE):
        $affected_rows = $db->affected_rows;
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": query written to '.$affected_rows.' rows",$logfile);
    else:
        trigger_error("[ERROR] adminfunctions.php: Function setmtcemode: Error: " . $db->error, E_USER_ERROR);
    endif;
}