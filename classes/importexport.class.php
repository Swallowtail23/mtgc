<?php
/* Version:     2.0
    Date:       10/01/24
    Name:       importexport.class.php
    Purpose:    Import / export management class
    Notes:      - 
    To do:      -
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2023 Simon Wilson
    
 *  1.0
                Initial version
 * 
 *  2.0
 *              Break import into MTGC and Delver formats
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class ImportExport
{
    private $db;
    private $logfile;
    private $message;

    public function __construct($db, $logfile)
    {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->message = new Message();
    }

    public function exportCollectionToCsv($table,$filename = 'export.csv')
    {
    $csv_terminated = "\n";
    $csv_separator = ",";
    $csv_enclosed = '"';
    $csv_escaped = "\\";
    $table = $this->db->real_escape_string($table);
    $sql = "SELECT setcode,number_import,name,normal,$table.foil,$table.etched,$table.id as scryfall_id FROM $table JOIN cards_scry ON $table.id = cards_scry.id WHERE (($table.normal > 0) OR ($table.foil > 0) OR ($table.etched > 0))";
    $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Running Export Collection to CSV: $sql",$this->logfile);

    // Gets the data from the database
    $result = $this->db->query($sql);
    if($result === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
    else:
        $fields_cnt = $result->field_count;
        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Number of fields: $fields_cnt",$this->logfile);
        $schema_insert = '';
        for ($i = 0; $i < $fields_cnt; $i++):
            $fieldinfo = mysqli_fetch_field_direct($result, $i);
            $l = $csv_enclosed.str_replace($csv_enclosed, $csv_escaped.$csv_enclosed, stripslashes($fieldinfo->name)).$csv_enclosed;
            $schema_insert .= $l;
            $schema_insert .= $csv_separator;
        endfor;

        $out = trim(substr($schema_insert, 0, -1));
        $out .= $csv_terminated;

        // Format the data
        while($row = $result->fetch_row()):
            $schema_insert = '';
            for ($j = 0; $j < $fields_cnt; $j++):
                if ($row[$j] == '0' || $row[$j] != ''):
                    if ($csv_enclosed == ''):
                        $schema_insert .= $row[$j];
                    else:
                        $schema_insert .= $csv_enclosed .
                        str_replace($csv_enclosed, $csv_escaped . $csv_enclosed, $row[$j]) . $csv_enclosed;
                    endif;
                else:
                    $schema_insert .= '';
                endif;
                if ($j < $fields_cnt - 1):
                    $schema_insert .= $csv_separator;
                endif;
            endfor;
            $out .= $schema_insert;
            $out .= $csv_terminated;
        endwhile;
            $out .= $csv_enclosed;
            $out .= $csv_terminated;
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Length: " . strlen($out));
        // Output to browser with appropriate mime type, you choose ;)
        header("Content-type: text/x-csv; charset=UTF-8");
        //header("Content-type: text/csv");
        //header("Content-type: application/csv");
        header("Content-Disposition: attachment; filename=$filename");
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo $out;
        // exit;
    endif;
    }
    
    private function checkFormat($format,$d0,$d1,$d2,$d3,$d4,$d5,$d6)
    {
        // Check native MTG Collection import format
        if ($format === 'mtgc'):
            if (    (strpos(strtolower($d0),'code') === FALSE) OR
                    (strpos(strtolower($d1),'number') === FALSE) OR
                    (strpos(strtolower($d2),'name') === FALSE) OR
                    (strpos(strtolower($d3),'normal') === FALSE) OR
                    (strpos(strtolower($d4),'foil') === FALSE) OR
                    (strpos(strtolower($d5),'etched') === FALSE) OR
                    (strpos(strtolower($d6),'id') === FALSE)
                ):
                $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Import file {$_FILES['filename']['name']} does not contain correct '$format' header row",$this->logfile);
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Import file header row: '$d0', '$d1', '$d2', '$d3', '$d4', '$d5', '$d6'",$this->logfile);
                return "incorrect format";
            else:
                return "ok header";
            endif;
        
        // Check Delver Lens import format
        elseif ($format === 'delverlens'):
            if (    (strpos(strtolower($d0),'code') === FALSE) OR
                    (strpos(strtolower($d1),'number') === FALSE) OR
                    (strpos(strtolower($d2),'name') === FALSE) OR
                    (strpos(strtolower($d3),'non-foil') === FALSE) OR
                    ((strpos(strtolower($d4),'foil') === FALSE) OR (strpos(strtolower($d4),'non-foil') !== FALSE)) OR
                    (strpos(strtolower($d5),'id') === FALSE)
                ):
                $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Import file {$_FILES['filename']['name']} does not contain correct '$format' header row",$this->logfile);
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Import file header row: '$d0', '$d1', '$d2', '$d3', '$d4', '$d5', '$d6'",$this->logfile);
                return "incorrect format";
            else:
                return "ok header";
            endif;
        else:
            $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Import file {$_FILES['filename']['name']} does not contain valid header row",$this->logfile);
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Import file header row: '$d0', '$d1', '$d2', '$d3', '$d4', '$d5', '$d6'",$this->logfile);
            return "incorrect format";
        endif;
    }

    private function mapFormat($format,$d0,$d1,$d2,$d3,$d4,$d5,$d6)
    {
        $data = [];
        if ($format === 'mtgc'):
            $data[0] = $d0;
            $data[1] = $d1;
            $data[2] = stripslashes($d2);
            if (!empty($d3)): // normal qty
                $data[3] = $d3;
            else:
                $data[3] = 0;
            endif;
            if (!empty($d4)): // foil qty
                $data[4] = $d4;
            else:
                $data[4] = 0;
            endif;
            if (!empty($d5)): // etched qty
                $data[5] = $d5;
            else:
                $data[5] = 0;
            endif;
            if (!empty($d6)): // ID
                $data[6] = $d6;
            else:
                $data[6] = null;
            endif;
        elseif ($format === 'delverlens'):
            $data[0] = $d0;
            $data[1] = $d1;
            $data[2] = stripslashes($d2);

            if (!empty($d3)): // normal qty
                $data[3] = $d3;
            else:
                $data[3] = 0;
            endif;

            if (!empty($d4)): // foil qty
                $data[4] = $d4;
            else:
                $data[4] = 0;
            endif;

            $data[5] = 0; // Always set etched to 0 for Delver Lens, as it does not correctly support it

            if (!empty($d5)): // ID
                $data[6] = $d5;
            else:
                $data[6] = null;
            endif;
        else:
            return "error";
        endif;
        return $data;
    }

    public function importCollection($filename, $mytable, $importType, $useremail, $serveremail, $importFormat = 'mtgc') {
        
        // Check if called with a valid import type definition
        $validFormats = ['mtgc','delverlens'];
        if (!in_array($importFormat,$validFormats)):
            return "incorrect format";
        endif;
        
        // 'mtgc' expects header row to be: setcode,number,name,normal,foil,etched,id
        // 'delverlens' expects header row to be: Edition code,Collector's number,Name,Non-foil quantity,Foil quantity,Scryfall ID
        
        //Import uploaded file to Database
        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Import starting in '$importType' mode, '$importFormat' format",$this->logfile);
        $handle = fopen($filename, "r");
        $i = 0;
        $count = 0;
        $total = 0;
        $warningsummary = '';
        $warningheading = 'Warning type, Setcode, Row number, Setcode, Number, Import Name, Import Normal, Import Foil, Import Etched, Supplied ID, Database Name (if applicable), Database ID (if applicable)';
        while (($data = fgetcsv ($handle, 100000, ',')) !== FALSE):
            $idimport = 0;
            $row_no = $i + 1;
            if ($i === 0): // It's the header row, check to see if it matches the stated format
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Import file header row: " . implode(',', $data), $this->logfile);
                $validHeader = $this->checkFormat($importFormat,$data[0],$data[1],$data[2],$data[3],$data[4],$data[5],isset($data[6]) ? $data[6] : '');
                if ($validHeader !== "ok header"):
                    return "incorrect format";
                else:

                endif;
            elseif(isset($data[0]) AND isset($data[1]) AND isset($data[2])):  // We have bare minimum info - a setcode, a number and a name
                $dataMap = $this->mapFormat($importFormat,$data[0],$data[1],$data[2],$data[3],$data[4],$data[5],isset($data[6]) ? $data[6] : '');
                $data0 = $dataMap[0];
                $data1 = $dataMap[1];
                $data2 = $dataMap[2];
                $data3 = $dataMap[3];
                $data4 = $dataMap[4];
                $data5 = $dataMap[5];
                $data6 = $dataMap[6];
                
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no of import file (format: '$importFormat'): setcode({$data0}), number({$data1}), name ({$data2}), normal ({$data3}), foil ({$data4}), etched ({$data5}), id ({$data6})",$this->logfile);
                $supplied_id = $data6; // id
                if (!is_null($data6)): // ID has been supplied, run an ID check / import first
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Data has an ID ($data6), checking for a match",$this->logfile);
                    $cardtype = cardtype_for_id($data6);
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card type is: $cardtype",$this->logfile);
                    if($cardtype == 'nomatch'):
                        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: ID $data6 is not a valid id, trying setcode/number...",$this->logfile);
                        $importable = FALSE;
                    elseif($cardtype == 'none'):
                        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: ID $data6 is valid but db has no cardtype info",$this->logfile);
                        $importable = FALSE;
                    else:
                        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: ID $data6 is valid and we have cardtype info",$this->logfile);
                        if($cardtype == 'normalfoiletched'):
                            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Normal/Foil/Etched ID, no restrictions on card import",$this->logfile);
                            // All options available for import, no checks to be made
                            $importable = TRUE;
                        elseif($cardtype == 'normalfoil'):
                            if($data5 > 0):
                                $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Normal and Foil ID, but import contains Etched cards",$this->logfile);
                                echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                echo "<img src='/images/error.png' alt='Error'><br>";
                                $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6"."\n";
                                $warningsummary = $warningsummary.$newwarning;
                                $i = $i + 1;
                                continue;
                            else:
                                $importable = TRUE;
                            endif; 
                        elseif($cardtype == 'normaletched'):
                            if($data4 > 0):
                                $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Normal and Etched ID, but import contains Foil cards",$this->logfile);
                                echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                echo "<img src='/images/error.png' alt='Error'><br>";
                                $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6"."\n";
                                $warningsummary = $warningsummary.$newwarning;
                                $i = $i + 1;
                                continue;
                            else:
                                $importable = TRUE;
                            endif; 
                        elseif($cardtype == 'foiletched'):
                            if($data3 > 0):
                                $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Foil and Etched ID, but import contains Normal cards",$this->logfile);
                                echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                echo "<img src='/images/error.png' alt='Error'><br>";
                                $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6"."\n";
                                $warningsummary = $warningsummary.$newwarning;
                                $i = $i + 1;
                                continue;
                            else:
                                $importable = TRUE;
                            endif; 
                        elseif($cardtype == 'etchedonly'):
                            if($data3 > 0 or $data4 > 0):
                                $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Etched-only ID, but import contains Normal and/or Foil cards",$this->logfile);
                                echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                echo "<img src='/images/error.png' alt='Error'><br>";
                                $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6"."\n";
                                $warningsummary = $warningsummary.$newwarning;
                                $i = $i + 1;
                                continue;
                            else:
                                $importable = TRUE;
                            endif;                                                
                        elseif($cardtype == 'foilonly'):
                            if($data3 > 0 or $data5 > 0):
                                $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Foil-only ID, but import contains Normal and/or Etched cards",$this->logfile);
                                echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                echo "<img src='/images/error.png' alt='Error'><br>";
                                $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6"."\n";
                                $warningsummary = $warningsummary.$newwarning;
                                $i = $i + 1;
                                continue;
                            else:
                                $importable = TRUE;
                            endif;
                        elseif($cardtype == 'normalonly'):
                            if($data4 > 0 or $data5 > 0):
                                $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Foil-only ID, but import contains Foil and/or Etched cards",$this->logfile);
                                echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                echo "<img src='/images/error.png' alt='Error'><br>";
                                $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6"."\n";
                                $warningsummary = $warningsummary.$newwarning;
                                $i = $i + 1;
                                continue;
                            else:
                                $importable = TRUE;
                            endif;
                        endif;
                    endif;
                    if(isset($importable) AND $importable != FALSE):
                        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Match found for ID $data6 with no misallocated card types, will import",$this->logfile);
                        // Get existing values
                        $beforeStmt = $this->db->prepare("SELECT normal, foil, etched FROM `$mytable` WHERE id = ? LIMIT 1");
                        $beforeStmt->bind_param("s", $data6);
                        $beforeStmt->execute();
                        $result = $beforeStmt->get_result();
                        if ($result->num_rows > 0):
                            $currentValues = $result->fetch_assoc();
                            // Do something with $currentValues['normal'], $currentValues['foil'], and $currentValues['etched']
                        else:
                            $currentValues = ['normal' => 0, 'foil' => 0, 'etched' => 0];
                        endif;
                        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: ID $data6 has existing quantities of '{$currentValues['normal']}'/'{$currentValues['foil']}'/'{$currentValues['etched']}'",$this->logfile);
                        if ($importType === 'add'):
                            $stmt = $this->db->prepare("  INSERT INTO
                                                    `$mytable`
                                                    (id,normal,foil,etched)
                                                VALUES
                                                    (?,?,?,?)
                                                ON DUPLICATE KEY UPDATE
                                                    normal = normal + VALUES(normal),
                                                    foil   = foil + VALUES(foil),
                                                    etched = etched + VALUES(etched)
                                            ");
                            $desiredValues = ['normal' => $currentValues['normal'] + $data3, 'foil' => $currentValues['foil'] + $data4, 'etched' => $currentValues['etched'] + $data5];
                        elseif ($importType === 'replace'):
                            $stmt = $this->db->prepare("  INSERT INTO
                                                    `$mytable`
                                                    (id,normal,foil,etched)
                                                VALUES
                                                    (?,?,?,?)
                                                ON DUPLICATE KEY UPDATE
                                                    normal = VALUES(normal),
                                                    foil   = VALUES(foil),
                                                    etched = VALUES(etched)
                                            ");
                            $desiredValues = ['normal' => $data3, 'foil' => $data4, 'etched' => $data5];
                        else:
                            $stmt = FALSE;
                        endif;
                        if ($stmt === false):
                            trigger_error('[ERROR] profile.php: Preparing SQL: ' . $this->db->error, E_USER_ERROR);
                        endif;
                        $bind = $stmt->bind_param("ssss",
                                        $data6,
                                        $data3,
                                        $data4,
                                        $data5
                                    );
                        if ($bind === false):
                            trigger_error('[ERROR] profile.php: Binding parameters: ' . $this->db->error, E_USER_ERROR);
                        endif;
                        $exec = $stmt->execute();
                        if ($exec === false):
                            trigger_error("[ERROR] profile.php: Importing row $row_no" . $this->db->error, E_USER_ERROR);
                        else:
                            $status = $this->db->affected_rows; // 1 = add, 2 = change, 0 = no change
                            if($status === 1):
                                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: New, imported - no error returned; return code: $status",$this->logfile);
                            elseif($status === 2):
                                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Updated - no error returned; return code: $status",$this->logfile);
                            else:
                                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: No change - no error returned; return code: $status",$this->logfile);
                            endif;
                        endif;
                            $stmt->close();
                        if($status === 1 OR $status === 2 OR $status === 0):
                            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Import query ran - checking",$this->logfile);
                            if($sqlcheckqry = $this->db->execute_query("SELECT normal,foil,etched FROM $mytable WHERE id = ? LIMIT 1",[$data6])):
                                $rowcount = $sqlcheckqry->num_rows;
                                if($rowcount > 0):
                                    $sqlcheck = $sqlcheckqry->fetch_assoc();
                                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Check result = Normal: {$sqlcheck['normal']}; Foil: {$sqlcheck['foil']}; Etched: {$sqlcheck['etched']}",$this->logfile);
                                    if (($sqlcheck['normal'] == $desiredValues['normal']) AND ($sqlcheck['foil'] == $desiredValues['foil']) AND ($sqlcheck['etched'] == $desiredValues['etched'])):
                                        $total = $total + $data3 + $data4 + $data5;
                                        $count = $count + 1;
                                        $idimport = 1;
                                    else:
                                        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Check result = new result qties do not match desired result qties",$this->logfile);
                                        $idimport = 20;
                                    endif;
                                else:
                                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Check result = No match",$this->logfile);
                                    $idimport = 0;
                                endif;
                            else:
                                trigger_error("[ERROR]: SQL failure: " . $this->db->error, E_USER_ERROR);
                            endif;
                        endif;
                    endif;    
                endif;
                if (!empty($data0) AND !empty($data1) AND !empty($data2) AND $idimport === 0): // ID import has not been successful, try with setcode, number, name
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Data place 1 (setcode - $data0), place 2 (number - $data1) place 3 (name - $data2) without ID - trying setcode/number",$this->logfile);
                    $stmt = $this->db->execute_query("SELECT id,name,printed_name,flavor_name,f1_name,f1_printed_name,f1_flavor_name,f2_name,f2_printed_name,f2_flavor_name,finishes FROM cards_scry WHERE setcode = ? AND number_import = ? LIMIT 1", [$data0,$data1]);
                    if($stmt != TRUE):
                        trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $this->db->error, E_USER_ERROR);
                    else:
                        if ($stmt->num_rows > 0):
                            $result = $stmt->fetch_assoc();
                            if(isset($result['name'])):
                                $db_name = $result['name'];
                                $db_id = $result['id'];
                                $db_all_names = array("{$result['name']}","{$result['printed_name']}","{$result['flavor_name']}","{$result['f1_name']}","{$result['f1_printed_name']}","{$result['f1_flavor_name']}","{$result['f2_name']}","{$result['f2_printed_name']}","{$result['f2_flavor_name']}");
                                if($db_name != $data2):
                                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Supplied card setcode and number do not match primary db name for id {$result['id']}, checking other db names",$this->logfile);
                                    if(!in_array($data2,$db_all_names)):
                                        $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": No db name match for {$result['id']} (db names: $db_all_names[0], $db_all_names[1], $db_all_names[2], $db_all_names[3], $db_all_names[4], $db_all_names[5], $db_all_names[6], $db_all_names[7], $db_all_names[8])",$this->logfile);
                                        echo "Row $row_no: ERROR: ID and Name not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                        echo "<img src='/images/error.png' alt='Error'><br>";
                                        $newwarning = "ERROR - name mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                        $warningsummary = $warningsummary.$newwarning;
                                        $i = $i + 1;
                                        continue;
                                    else:
                                        $importtype = 'alternate_name';
                                        $data6 = $result['id'];
                                        $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Supplied name $data2 matches with a secondary name for id {$result['id']}, will import",$this->logfile);
                                    endif;
                                else:
                                    if(isset($result['finishes'])):
                                        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Card setcode and number matches on supplied name ($data2) for db id {$result['id']}, looking up finishes",$this->logfile);
                                        $data6 = $result['id'];
                                        $finishes = json_decode($result['finishes'], TRUE);
                                        $cardtype = cardtypes($finishes);
                                        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card type is: $cardtype",$this->logfile);
                                        if($cardtype != 'none'):
                                            if($cardtype == 'normalfoiletched'):
                                                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Normal/Foil/Etched ID, no restrictions on card import",$this->logfile);
                                            elseif($cardtype == 'normalfoil'):
                                                if($data5 > 0):
                                                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Normal and Foil ID, but import contains Etched cards",$this->logfile);
                                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif; 
                                            elseif($cardtype == 'normaletched'):
                                                if($data4 > 0):
                                                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Normal and Etched ID, but import contains Foil cards",$this->logfile);
                                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif; 
                                            elseif($cardtype == 'foiletched'):
                                                if($data3 > 0):
                                                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Foil and Etched ID, but import contains Normal cards",$this->logfile);
                                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif; 
                                            elseif($cardtype == 'etchedonly'):
                                                if($data3 > 0 or $data4 > 0):
                                                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Etched-only ID, but import contains Normal and/or Foil cards",$this->logfile);
                                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif;                                                
                                            elseif($cardtype == 'foilonly'):
                                                if($data3 > 0 or $data5 > 0):
                                                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Foil-only ID, but import contains Normal and/or Etched cards",$this->logfile);
                                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif;
                                            elseif($cardtype == 'normalonly'):
                                                if($data4 > 0 or $data5 > 0):
                                                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Card matches to a Foil-only ID, but import contains Foil and/or Etched cards",$this->logfile);
                                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif;
                                            endif;
                                        endif;    
                                    endif; 
                                endif;
                                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Setcode ($data0)/collector number ($data1) with supplied ID ($supplied_id) matched on name and importing as ID $data6",$this->logfile);
                            endif;
                        else: //if ($stmt->num_rows > 0)
                            $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Card setcode and number do not match a card in db",$this->logfile);
                            echo "Row $row_no: ERROR: ID and name not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                            echo "<img src='/images/error.png' alt='Error'><br>";
                            $newwarning = "ERROR - failed to find an ID and name match, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, N/A, N/A \n";
                            $warningsummary = $warningsummary.$newwarning;
                            $i = $i + 1;
                            continue;
                        endif;
                    endif;    

                    if (!empty($data6)): //write the import
                        // Get existing values
                        $beforeStmt = $this->db->prepare("SELECT normal, foil, etched FROM `$mytable` WHERE id = ? LIMIT 1");
                        $beforeStmt->bind_param("s", $data6);
                        $beforeStmt->execute();
                        $result = $beforeStmt->get_result();
                        if ($result->num_rows > 0):
                            $currentValues = $result->fetch_assoc();
                            // Do something with $currentValues['normal'], $currentValues['foil'], and $currentValues['etched']
                        else:
                            $currentValues = ['normal' => 0, 'foil' => 0, 'etched' => 0];
                        endif;
                        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: ID $data6 has existing quantities of '{$currentValues['normal']}'/'{$currentValues['foil']}'/'{$currentValues['etched']}'",$this->logfile);
                        if ($importType === 'add'):
                            $stmt = $this->db->prepare("  INSERT INTO
                                                    `$mytable`
                                                    (id,normal,foil,etched)
                                                VALUES
                                                    (?,?,?,?)
                                                ON DUPLICATE KEY UPDATE
                                                    normal = normal + VALUES(normal),
                                                    foil   = foil + VALUES(foil),
                                                    etched = etched + VALUES(etched)
                                            ");
                            $desiredValues = ['normal' => $currentValues['normal'] + $data3, 'foil' => $currentValues['foil'] + $data4, 'etched' => $currentValues['etched'] + $data5];
                        elseif ($importType === 'replace'):
                            $stmt = $this->db->prepare("  INSERT INTO
                                                    `$mytable`
                                                    (id,normal,foil,etched)
                                                VALUES
                                                    (?,?,?,?)
                                                ON DUPLICATE KEY UPDATE
                                                    normal = VALUES(normal),
                                                    foil   = VALUES(foil),
                                                    etched = VALUES(etched)
                                            ");
                            $desiredValues = ['normal' => $data3, 'foil' => $data4, 'etched' => $data5];
                        else:
                            $stmt = FALSE;
                        endif;
                        if ($stmt === false):
                            trigger_error('[ERROR] profile.php: Preparing SQL: ' . $this->db->error, E_USER_ERROR);
                        endif;
                        $bind = $stmt->bind_param("ssss",
                                        $data6,
                                        $data3,
                                        $data4,
                                        $data5
                                    );
                        if ($bind === false):
                            trigger_error('[ERROR] profile.php: Binding parameters: ' . $this->db->error, E_USER_ERROR);
                        endif;
                        $exec = $stmt->execute();
                        if ($exec === false):
                            trigger_error("[ERROR] profile.php: Importing row $row_no" . $this->db->error, E_USER_ERROR);
                        else:
                            $status = $this->db->affected_rows; // 1 = add, 2 = change, 0 = no change
                            if($status === 1):
                                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: New, imported - no error returned; return code: $status",$this->logfile);
                            elseif($status === 2):
                                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Updated - no error returned; return code: $status",$this->logfile);
                            else:
                                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: No change - no error returned; return code: $status",$this->logfile);
                            endif;
                        endif;
                        $stmt->close();
                        if($status === 1 OR $status === 2 OR $status === 0):
                            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Import query ran OK - checking...",$this->logfile);
                            if($sqlcheckqry = $this->db->execute_query("SELECT normal,foil,etched FROM $mytable WHERE id = ? LIMIT 1",[$data6])):
                                $rowcount = $sqlcheckqry->num_rows;
                                if($rowcount > 0):
                                    $sqlcheck = $sqlcheckqry->fetch_assoc();
                                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Check result = Normal: {$sqlcheck['normal']}; Foil: {$sqlcheck['foil']}; Etched: {$sqlcheck['etched']}",$this->logfile);
                                    if (($sqlcheck['normal'] == $desiredValues['normal']) AND ($sqlcheck['foil'] == $desiredValues['foil']) AND ($sqlcheck['etched'] == $desiredValues['etched'])):
                                        if(isset($importtype) AND $importtype == 'alternate_name'):
                                            $newwarning = "WARNING - card matched to alternate card name, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $supplied_id, $db_name, $db_id \n";
                                            $warningsummary = $warningsummary.$newwarning;
                                        else:
                                            // echo "Row $row_no: NORMAL: Setcode/number matched, successful import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) <img src='/images/success.png' alt='Success'><br>";
                                        endif;
                                            $total = $total + $data3 + $data4 + $data5;
                                            $count = $count + 1;
                                    else: 
                                        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Check result = new result qties do not match desired result qties",$this->logfile); ?>
                                        <img src='/images/error.png' alt='Failure'><br> <?php
                                    endif;
                                else:
                                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Row $row_no: Check result = No match",$this->logfile);
                                    $idimport = 0;                                
                                endif;
                            else:
                                trigger_error("[ERROR]: SQL failure: " . $this->db->error, E_USER_ERROR);
                            endif;
                        endif;
                    endif;
                elseif($idimport === 1):
                    // do nothing
                else:
                    echo "Row ",$i+1,": Check row - not enough data to identify card <img src='/images/error.png' alt='Failure'><br>";
                    $newwarning = "ERROR - not enough data to identify card, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, N/A, N/A \n";
                    $warningsummary = $warningsummary.$newwarning;
                endif;
            endif;
            $i = $i + 1;
        endwhile;
        fclose($handle);
        $summary = "Import done - $count unique cards, $total in total.";
        print $summary;
        if ($warningsummary === ''):
            $warningsummary = 'No warnings or errors';
        endif;
        $from = "From: $serveremail\r\nReturn-path: $serveremail"; 
        $subject = "Import failures / warnings"; 
        $message = "$warningheading \n \n $warningsummary \n \n $summary";
        mail($useremail, $subject, $message, $from); 
        $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Import finished",$this->logfile);
        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Warnings: '$warningsummary'",$this->logfile); ?>
        <script type="text/javascript">
            (function() {
                fetch('/valueupdate.php?table=<?php echo("$mytable"); ?>');
            })();

            alert('Import completed - a full collection value resync is being run, and can also take several minutes. Accessing your Profile page while this is running will take longer than usual.');
            window.onload=function(){document.body.style.cursor='wait';}
        </script> <?php
    }

    public function __toString() {
        $this->message->MessageTxt("[ERROR]", "Class " . __CLASS__, "Called as string");
        return "Called as a string";
    }
}