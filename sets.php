<?php 
/* Version:     4.0
    Date:       02/12/2023
    Name:       sets.php
    Purpose:    Lists all setcodes and sets in the database
    Notes:      This page is the only one NOT mobile responsive design. 
 *              This is because the only way to access it is from the
                link on profile.php, from a <div> that is not visible on mobile.
 *  To do:      -
        
    1.0
                Initial version
    2.0         
                Moved to use Mysqli_Manager library
 *  3.0
 *              Refactoring for cards_scry
 *
 *  4.0         02/12/2023
 *              Add pagination and set image reload for admins
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');     //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');      //Setup page variables
forcechgpwd();                              //Check if user is disabled or needs to change password
$msg = new Message;

?> 
<!DOCTYPE html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title>MtG collection sets</title>
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <link href="//cdn.jsdelivr.net/npm/keyrune@latest/css/keyrune.css" rel="stylesheet" type="text/css" />
    <?php include('includes/googlefonts.php');?>
    <script src="/js/jquery.js"></script>
    <?php
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $setsPerPage = 30; // Adjust this value based on your preference
    $offset = ($page - 1) * $setsPerPage;
    ?>
    <script>
        function reloadImages(setcode) {
            document.body.style.cursor = "wait";
            $.ajax({
                type: 'POST',
                url: 'admin/ajaxsetimg.php',
                data: { setcode: setcode },
                success: function(response) {
                    // Parse the JSON response
                    var result = JSON.parse(response);

                    // Display the message using an alert box
                    showMessage(result.status, result.message);

                    if (result.status === 'success') {
                        console.log(result.message);
                    } else {
                        console.error(result.message);
                    }
                    document.body.style.cursor = "default";
                },
                error: function(error) {
                    // Display an error message using an alert box
                    showMessage('error', 'An error occurred.');
                    console.error(error);
                    document.body.style.cursor = "default";
                }
            });
        }

        function showMessage(status, message) {
            // Display the message using an alert box
            alert(message);
        }
    </script>
    <script>
        // Function to send an AJAX request to filter sets
        var isAdmin = <?php echo json_encode($admin == 1); ?>;
        function filterSets(filterValue, setsPerPage) {
            var filterValue = document.getElementById('setCodeFilter').value;

            if (filterValue.length >= 3) {
                $.ajax({
                    type: 'GET',
                    url: 'ajax/ajaxsets.php',
                    data: { filter: filterValue,
                            setsPerPage: setsPerPage,
                            offset: 0},
                    dataType: 'json',
                    success: function(response) {
                        // Update the table with the filtered results
                        updateTable(response);
                        document.getElementById('pagination').style.display = 'none';
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error("AJAX error: " + textStatus + " - " + errorThrown);
                    }
                });
            } else if (filterValue.length === 0) {
                // Reload the default sets.php when filterValue is back to zero
                $.ajax({
                    type: 'GET',
                    url: 'ajax/ajaxsets.php',
                    data: { filter: filterValue,
                            setsPerPage: setsPerPage,
                            offset: <?php echo $offset; ?>},
                    dataType: 'json',
                    success: function(response) {
                        // Update the table with the filtered results
                        updateTable(response);
                        document.getElementById('pagination').style.display = '';
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error("AJAX error: " + textStatus + " - " + errorThrown);
                    }
                });
            }
        }

        // Function to update the table with filtered results
            function updateTable(filteredSets) {
                var table = document.querySelector('#setlist');
                var tableBody = table.getElementsByTagName('tbody')[0];
                var currentYear = ''; // Variable to keep track of the current year
                var totalColumns = isAdmin ? 8 : 7; // Total columns in the table

                while (tableBody.rows.length > 1) {
                    tableBody.deleteRow(1);
                }

                filteredSets.forEach(function (set) {
                    var setYear = new Date(set.setdate).getFullYear().toString();
                    if (setYear != currentYear) {
                        var yearRow = tableBody.insertRow(tableBody.rows.length);
                        var yearCell = yearRow.insertCell(0);
                        yearCell.colSpan = totalColumns;
                        yearCell.className = "year-header";
                        yearCell.innerHTML = '<h3>' + setYear + '</h3>';
                        currentYear = setYear;
                    }

                    var row = tableBody.insertRow(tableBody.rows.length);
                // Populate the row with data from set
                var iconCell = row.insertCell(0);
                var setcode = set.setcode;
                var time = new Date().getTime(); // To ensure fresh image load
                var img = document.createElement('img');
                img.className = 'seticon';
                img.src = 'cardimg/seticons/' + setcode + '.svg?' + time;
                img.alt = setcode.toUpperCase();
                iconCell.appendChild(img);

                var codeCell = row.insertCell(1);
                var setcodeupper = set.setcode.toUpperCase();
                var link = document.createElement('a');
                link.href = 'index.php?adv=yes&searchname=yes&legal=any&set%5B%5D=' + encodeURIComponent(setcodeupper) + '&sortBy=setdown&layout=grid';
                link.textContent = setcodeupper;
                codeCell.appendChild(link);

                var nameCell = row.insertCell(2);
                nameCell.textContent = set.set_name;

                var typeCell = row.insertCell(3);
                var setType = set.set_type.split('_').map(function(word) {
                    return word.charAt(0).toUpperCase() + word.slice(1);
                    }).join(' ');
                setType = setType.replace(/_/g, ' ');
                typeCell.textContent = setType;
                typeCell.classList.add('columnhide');

                var parentCell = row.insertCell(4);
                parentCell.textContent = set.parent_set_code.toUpperCase();
                parentCell.classList.add('columnhide');

                var dateCell = row.insertCell(5);
                var inputDate = set.setdate; // Replace set.setdate with your date variable

                // Split the input date into components
                var dateComponents = inputDate.split('-');
                var year = dateComponents[0];
                var month = dateComponents[1];
                var day = parseInt(dateComponents[2]);

                // Create an array of month names
                var monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

                // Format the date as "30 Oct 2010" and set it in dateCell
                dateCell.textContent = monthNames[parseInt(month) - 1] + " " + day;

                var countCell = row.insertCell(6);
                countCell.textContent = set.card_count.toLocaleString();
                countCell.classList.add('columnhide');

                if (isAdmin) {
                    var reloadCell = row.insertCell(7);
                    reloadCell.style.textAlign = 'center';

                    var link = document.createElement('a');
                    link.href = "javascript:void(0);";
                    link.onclick = function() {
                        reloadImages(set.setcode);
                    };

                    var iconSpan = document.createElement('span');
                    iconSpan.className = "material-symbols-outlined";
                    iconSpan.textContent = "frame_reload";

                    link.appendChild(iconSpan);
                    reloadCell.appendChild(link);
                }
            });
        }
    </script>
    <script>
        $(document).ready(function() {
            $('#setCodeFilter').focus();
        });
    </script>
</head>

<body class="body">
<?php 
include_once("includes/analyticstracking.php");
require('includes/overlays.php');             
require('includes/header.php');
require('includes/menu.php'); 
?>
    
<div id='page'>
    <div class='staticpagecontent'>
        <?php
        $stmt = $db->prepare("SELECT 
                                set_name,
                                setcode,
                                parent_set_code,
                                set_type,
                                card_count,
                                nonfoil_only,
                                foil_only,
                                min(cards_scry.release_date) as date,
                                sets.release_date as setdate
                            FROM cards_scry 
                            LEFT JOIN sets ON cards_scry.set_id = sets.id
                            GROUP BY 
                                set_name
                            ORDER BY 
                                setdate DESC, parent_set_code DESC 
                            LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $setsPerPage, $offset);
        if ($stmt === false):
            trigger_error("[ERROR] ".basename(__FILE__)." ".__LINE__,": Preparing SQL: " . $db->error, E_USER_ERROR);
        endif;
        $exec = $stmt->execute();
        if ($exec === false):
            trigger_error("[ERROR] ".basename(__FILE__)." ".__LINE__,": Executing SQL: " . $db->error, E_USER_ERROR);
        else: 
            $result = $stmt->get_result();
            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,$result->num_rows." results",$logfile);
            if ($result->num_rows === 0):
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__.": No results ". $db->error, E_USER_ERROR);
            endif;
        endif;
        ?>
        <div class="sets-header-container">
            <h2 class='h2pad sets-header'>Sets Information</h2>
            <div class="filter-container">
                <input type="text" class="textinput" id="setCodeFilter" oninput="filterSets(this.value, <?php echo $setsPerPage; ?>)" placeholder="SETNAME/CODE FILTER">
            </div>
        </div>
        <table id='setlist'>
            <tr>
                <td class='setcell'>
                    <b>Icon</b>
                </td>
                <td class='setcell'>
                    <b>Code</b>
                </td>
                <td class='setcell'>
                    <b>Name</b>
                </td>
                <td class='setcell columnhide'>
                    <b>Type</b>
                </td>
                <td class='setcell columnhide'>
                    <b>Parent set</b>
                </td>
                <td class='setcell'>
                    <b>Release date</b>
                </td>
                <td class='setcell columnhide'>
                    <b>Card count</b>
                </td>
                <?php if ($admin == 1): ?>
                    <td class='setcell'>
                        <b>Reload images</b>
                    </td>
                <?php endif; ?>
            </tr>
            <?php
            $currentYear = null;
            if($result === false):
                // Should never get here with catches above
                $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Error retrieving data",$logfile); ?>
                <tr>
                    <td colspan="2">Error retrieving data</td>
                </tr> <?php
            else:
                while ($row = $result->fetch_assoc()): 
                    $setYear = date('Y', strtotime($row['setdate']));
                    if(isset($row['setcode']) AND $row['setcode'] !== null):
                        $setcodeupper = strtoupper($row['setcode']);
                    else:
                        $setcodeupper = '';
                    endif;
                    if(isset($row['set_name']) AND $row['set_name'] !== null):
                        $setname = $row['set_name'];
                    else:
                        $setname = '';
                    endif;
                    if(isset($row['set_type']) AND $row['set_type'] !== null):
                        $settype = ucwords(str_replace('_', ' ', $row['set_type']));
                    else:
                        $settype = '';
                    endif;
                    if(isset($row['parent_set_code']) AND $row['parent_set_code'] !== null):
                        $parentsetcode = strtoupper($row['parent_set_code']);
                    else:
                        $parentsetcode = '';
                    endif;
                    if(isset($row['setdate']) AND $row['setdate'] !== null):
                        $setdate = strtoupper($row['setdate']);
                    else:
                        $setdate = '';
                    endif;
                    if(isset($row['card_count']) AND $row['card_count'] !== null):
                        $cardcount = strtoupper($row['card_count']);
                    else:
                        $cardcount = '';
                    endif;
                    if ($setYear != $currentYear):
                        echo '<tr>';
                        if ($admin == 1):
                            echo '<td colspan="8" class="year-header"><h3>' . $setYear . '</h3></td>';
                        else:
                            echo '<td colspan="7" class="year-header"><h3>' . $setYear . '</h3></td>';
                        endif;
                        echo '</tr>';
                        $currentYear = $setYear;
                    endif;

                    ?>
                    <tr>
                        <td class='setcell'>
                            <?php 
                            $time = time();
                            echo "<img class='seticon' src='cardimg/seticons/{$row['setcode']}.svg?$time' alt='$setcodeupper'>"; ?>
                        </td>
                        <td class='setcell'>
                            <?php echo "<a href='index.php?adv=yes&amp;searchname=yes&amp;legal=any&amp;set%5B%5D=$setcodeupper&amp;sortBy=setdown&amp;layout=grid'>$setcodeupper</a>"; ?>
                        </td>
                        <td class='setcell'>
                            <?php echo $setname; ?>
                        </td>
                        <td class='setcell columnhide'>
                            <?php echo $settype; ?>
                        </td>
                        <td class='setcell columnhide'>
                            <?php echo $parentsetcode; ?>
                        </td>
                        <td class='setcell'>
                            <?php echo date('M j', strtotime($setdate)); ?>
                        </td>
                        <td class='setcell columnhide' style='text-align: center;'>
                            <?php echo number_format($cardcount); ?>
                        </td>
                        <td class='setcell' style='text-align: center;'>
                            <?php echo ($admin == 1 ? '<a href="javascript:void(0);" onclick="reloadImages(\''.$row['setcode'].'\')"><span class="material-symbols-outlined">frame_reload</span></a>' : ''); ?>
                        </td>
                    </tr>
                    <?php 
                endwhile;
            endif;
            ?>
        </table>
        <br>&nbsp; <?php
        $totalSetsQuery = $db->query("SELECT COUNT(DISTINCT set_name) as totalSets FROM cards_scry");
        $totalSets = $totalSetsQuery->fetch_assoc()['totalSets'];
        $totalPages = ceil($totalSets / $setsPerPage);

        echo '<div id="pagination" class="pagination">';
            for ($i = 1; $i <= $totalPages; $i++):
                if ($i == $page):
                    // Current page, display without hyperlink
                    echo '<span>' . $i . '&nbsp;&nbsp;</span>';
                else:
                    // Other pages, display with hyperlink
                    echo '<a href="?page=' . $i . '">' . $i . '&nbsp;&nbsp;</a>';
                endif;
            endfor;
        echo '</div>';
        ?>
    </div>
</div>
<?php     
    require('includes/footer.php'); 
?>
</body>
</html>