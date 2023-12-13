<?php 
/* Version:     4.0
    Date:       25/03/2023
    Name:       admin/users.php
    Purpose:    User administrative tasks
    Notes:      
        
    1.0
                Initial version
    2.0         
                Mysqli_Manager
 *  3.0
 *              Migrate from writelog to message class
 *  4.0
 *              PHP 8.1 compatibility
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('../includes/ini.php');                //Initialise and load ini file
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');      //Includes basic functions for non-secure pages
require ('../includes/secpagesetup.php');       //Setup page variables
forcechgpwd();                                  //Check if user is disabled or needs to change password
$msg = new Message;

//Check if user is logged in, if not redirect to login.php
$msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Admin page called by user $username ($useremail)",$logfile);
// Is admin running the page
$msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Admin is $admin",$logfile);
if ($admin !== 1):
    require('reject.php');
endif;


if (isset($_POST['newuser'])):
    $newuser = ($_POST['newuser'] == 'yes') ? 'yes' : '';
    if (isset($_POST['password'])):
        $password = $_POST['password'];
    endif;    
    if (isset($_POST['email'])):
        $postemail_raw = $_POST['email'];
        $postemail = check_input($_POST['email']);
    endif; 
    if (isset($_POST['username'])):
        $username_raw = $_POST['username'];
        $username = check_input($_POST['username']);
    endif; 
endif;    
if (isset($_POST['updateusers'])):
    $updateusers = ($_POST['updateusers'] == 'yes') ? 'yes' : '';
    $updatearray[] = filter_input_array(INPUT_POST);
endif; 
?>

<!DOCTYPE html>
<head>
    <title>MtG collection administration - users</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" type="text/css" href="/css/style<?php echo $cssver?>.css">
<?php include('../includes/googlefonts.php');?>
<script src="../js/jquery.js"></script>
<script type="text/javascript">   
    jQuery( function($) {
        $('#newuserform').submit(function() {
            if(($('#username').val() === '') || ($('#email').val() === '') || ($('#pword').val() === '')){
                alert("You need to complete all fields");
                return false;
            }
        });
    });
</script>
</head>
<body id="body" class="body">

<?php 
include '../includes/overlays.php'; 
include '../includes/header.php';
require('../includes/menu.php');
?>
<div id='page'>
    <div class='staticpagecontent'>
        <?php 
        // Generate new account or do password reset
        if ((isset($newuser)) AND ($newuser === "yes")):
            $obj = new PasswordCheck($db, $logfile);
            $newuserstatus = $obj->newUser($username_raw, $postemail_raw, $password, $dbname); // Use "_raw" variables as newuser() uses parameterised query, so no need to quote
            if ($newuserstatus === 2):
                echo "<div class='alert-box success'><span>success: </span>User $username / $postemail created, password successfully recorded and checked.</div>";    
                echo "<div class='alert-box success'><span>success: </span>Writing table successful.</div>";    
            elseif ($newuserstatus === 1):
                echo "<div class='alert-box success'><span>success: </span>User $username / $postemail password successfully recorded and checked.</div>";    
                echo "<div class='alert-box notice'><span>notice: </span>No new collection table created, already exists for this user.</div>"; 
            else:
                echo "<div class='alert-box error'><span>error: </span>Something went wrong. Check logs.</div>";    
            endif;
        endif;

        // Multiple user form update
        if ((isset($updateusers)) AND ($updateusers === "yes")):
            foreach ($updatearray[0]['id'] as $i => $id) :
                $sql_id = $db->real_escape_string($updatearray[0]['id'][$i]);
                ${'sqlid'.$id} = $sql_id;
                $sql_eml = $db->real_escape_string($updatearray[0]['eml'][$i]);
                ${'sqleml'.$id} = $sql_eml;
                $sql_name = $db->real_escape_string($updatearray[0]['name'][$i]);
                ${'sqlname'.$id} = $sql_name;
                $sql_status = $db->real_escape_string($updatearray[0]['status'][$i]);
                ${'sqlstatus'.$id} = $sql_status;
                $sql_adm = $db->real_escape_string($updatearray[0]['adm'][$i]);
                ${'sqladm'.$id} = $sql_adm;
                //Simple update of fields
                $query = "UPDATE users SET username = ?, email = ?, status = ?, admin = ? WHERE usernumber = ?";
                $params = [$sql_name, $sql_eml, $sql_status, $sql_adm, $sql_id];
                if ($result = $db->execute_query($query, $params)):
                    $affected_rows = $db->affected_rows;
                    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Update user query by $useremail from {$_SERVER['REMOTE_ADDR']} affected $affected_rows rows", $logfile);
                else:
                    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Update user query unsuccessful", $logfile);
                endif;
                $usertable = $sql_id."collection";
                // More complex updates
                // - delete card collection for a user
                if (($updatearray[0]['actions'][$i]) == 'deletecards'):
                    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Clearing collection for $sql_name from {$_SERVER['REMOTE_ADDR']}",$logfile);
                    if ($db->execute_query("DELETE FROM $usertable")):
                        if($deletecards = $db->execute_query("SELECT * FROM $usertable")):
                            if ($deletecards->num_rows == 0):
                                echo "<div class='alert-box success'><span>success: </span>Cards cleared for $sql_name</div>";    
                                $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Table empty successful",$logfile);
                            else:    
                                echo "<div class='alert-box error'><span>error: </span>Cards not cleared for $sql_name</div>";    
                                $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Table empty failed",$logfile);
                            endif;
                        endif;
                    endif;
                // - delete user and collection
                elseif (($updatearray[0]['actions'][$i]) == 'deleteuser'):
                    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Nuking $sql_name from {$_SERVER['REMOTE_ADDR']}",$logfile);
                    if ($db->execute_query("DELETE FROM users WHERE usernumber = ?",[$sql_id])):
                        if($nukeuser = $db->execute_query("SELECT username FROM users WHERE usernumber = ?",[$sql_id])):
                            if ($nukeuser->num_rows == 0):
                                echo "<div class='alert-box success'><span>success: </span>User $sql_name removed</div>";    
                                $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": User deletion successful",$logfile);
                            else:    
                                echo "<div class='alert-box error'><span>error: </span>User $sql_name not removed</div>";    
                                $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": User deletion failed",$logfile);
                            endif;
                        endif;
                    endif;
                    $sqldrop = "DROP TABLE $usertable";
                    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Running $sqldrop",$logfile);
                    $db->query($sqldrop);
                    $queryexists = "SHOW TABLES LIKE '$usertable'";
                    $stmt = $db->prepare($queryexists);
                    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Checking if collection table still exists: $queryexists",$logfile);
                    $exec = $stmt->execute();
                    if ($exec === false):
                        $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Collection table check failed",$logfile);
                    else:
                        $stmt->store_result();
                        $collection_exists = $stmt->num_rows; //$collection_exists now includes the quantity of tables with the collection name
                        $stmt->close();
                        $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Collection table check returned $collection_exists rows",$logfile);
                        if($collection_exists === 0): //No existing collection table
                            echo "<div class='alert-box success'><span>success: </span>Table dropped for $sql_name</div>";
                            $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Collection table check shows 0",$logfile);
                        elseif($collection_exists == -1):
                            $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Shouldn't be here...",$logfile);
                        else: // There is still a table with this name
                            echo "<div class='alert-box error'><span>error: </span>Table not dropped for $sql_name</div>";
                            $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Table still exists",$logfile);
                        endif;
                    endif;
                elseif (($updatearray[0]['actions'][$i]) == 'resetpassword'):
                    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Reset password call for $sql_id/$sql_name/$sql_eml from {$_SERVER['REMOTE_ADDR']}",$logfile);
                    $obj = new PasswordCheck ($db, $logfile);
                    $reset = $obj->passwordReset ($sql_eml, $admin, $dbname);
                    if ($reset === 2):
                        echo "<div class='alert-box success'><span>success: </span>User $username / $sql_eml created, password successfully recorded and checked.</div>";    
                        echo "<div class='alert-box success'><span>success: </span>Writing table successful.</div>";    
                    elseif ($reset === 1):
                        echo "<div class='alert-box success'><span>success: </span>User $username / $sql_eml password successfully recorded and checked.</div>";    
                        echo "<div class='alert-box notice'><span>notice: </span>No new collection table created, already exists for this user.</div>"; 
                    else:
                        echo "<div class='alert-box error'><span>error: </span>Something went wrong. Check logs.</div>";    
                    endif;
                endif;
            endforeach;
        else:
            $updateusers = '';
        endif;?>
        <form id='newuserform' name="newuser" action="users.php" method="post" autocomplete="user-form">
            <h3> Add a new user </h3>
            To change an existing user's password, use this form.<br>
            <input type='hidden' name="newuser" value="yes">
            <input title="Please enter username" placeholder="Username" id="username" autocomplete="off" name="username" type="text" size="12" maxlength="12" /><br>
            <input title="Email address" placeholder="Email" id="email" autocomplete="user-email-for-form" name="email" type="email" size="64" maxlength="64" /><br>
            <input type="password" id='pword' title="Please Enter Your Password" placeholder="Password" size="20" autocomplete="user-password-for-form" name="password" maxlength="20" />
            <input type="submit" value="Create user" />
        </form>

        <div>
            <h3>User table</h3> <?php 
            $allusertable = $db->execute_query("SELECT username, usernumber, email, reg_date, lastlogin_date, status, admin FROM users");?>
            <form name="updateusers" action="users.php" method="post">
                <table>
                    <tr>
                        <td>User #</td>
                        <td>Registered</td>
                        <td>Last login</td>
                        <td>Username</td>
                        <td>Email</td>
                        <td>Status</td>
                        <td>Admin</td>
                        <?php if($updateusers === 'yes'): ?>
                        <td></td>
                        <?php endif; ?>
                        <td>Actions</td>

                    </tr>
                    <?php 
                    while ($alluserresults = $allusertable->fetch_assoc()): 
                        $usertable = $alluserresults['usernumber']."collection";
                        ?>
                        <tr>
                            <td> 
                                <?php echo $alluserresults['usernumber']; ?> 
                                <input type='hidden' name=id[] value='<?php echo $alluserresults['usernumber']; ?>'>
                            </td>
                            <td> 
                                <?php echo $alluserresults['reg_date']; ?> 
                            </td>
                            <td> 
                                <?php echo $alluserresults['lastlogin_date']; ?> 
                            </td>
                            <td> 
                                <input type='text' size='10' name=name[] value='<?php echo $alluserresults['username']; ?>'>
                            </td>
                            <td> 
                                <input type='email' size='30' name=eml[] value='<?php echo $alluserresults['email']; ?>'>
                            </td>
                            <td> 
                                <select name='status[]'>
                                    <option value='active' <?php if($alluserresults['status'] === 'active'): echo "selected"; endif; ?> >active</option>
                                    <option value='disabled'  <?php if($alluserresults['status'] === 'disabled'): echo "selected"; endif; ?> >disabled</option>
                                    <option value='locked' <?php if($alluserresults['status'] === 'locked'): echo "selected"; endif; ?> >locked</option>
                                    <option value='chgpwd' <?php if($alluserresults['status'] === 'chgpwd'): echo "selected"; endif; ?> >password change required</option>
                                    <option value='mtce' <?php if($alluserresults['status'] === 'mtce'): echo "selected"; endif; ?> >site maintenance</option>
                                </select> 
                            </td>
                            <td> 
                                <select name='adm[]'>
                                    <option value=1 <?php if($alluserresults['admin'] == 1): echo "selected"; endif; ?> >Yes</option>
                                    <option value=0  <?php if($alluserresults['admin'] == 0): echo "selected"; endif; ?> >No</option>
                                </select> 
                            </td>

                            <?php if($updateusers === 'yes'): ?>
                            <td>
                                <?php 
                                $aur_usernumber = $alluserresults['usernumber'];
                                $updatesql = $db->execute_query("SELECT username, email, status, admin FROM users WHERE usernumber = ? LIMIT 1",[$aur_usernumber]);
                                $updateoutcome = $updatesql->fetch_assoc();
                                if(((string)$updateoutcome['username'] === (string)${'sqlname'.$alluserresults['usernumber']}) 
                                    AND ((string)$updateoutcome['email'] === (string)${'sqleml'.$alluserresults['usernumber']})
                                    AND 
                                        (((string)$updateoutcome['status'] === (string)${'sqlstatus'.$alluserresults['usernumber']})
                                        OR
                                        (isset($reset) AND ($reset === 1 || $reset === 2)))
                                    AND ((string)$updateoutcome['admin'] === (string)${'sqladm'.$alluserresults['usernumber']})): ?>
                                    <img src='/images/success.png' alt='Success'>
                                <?php else: ?>
                                    <img src='/images/error.png' alt='Failure'>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <select name='actions[]'>
                                    <option value='' selected></option>
                                    <option value=deletecards>Delete collection</option>
                                    <option value=deleteuser>Delete user & cards</option>
                                    <option value=resetpassword>Reset password</option>
                                </select> 
                            </td>
                        </tr>
                    <?php 
                    endwhile; ?>
                </table>
                <input type='hidden' name="updateusers" value="yes">
                <br>
                <input type="submit" value="Update users" />
            </form>
            <form id='exportcsv' action="/csv.php"  method="GET">
            </form>
            <h4>Export</h4>
            <form action="/csv.php"  method="GET">
                <select name='table'>
                <?php 
                $exportlist = $db->execute_query("SELECT usernumber,username FROM users");
                while ($listuser = $exportlist->fetch_assoc()):
                    $userno = $listuser['usernumber'];
                    $userid = $listuser['username'];
                    echo "<option value='{$userno}collection'>$userid</option>";
                endwhile;
                ?>
                </select>
                <input type="submit" value="Export to CSV">
            </form>
        </div>
    </div>
</div>
    
<?php require('../includes/footer.php'); ?>
</body>
</html>