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

session_start();
require ('../includes/ini.php');                //Initialise and load ini file
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');      //Includes basic functions for non-secure pages
require ('adminfunctions.php');
require ('../includes/secpagesetup.php');       //Setup page variables
forcechgpwd();                                  //Check if user is disabled or needs to change password

//Check if user is logged in, if not redirect to login.php
$obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Admin page called by user $username ($useremail)",$logfile);
// Is admin running the page
$obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Admin is $admin",$logfile);
if ($admin !== 1):
    require('reject.php');
endif;


if (isset($_POST['newuser'])):
    $newuser = ($_POST['newuser'] == 'yes') ? 'yes' : '';
    if (isset($_POST['password'])):
        $password = $_POST['password'];
    endif;    
    if (isset($_POST['email'])):
        $postemail = check_input($_POST['email']);
    endif; 
    if (isset($_POST['username'])):
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
            $newuserstatus = newuser($username, $postemail, $password, $dbname);
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
                $sql_id = $db->escape($updatearray[0]['id'][$i]);
                ${'sqlid'.$id} = $sql_id;
                $sql_eml = $db->escape($updatearray[0]['eml'][$i]);
                ${'sqleml'.$id} = $sql_eml;
                $sql_name = $db->escape($updatearray[0]['name'][$i]);
                ${'sqlname'.$id} = $sql_name;
                $sql_status = $db->escape($updatearray[0]['status'][$i]);
                ${'sqlstatus'.$id} = $sql_status;
                $sql_adm = $db->escape($updatearray[0]['adm'][$i]);
                ${'sqladm'.$id} = $sql_adm;
                //Simple update of fields
                if ($simpleupdate = $db->query("UPDATE users SET username='$sql_name',
                                email='$sql_eml', status='$sql_status',
                                admin=$sql_adm WHERE usernumber='$sql_id'") === TRUE):
                    $affected_rows = $db->affected_rows;
                    $obj = new Message;
                    $obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Update user query by $useremail from {$_SERVER['REMOTE_ADDR']} affected $affected_rows rows",$logfile);
                else:
                    $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Update user query unsuccessful",$logfile);
                endif;
                $usertable = $sql_id."collection";
                // More complex updates
                // - delete card collection for a user
                if (($updatearray[0]['actions'][$i]) == 'deletecards'):
                    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Clearing collection for $sql_name from {$_SERVER['REMOTE_ADDR']}",$logfile);
                    if ($db->delete("$usertable")):
                        if($deletecards = $db->select('*',"$usertable")):
                            if ($deletecards->num_rows == 0):
                                echo "<div class='alert-box success'><span>success: </span>Cards cleared for $sql_name</div>";    
                                $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Table empty successful",$logfile);
                            else:    
                                echo "<div class='alert-box error'><span>error: </span>Cards not cleared for $sql_name</div>";    
                                $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Table empty failed",$logfile);
                            endif;
                        endif;
                    endif;
                // - delete user and collection
                elseif (($updatearray[0]['actions'][$i]) == 'deleteuser'):
                    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Nuking $sql_name from {$_SERVER['REMOTE_ADDR']}",$logfile);
                    if ($db->delete('users',"WHERE usernumber = '$sql_id'")):
                        if($nukeuser = $db->select('username','users',"WHERE usernumber = '$sql_id'")):
                            if ($nukeuser->num_rows == 0):
                                echo "<div class='alert-box success'><span>success: </span>User $sql_name removed</div>";    
                                $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"User deletion successful",$logfile);
                            else:    
                                echo "<div class='alert-box error'><span>error: </span>User $sql_name not removed</div>";    
                                $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"User deletion failed",$logfile);
                            endif;
                        endif;
                    endif;
                    $sqldrop = "DROP TABLE $usertable";
                    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Running $sqldrop",$logfile);
                    $db->query($sqldrop);
                    $queryexists = "SHOW TABLES LIKE '$usertable'";
                    $stmt = $db->prepare($queryexists);
                    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Checking if collection table still exists: $queryexists",$logfile);
                    $exec = $stmt->execute();
                    if ($exec === false):
                        $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Collection table check failed",$logfile);
                    else:
                        $stmt->store_result();
                        $collection_exists = $stmt->num_rows; //$collection_exists now includes the quantity of tables with the collection name
                        $stmt->close();
                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Collection table check returned $collection_exists rows",$logfile);
                        if($collection_exists === 0): //No existing collection table
                            echo "<div class='alert-box success'><span>success: </span>Table dropped for $sql_name</div>";
                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Collection table check shows 0",$logfile);
                        elseif($collection_exists == -1):
                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Shouldn't be here...",$logfile);
                        else: // There is still a table with this name
                            echo "<div class='alert-box error'><span>error: </span>Table not dropped for $sql_name</div>";
                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Table still exists",$logfile);
                        endif;
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
            <h3>User table</h3>
            <?php 
            $allusertable = $db->select('username, usernumber, email, reg_date, lastlogin_date, status, admin','users');
            ?>
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
                                $updateoutcome = $db->select_one('username, email, status, admin','users',"WHERE usernumber={$alluserresults['usernumber']}");
                                if(($updateoutcome['username'] === ${'sqlname'.$alluserresults['usernumber']}) 
                                    AND ($updateoutcome['email'] === ${'sqleml'.$alluserresults['usernumber']})
                                    AND ($updateoutcome['status'] === ${'sqlstatus'.$alluserresults['usernumber']})
                                    AND ($updateoutcome['admin'] === ${'sqladm'.$alluserresults['usernumber']})): ?>
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
                $exportlist = $db->select('usernumber,username','users');
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