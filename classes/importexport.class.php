<?php
/* Version:     3.1
    Date:       20/01/24
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
 * 
 *  3.0         13/01/24
 *              Move to use PHPMailer
 *  
    3.1         20/01/24
 *              Move to logMessage
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class ImportExport
{
    private $db;
    private $logfile;
    private $useremail;
    private $serveremail;
    private $message;
    private $siteTitle;
    
    public function __construct($db, $logfile, $useremail, $serveremail, $siteTitle = null)
    {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->useremail = $useremail;
        $this->serveremail = $serveremail;
        $this->message = new Message($this->logfile);
        $this->siteTitle = $siteTitle ?: $GLOBALS['siteTitle'];
    }

    public function exportCollectionToCsv($table, $myURL, $smtpParameters, $format = 'echo', $filename = 'export.csv', $username = '', $useremail = '')
    {
        $csv_terminated = "\n";
        $csv_separator = ",";
        $csv_enclosed = '"';
        $csv_escaped = "\\";
        $table = $this->db->real_escape_string($table);
        $sql = "SELECT setcode,number_import,name,normal,$table.foil,$table.etched,$table.id as scryfall_id FROM $table JOIN cards_scry ON $table.id = cards_scry.id WHERE (($table.normal > 0) OR ($table.foil > 0) OR ($table.etched > 0))";
        $this->message->logMessage('[NOTICE]',"Running Export Collection to CSV: $sql");

        // Gets the data from the database
        $result = $this->db->query($sql);
        if($result === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
        else:
            $fields_cnt = $result->field_count;
            $this->message->logMessage('[DEBUG]',"Number of fields: $fields_cnt");
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
            $out .= $csv_terminated;
            if ($format === 'echo'):
                header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                header("Content-Length: " . strlen($out));
                header("Content-type: text/x-csv; charset=UTF-8");
                header("Content-Disposition: attachment; filename=$filename");
                echo "\xEF\xBB\xBF"; // UTF-8 BOM
                echo $out;
            elseif($format === 'email'):
                $mail = new myPHPMailer(true, $smtpParameters, $this->serveremail, $this->logfile);

                $tempFile = tempnam(sys_get_temp_dir(), 'export_');
                file_put_contents($tempFile, $out);

                $subject = "Collection export";
                $emailbody = "Your $this->siteTitle export is attached. <br><br> Opt out of automated emails in your profile at <a href='$myURL/profile.php'>your $this->siteTitle profile page</a>";
                $emailaltbody = "Your $this->siteTitle export is attached. \r\n\r\n Opt out of automated emails in your profile at your $this->siteTitle profile page ($myURL/profile.php) \r\n\r\n";
                $mailresult = $mail->sendEmail($this->useremail, TRUE, $subject, $emailbody, $emailaltbody, $tempFile, $filename);
                if (isset($tempFile)):
                    unlink($tempFile);
                endif;
                if($mailresult === TRUE):
                    return TRUE;
                else:
                    return FALSE;
                endif;
            elseif($format === 'weekly' && $username !== '' && $useremail !== ''):
                $mail = new myPHPMailer(true, $smtpParameters, $this->serveremail, $this->logfile);

                $tempFile = tempnam(sys_get_temp_dir(), 'export_');
                file_put_contents($tempFile, $out);

                $subject = "$this->siteTitle weekly export";
                $emailbody = "Hi $username, please see attached your weekly collection export from $this->siteTitle. <br><br> Opt out of automated emails in your profile at <a href='$myURL/profile.php'>your $this->siteTitle profile page</a>";
                $emailaltbody = "Hi $username, please see attached your weekly collection export from $this->siteTitle. \r\n\r\n Opt out of automated emails in your profile at your $this->siteTitle profile page ($myURL/profile.php) \r\n\r\n";
                $mailresult = $mail->sendEmail($useremail, TRUE, $subject, $emailbody, $emailaltbody, $tempFile, $filename);
                if (isset($tempFile)):
                    unlink($tempFile);
                endif;
                if($mailresult === TRUE):
                    return TRUE;
                else:
                    return FALSE;
                endif;
            endif;
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
                $this->message->logMessage('[ERROR]',"Import file {$_FILES['filename']['name']} does not contain correct '$format' header row");
                $this->message->logMessage('[DEBUG]',"Import file header row: '$d0', '$d1', '$d2', '$d3', '$d4', '$d5', '$d6'");
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
                $this->message->logMessage('[ERROR]',"Import file {$_FILES['filename']['name']} does not contain correct '$format' header row");
                $this->message->logMessage('[DEBUG]',"Import file header row: '$d0', '$d1', '$d2', '$d3', '$d4', '$d5', '$d6'");
                return "incorrect format";
            else:
                return "ok header";
            endif;
        else:
            $this->message->logMessage('[ERROR]',"Import file {$_FILES['filename']['name']} does not contain valid header row");
            $this->message->logMessage('[DEBUG]',"Import file header row: '$d0', '$d1', '$d2', '$d3', '$d4', '$d5', '$d6'");
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
    
    public function guessCsvDelimiter($filePath, $numLines = 5) 
    {
        $file = fopen($filePath, 'r');
        $delimiters = [",", "\t", ";", "|", "&"];
        $results = array_fill_keys($delimiters, 0);
        $lines = [];

        while ($numLines-- > 0 && ($line = fgets($file)) !== false):
            $lines[] = $line;
        endwhile;
        fclose($file);

        foreach ($lines as $line):
            foreach ($delimiters as $delimiter):
                $result = count(str_getcsv($line, $delimiter));
                if ($result > $results[$delimiter]):
                    $results[$delimiter] = $result;
                endif;
            endforeach;
        endforeach;

        return array_search(max($results), $results);
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
        $this->message->logMessage('[DEBUG]',"Import starting in '$importType' mode, '$importFormat' format");
        $handle = fopen($filename, "r");
        $i = 0;
        $count = 0;
        $total = 0;
        $warningsummary = '';
        $warningheading = 'Warning type, Setcode, Row number, Setcode, Number, Import Name, Import Normal, Import Foil, Import Etched, Supplied ID, Database Name (if applicable), Database ID (if applicable)';
        if ($importFormat === 'mtgc'):
            $delimiter = ',';
        elseif ($importFormat === 'delverlens'):
            $delimiter = $this->guessCsvDelimiter($filename);
        else:
            $delimiter = ',';
        endif;
        $this->message->logMessage('[DEBUG]',"Import file delimiter is '$delimiter'");
        while (($data = fgetcsv ($handle, 100000, $delimiter)) !== FALSE):
            $idimport = 0;
            $row_no = $i + 1;
            if ($i === 0): // It's the header row, check to see if it matches the stated format
                $this->message->logMessage('[DEBUG]',"Import file header row: " . implode($delimiter, $data));
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
                
                $this->message->logMessage('[DEBUG]',"Row $row_no of import file (format: '$importFormat'): setcode({$data0}), number({$data1}), name ({$data2}), normal ({$data3}), foil ({$data4}), etched ({$data5}), id ({$data6})");
                $supplied_id = $data6; // id
                if (!is_null($data6)): // ID has been supplied, run an ID check / import first
                    $this->message->logMessage('[DEBUG]',"Row $row_no: Data has an ID ($data6), checking for a match");
                    $cardtype = cardtype_for_id($data6);
                    $this->message->logMessage('[DEBUG]',"Row $row_no: Card type is: $cardtype");
                    if($cardtype == 'nomatch'):
                        $this->message->logMessage('[DEBUG]',"Row $row_no: ID $data6 is not a valid id, trying setcode/number...");
                        $importable = FALSE;
                    elseif($cardtype == 'none'):
                        $this->message->logMessage('[DEBUG]',"Row $row_no: ID $data6 is valid but db has no cardtype info");
                        $importable = FALSE;
                    else:
                        $this->message->logMessage('[DEBUG]',"Row $row_no: ID $data6 is valid and we have cardtype info");
                        if($cardtype == 'normalfoiletched'):
                            $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Normal/Foil/Etched ID, no restrictions on card import");
                            // All options available for import, no checks to be made
                            $importable = TRUE;
                        elseif($cardtype == 'normalfoil'):
                            if($data5 > 0):
                                $this->message->logMessage('[ERROR]',"Row $row_no: Card matches to a Normal and Foil ID, but import contains Etched cards");
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
                                $this->message->logMessage('[ERROR]',"Row $row_no: Card matches to a Normal and Etched ID, but import contains Foil cards");
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
                                $this->message->logMessage('[ERROR]',"Row $row_no: Card matches to a Foil and Etched ID, but import contains Normal cards");
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
                                $this->message->logMessage('[ERROR]',"Row $row_no: Card matches to a Etched-only ID, but import contains Normal and/or Foil cards");
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
                                $this->message->logMessage('[ERROR]',"Row $row_no: Card matches to a Foil-only ID, but import contains Normal and/or Etched cards");
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
                                $this->message->logMessage('[ERROR]',"Row $row_no: Card matches to a Foil-only ID, but import contains Foil and/or Etched cards");
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
                        $this->message->logMessage('[DEBUG]',"Row $row_no: Match found for ID $data6 with no misallocated card types, will import");
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
                        $this->message->logMessage('[DEBUG]',"Row $row_no: ID $data6 has existing quantities of '{$currentValues['normal']}'/'{$currentValues['foil']}'/'{$currentValues['etched']}'");
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
                                $this->message->logMessage('[DEBUG]',"Row $row_no: New, imported - no error returned; return code: $status");
                            elseif($status === 2):
                                $this->message->logMessage('[DEBUG]',"Row $row_no: Updated - no error returned; return code: $status");
                            else:
                                $this->message->logMessage('[DEBUG]',"Row $row_no: No change - no error returned; return code: $status");
                            endif;
                        endif;
                            $stmt->close();
                        if($status === 1 OR $status === 2 OR $status === 0):
                            $this->message->logMessage('[DEBUG]',"Row $row_no: Import query ran - checking");
                            if($sqlcheckqry = $this->db->execute_query("SELECT normal,foil,etched FROM $mytable WHERE id = ? LIMIT 1",[$data6])):
                                $rowcount = $sqlcheckqry->num_rows;
                                if($rowcount > 0):
                                    $sqlcheck = $sqlcheckqry->fetch_assoc();
                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Check result = Normal: {$sqlcheck['normal']}; Foil: {$sqlcheck['foil']}; Etched: {$sqlcheck['etched']}");
                                    if (($sqlcheck['normal'] == $desiredValues['normal']) AND ($sqlcheck['foil'] == $desiredValues['foil']) AND ($sqlcheck['etched'] == $desiredValues['etched'])):
                                        $total = $total + $data3 + $data4 + $data5;
                                        $count = $count + 1;
                                        $idimport = 1;
                                    else:
                                        $this->message->logMessage('[DEBUG]',"Row $row_no: Check result = new result qties do not match desired result qties");
                                        $idimport = 20;
                                    endif;
                                else:
                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Check result = No match");
                                    $idimport = 0;
                                endif;
                            else:
                                trigger_error("[ERROR]: SQL failure: " . $this->db->error, E_USER_ERROR);
                            endif;
                        endif;
                    endif;    
                endif;
                if (!empty($data0) AND !empty($data1) AND !empty($data2) AND $idimport === 0): // ID import has not been successful, try with setcode, number, name
                    $this->message->logMessage('[DEBUG]',"Row $row_no: Data place 1 (setcode - $data0), place 2 (number - $data1) place 3 (name - $data2) without ID - trying setcode/number");
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
                                    $this->message->logMessage('[DEBUG]',"Supplied card setcode and number do not match primary db name for id {$result['id']}, checking other db names");
                                    if(!in_array($data2,$db_all_names)):
                                        $this->message->logMessage('[ERROR]',"No db name match for {$result['id']} (db names: $db_all_names[0], $db_all_names[1], $db_all_names[2], $db_all_names[3], $db_all_names[4], $db_all_names[5], $db_all_names[6], $db_all_names[7], $db_all_names[8])");
                                        echo "Row $row_no: ERROR: ID and Name not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                        echo "<img src='/images/error.png' alt='Error'><br>";
                                        $newwarning = "ERROR - name mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                        $warningsummary = $warningsummary.$newwarning;
                                        $i = $i + 1;
                                        continue;
                                    else:
                                        $importtype = 'alternate_name';
                                        $data6 = $result['id'];
                                        $this->message->logMessage('[ERROR]',"Supplied name $data2 matches with a secondary name for id {$result['id']}, will import");
                                    endif;
                                else:
                                    if(isset($result['finishes'])):
                                        $this->message->logMessage('[DEBUG]',"Card setcode and number matches on supplied name ($data2) for db id {$result['id']}, looking up finishes");
                                        $data6 = $result['id'];
                                        $finishes = json_decode($result['finishes'], TRUE);
                                        $cardtype = cardtypes($finishes);
                                        $this->message->logMessage('[DEBUG]',"Row $row_no: Card type is: $cardtype");
                                        if($cardtype != 'none'):
                                            if($cardtype == 'normalfoiletched'):
                                                $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Normal/Foil/Etched ID, no restrictions on card import");
                                            elseif($cardtype == 'normalfoil'):
                                                if($data5 > 0):
                                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Normal and Foil ID, but import contains Etched cards");
                                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif; 
                                            elseif($cardtype == 'normaletched'):
                                                if($data4 > 0):
                                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Normal and Etched ID, but import contains Foil cards");
                                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif; 
                                            elseif($cardtype == 'foiletched'):
                                                if($data3 > 0):
                                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Foil and Etched ID, but import contains Normal cards");
                                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif; 
                                            elseif($cardtype == 'etchedonly'):
                                                if($data3 > 0 or $data4 > 0):
                                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Etched-only ID, but import contains Normal and/or Foil cards");
                                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif;                                                
                                            elseif($cardtype == 'foilonly'):
                                                if($data3 > 0 or $data5 > 0):
                                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Foil-only ID, but import contains Normal and/or Etched cards");
                                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif;
                                            elseif($cardtype == 'normalonly'):
                                                if($data4 > 0 or $data5 > 0):
                                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Foil-only ID, but import contains Foil and/or Etched cards");
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
                                $this->message->logMessage('[DEBUG]',"Row $row_no: Setcode ($data0)/collector number ($data1) with supplied ID ($supplied_id) matched on name and importing as ID $data6");
                            endif;
                        else: //if ($stmt->num_rows > 0)
                            $this->message->logMessage('[ERROR]',"Card setcode and number do not match a card in db");
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
                        $this->message->logMessage('[DEBUG]',"Row $row_no: ID $data6 has existing quantities of '{$currentValues['normal']}'/'{$currentValues['foil']}'/'{$currentValues['etched']}'");
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
                                $this->message->logMessage('[DEBUG]',"Row $row_no: New, imported - no error returned; return code: $status");
                            elseif($status === 2):
                                $this->message->logMessage('[DEBUG]',"Row $row_no: Updated - no error returned; return code: $status");
                            else:
                                $this->message->logMessage('[DEBUG]',"Row $row_no: No change - no error returned; return code: $status");
                            endif;
                        endif;
                        $stmt->close();
                        if($status === 1 OR $status === 2 OR $status === 0):
                            $this->message->logMessage('[DEBUG]',"Row $row_no: Import query ran OK - checking...");
                            if($sqlcheckqry = $this->db->execute_query("SELECT normal,foil,etched FROM $mytable WHERE id = ? LIMIT 1",[$data6])):
                                $rowcount = $sqlcheckqry->num_rows;
                                if($rowcount > 0):
                                    $sqlcheck = $sqlcheckqry->fetch_assoc();
                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Check result = Normal: {$sqlcheck['normal']}; Foil: {$sqlcheck['foil']}; Etched: {$sqlcheck['etched']}");
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
                                        $this->message->logMessage('[DEBUG]',"Row $row_no: Check result = new result qties do not match desired result qties"); ?>
                                        <img src='/images/error.png' alt='Failure'><br> <?php
                                    endif;
                                else:
                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Check result = No match");
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
        $this->message->logMessage('[NOTICE]',"Import finished");
        $this->message->logMessage('[DEBUG]',"Warnings: '$warningsummary'"); ?>
        <script type="text/javascript">
            (function() {
                fetch('/valueupdate.php?table=<?php echo("$mytable"); ?>');
            })();

            alert('Import completed - a full collection value resync is being run, and can also take several minutes. Accessing your Profile page while this is running will take longer than usual.');
            window.onload=function(){document.body.style.cursor='wait';}
        </script> <?php
    }

    public function __toString() {
        $this->message->logMessage("[ERROR]","Called as string");
        return "Called as a string";
    }
}