<?php 
/* Version:     5.0
    Date:       17/12/2023
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
 * 
 *  5.0         17/12/2023
 *              Add local currency control
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('../includes/ini.php');                //Initialise and load ini file
require ('../includes/error_handling.php');
require ('../includes/functions.php');      //Includes basic functions for non-secure pages
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
            if(($('#username').val() === '') || ($('#email').val() === ''))){
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
                $sql_fx = $db->real_escape_string($updatearray[0]['currency'][$i]);
                if($sql_fx === 'zzz'):
                    $sql_fx = NULL;
                elseif (!in_array($sql_fx, array_column($currencies, 'code'))):
                    $sql_fx = NULL;
                endif;
                ${'sqlfx'.$id} = $sql_fx;
                $sql_adm = $db->real_escape_string($updatearray[0]['adm'][$i]);
                ${'sqladm'.$id} = $sql_adm;
                //Simple update of fields
                $query = "UPDATE users SET username = ?, email = ?, status = ?, admin = ?, currency = ? WHERE usernumber = ?";
                $params = [$sql_name, $sql_eml, $sql_status, $sql_adm, $sql_fx, $sql_id];
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
            <h3> New user </h3>
            Leave password blank to have a random password generated and sent to the new user's email address.<br>
            <input type='hidden' name="newuser" value="yes">
            <input class="textinput" title="Please enter username" placeholder="Username" id="username" autocomplete="off" name="username" type="text" size="12" maxlength="12" /><br>
            <input class="textinput" title="Email address" placeholder="Email" id="email" autocomplete="user-email-for-form" name="email" type="email" size="64" maxlength="64" /><br>
            <input class="textinput" type="password" id='pword' title="Please Enter Your Password" placeholder="Password" size="20" autocomplete="user-password-for-form" name="password" maxlength="20" />
            <br><br>
            <input class="profilebutton" type="submit" value="ADD USER" />
        </form>

        <div>
            <h3>User table</h3> 
            Note, default currency is set in ini file ([fx], TargetCurrency)<?php 
            $allusertable = $db->execute_query("SELECT username, usernumber, email, badlogins, reg_date, lastlogin_date, status, admin, currency FROM users");?>
            <form name="updateusers" action="users.php" method="post">
                <table>
                    <tr>
                        <th style="padding: 5px;">User #</th>
                        <th style="padding: 5px;">Registered</th>
                        <th style="padding: 5px;">Last login</th>
                        <th style="padding: 5px;">Username</th>
                        <th style="padding: 5px;">Email</th>
                        <th style="padding: 5px;">Status</th>
                        <th style="padding: 5px;">Bad logins</th>
                        <th style="padding: 5px;">Local FX</th>
                        <th style="padding: 5px;">Admin</th>
                        <?php if($updateusers === 'yes'): ?>
                        <th style="padding: 5px;"></th>
                        <?php endif; ?>
                        <th style="padding: 5px;">Actions</th>
                    </tr>
                    <?php 
                    while ($alluserresults = $allusertable->fetch_assoc()): 
                        $usertable = $alluserresults['usernumber']."collection";
                        ?>
                        <tr>
                            <td style="padding: 5px;"> 
                                <?php echo $alluserresults['usernumber']; ?> 
                                <input type='hidden' name=id[] value='<?php echo $alluserresults['usernumber']; ?>'>
                            </td>
                            <td style="padding: 5px;"> 
                                <?php echo $alluserresults['reg_date']; ?> 
                            </td>
                            <td style="padding: 5px;"> 
                                <?php echo $alluserresults['lastlogin_date']; ?> 
                            </td>
                            <td style="padding: 5px;"> 
                                <input class="textinput" type='text' size='10' name=name[] value='<?php echo $alluserresults['username']; ?>'>
                            </td>
                            <td style="padding: 5px;">
                                <input class="textinput" type='email' size='30' name=eml[] value='<?php echo $alluserresults['email']; ?>'>
                            </td>
                            <td style="padding: 5px;"> 
                                <select class="dropdown" name='status[]'>
                                    <option value='active' <?php if($alluserresults['status'] === 'active'): echo "selected"; endif; ?> >active</option>
                                    <option value='disabled'  <?php if($alluserresults['status'] === 'disabled'): echo "selected"; endif; ?> >disabled</option>
                                    <option value='locked' <?php if($alluserresults['status'] === 'locked'): echo "selected"; endif; ?> >locked</option>
                                    <option value='chgpwd' <?php if($alluserresults['status'] === 'chgpwd'): echo "selected"; endif; ?> >password change required</option>
                                    <option value='mtce' <?php if($alluserresults['status'] === 'mtce'): echo "selected"; endif; ?> >site maintenance</option>
                                </select> 
                            </td>
                            <td style="padding: 5px;"> 
                                <?php echo $alluserresults['badlogins']; ?> 
                            </td>
                            <td style="padding: 5px;"> 
                                <select class="dropdown" name='currency[]'>
                                    <?php foreach($currencies as $currency): ?>
                                        <option value='<?php echo $currency['code']; ?>' 
                                            <?php if($alluserresults['currency'] === $currency['db']): ?>selected<?php endif; ?>>
                                            <?php echo $currency['pretty']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td style="padding: 5px;"> 
                                <select class="dropdown" name='adm[]'>
                                    <option value=1 <?php if($alluserresults['admin'] == 1): echo "selected"; endif; ?> >Yes</option>
                                    <option value=0  <?php if($alluserresults['admin'] == 0): echo "selected"; endif; ?> >No</option>
                                </select> 
                            </td>

                            <?php if($updateusers === 'yes'): ?>
                            <td style="padding: 5px;">
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
                            <td style="padding: 5px;">
                                <select class="dropdown" name='actions[]'>
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
                <input class="profilebutton" type="submit" value="UPDATE" />
            </form>
            <form id='exportcsv' action="/csv.php"  method="GET">
            </form>
            <h4>Export</h4>
            Export specific user's collection to a .csv file.
            <form action="/csv.php"  method="GET">
                <select class="dropdown" name='table'>
                <?php 
                $exportlist = $db->execute_query("SELECT usernumber,username FROM users");
                while ($listuser = $exportlist->fetch_assoc()):
                    $userno = $listuser['usernumber'];
                    $userid = $listuser['username'];
                    echo "<option value='{$userno}collection'>$userid</option>";
                endwhile;
                ?>
                </select>
                <br><br>
                <input class="profilebutton" type="submit" value="EXPORT CSV">
            </form>
        </div>
    </div>
</div>
    
<?php require('../includes/footer.php'); ?>
</body>
</html>