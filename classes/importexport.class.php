<?php
/* Version:     4.0
    Date:       09/06/24
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
 * 
 *  4.0         09/06/24
 *              Update export and import routines for languages
 *              MTGC-87 and MTGC-89
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
    private $batchedCardIds = []; // Array to store batched cards to add
    
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
        $sql = "SELECT setcode,number_import,name,lang,normal,$table.foil,$table.etched,$table.id as scryfall_id FROM $table JOIN cards_scry ON $table.id = cards_scry.id WHERE (($table.normal > 0) OR ($table.foil > 0) OR ($table.etched > 0))";
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
    
    private function checkFormat($format,$d0,$d1,$d2,$d3,$d4,$d5,$d6,$d7)
    {
        // Check native MTG Collection import format
        if ($format === 'mtgc'):
            if ((strpos(strtolower($d0),'code') === FALSE) OR
                (strpos(strtolower($d1),'number') === FALSE) OR
                (strpos(strtolower($d2),'name') === FALSE) OR
                (strpos(strtolower($d3),'lang') === FALSE) OR
                (strpos(strtolower($d4),'normal') === FALSE) OR
                (strpos(strtolower($d5),'foil') === FALSE) OR
                (strpos(strtolower($d6),'etched') === FALSE) OR
                (strpos(strtolower($d7),'id') === FALSE)):
                $this->message->logMessage('[ERROR]',"Import file {$_FILES['filename']['name']} does not contain correct '$format' header row");
                $this->message->logMessage('[DEBUG]',"Import file header row: '$d0', '$d1', '$d2', '$d3', '$d4', '$d5', '$d6', '$d7'");
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
                $this->message->logMessage('[DEBUG]',"Import file header row: '$d0', '$d1', '$d2', '$d3', '$d4', '$d5'");
                return "incorrect format";
            else:
                return "ok header";
            endif;
            else:
            $this->message->logMessage('[ERROR]',"Import file {$_FILES['filename']['name']} does not contain valid header row");
            $this->message->logMessage('[DEBUG]',"Import file header row: '$d0', '$d1', '$d2', '$d3', '$d4', '$d5', '$d6', '$d7'");
            return "incorrect format";
        endif;
    }

    private function mapFormat($format,$d0,$d1,$d2,$d3,$d4,$d5,$d6,$d7)
    {
        $data = [];
        if ($format === 'mtgc'):
            $data[0] = $d0;
            $data[1] = $d1;
            $data[2] = trim(stripslashes($d2));
            if (!empty($d3)): // language
                $data[3] = $d3;
            else:
                $data[3] = 'unspecified';
            endif;
            if (!empty($d4)): // normal qty
                $data[4] = $d4;
            else:
                $data[4] = 0;
            endif;
            if (!empty($d5)): // foil qty
                $data[5] = $d5;
            else:
                $data[5] = 0;
            endif;
            if (!empty($d6)): // etched qty
                $data[6] = $d6;
            else:
                $data[6] = 0;
            endif;
            if (!empty($d7)): // ID
                $data[7] = $d7;
            else:
                $data[7] = null;
            endif;
        elseif ($format === 'delverlens'):
            $data[0] = $d0;
            $data[1] = $d1;
            $data[2] = trim(stripslashes($d2));
            $data[3] = 'unspecified'; // Language not specified in Delver format
            if (!empty($d3)): // normal qty
                $data[4] = $d3;
            else:
                $data[4] = 0;
            endif;

            if (!empty($d4)): // foil qty
                $data[5] = $d4;
            else:
                $data[5] = 0;
            endif;

            $data[6] = 0; // Always set etched to 0 for Delver Lens, as it does not correctly support it

            if (!empty($d5)): // ID
                $data[7] = $d5;
            else:
                $data[7] = null;
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
        $warningheading = 'Warning type, Row number, Setcode, Number, Name, Language, Import Normal, Import Foil, Import Etched, Supplied ID, Database Name (if applicable), Database ID (if applicable)';
            if ($importFormat === 'mtgc'):
                $delimiter = ',';
            elseif ($importFormat === 'delverlens'):
                $delimiter = $this->guessCsvDelimiter($filename);
            $delverLang = 'unspecified';
        else:
            $delimiter = ',';
            endif;
        $this->message->logMessage('[DEBUG]',"Import format '$importFormat', delimiter '$delimiter'");
            while (($data = fgetcsv ($handle, 100000, $delimiter)) !== FALSE):
                $idimport = 0;
                $excessNormal = $excessFoil = $excessEtched = 0;
                $row_no = $i + 1;
                if ($i === 0): // It's the header row, check to see if it matches the stated format
                    $this->message->logMessage('[DEBUG]',"Import format: $importFormat; Import file header row: " . implode($delimiter, $data));
                    $validHeader = $this->checkFormat($importFormat,$data[0],$data[1],$data[2],$data[3],$data[4],$data[5], isset($data[6]) ? $data[6] : '', isset($data[7]) ? $data[7] : '');
                    if ($validHeader !== "ok header"):
                        return "incorrect format";
                    else:

                    endif;                
                elseif(isset($data[0]) AND isset($data[1]) AND isset($data[2])):  // We have bare minimum info - a setcode, a number and a name
                    $dataMap = $this->mapFormat($importFormat,$data[0],$data[1],$data[2],$data[3],$data[4],$data[5],isset($data[6]) ? $data[6] : '',isset($data[7]) ? $data[7] : '');
                    $data0 = $dataMap[0];
                    $data1 = $dataMap[1];
                    $data2 = $dataMap[2];
                    $data3 = $dataMap[3];
                    $data4 = $dataMap[4];
                    $data5 = $dataMap[5];
                    $data6 = $dataMap[6];
                    $data7 = $dataMap[7];

                    $this->message->logMessage('[DEBUG]',"Row $row_no: Format: '$importFormat': setcode({$data0}), number({$data1}), name ({$data2}), lang ({$data3}), normal ({$data4}), foil ({$data5}), etched ({$data6}), id ({$data7})");
                    $supplied_id = $data7; // id
                    if (!is_null($data7)): // ID has been supplied, run an ID check / import first
                        $this->message->logMessage('[DEBUG]',"Row $row_no: Data has an ID ($data7), checking for a match");
                        $cardtype = cardtype_for_id($data7);
                        $this->message->logMessage('[DEBUG]',"Row $row_no: Card type is: $cardtype");
                        if($cardtype == 'nomatch'):
                            $this->message->logMessage('[DEBUG]',"Row $row_no: ID $data7 is not a valid id, trying setcode/number...");
                            $importable = FALSE;
                        elseif($cardtype == 'none'):
                            $this->message->logMessage('[DEBUG]',"Row $row_no: ID $data7 is valid but db has no cardtype info");
                            $importable = FALSE;
                        else:
                            $this->message->logMessage('[DEBUG]',"Row $row_no: ID $data7 is valid and we have cardtype info");
                            if($cardtype == 'normalfoiletched'):
                                $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Normal/Foil/Etched ID, no restrictions on card import");
                                // All options available for import, no checks to be made
                                $importable = TRUE;
                            elseif($cardtype == 'normalfoil'):
                                if($data6 > 0):
                                    $this->message->logMessage('[ERROR]',"Row $row_no: Card matches to a Normal and Foil ID, but import contains Etched cards");
                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7"."\n";
                                    $warningsummary = $warningsummary.$newwarning;
                                    $i = $i + 1;
                                    continue;
                                else:
                                    $importable = TRUE;
                                endif; 
                            elseif($cardtype == 'normaletched'):
                                if($data5 > 0):
                                    $this->message->logMessage('[ERROR]',"Row $row_no: Card matches to a Normal and Etched ID, but import contains Foil cards");
                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7"."\n";
                                    $warningsummary = $warningsummary.$newwarning;
                                    $i = $i + 1;
                                    continue;
                                else:
                                    $importable = TRUE;
                                endif; 
                            elseif($cardtype == 'foiletched'):
                                if($data4 > 0):
                                    $this->message->logMessage('[ERROR]',"Row $row_no: Card matches to a Foil and Etched ID, but import contains Normal cards");
                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7"."\n";
                                    $warningsummary = $warningsummary.$newwarning;
                                    $i = $i + 1;
                                    continue;
                                else:
                                    $importable = TRUE;
                                endif; 
                            elseif($cardtype == 'etchedonly'):
                                if($data4 > 0 or $data5 > 0):
                                    $this->message->logMessage('[ERROR]',"Row $row_no: Card matches to a Etched-only ID, but import contains Normal and/or Foil cards");
                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7"."\n";
                                    $warningsummary = $warningsummary.$newwarning;
                                    $i = $i + 1;
                                    continue;
                                else:
                                    $importable = TRUE;
                                endif;                                                
                            elseif($cardtype == 'foilonly'):
                                if($data4 > 0 or $data6 > 0):
                                    $this->message->logMessage('[ERROR]',"Row $row_no: Card matches to a Foil-only ID, but import contains Normal and/or Etched cards");
                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7"."\n";
                                    $warningsummary = $warningsummary.$newwarning;
                                    $i = $i + 1;
                                    continue;
                                else:
                                    $importable = TRUE;
                                endif;
                            elseif($cardtype == 'normalonly'):
                                if($data5 > 0 or $data6 > 0):
                                    $this->message->logMessage('[ERROR]',"Row $row_no: Card matches to a Foil-only ID, but import contains Foil and/or Etched cards");
                                    echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                    $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7"."\n";
                                    $warningsummary = $warningsummary.$newwarning;
                                    $i = $i + 1;
                                    continue;
                                else:
                                    $importable = TRUE;
                                endif;
                            endif;
                        endif;
                        if(isset($importable) AND $importable != FALSE):
                            $this->message->logMessage('[DEBUG]',"Row $row_no: Match found for ID $data7 with no misallocated card types, will import");
                            // Get existing values
                            $beforeStmt = $this->db->prepare("SELECT normal, foil, etched FROM `$mytable` WHERE id = ? LIMIT 1");
                            $beforeStmt->bind_param("s", $data7);
                            $beforeStmt->execute();
                            $result = $beforeStmt->get_result();
                            if ($result->num_rows > 0):
                                $currentValues = $result->fetch_assoc();
                                if($currentValues['normal'] === '' || $currentValues['normal'] === null):
                                    $currentValues['normal'] = 0;
                                endif;
                                if($currentValues['foil'] === '' || $currentValues['foil'] === null):
                                    $currentValues['foil'] = 0;
                                endif;
                                if($currentValues['etched'] === '' || $currentValues['etched'] === null):
                                    $currentValues['etched'] = 0;
                                endif;
                            else:
                                $currentValues = ['normal' => 0, 'foil' => 0, 'etched' => 0];
                            endif;
                            $this->message->logMessage('[DEBUG]',"Row $row_no: ID $data7 has existing quantities of '{$currentValues['normal']}'/'{$currentValues['foil']}'/'{$currentValues['etched']}'");
                            $this->message->logMessage('[DEBUG]',"Row $row_no: Running '$importType' import");
                            if ($importType === 'add'):
                                $desiredValues = ['normal' => $currentValues['normal'] + $data4, 'foil' => $currentValues['foil'] + $data5, 'etched' => $currentValues['etched'] + $data6];
                                $desiredValuesString = implode(',',$desiredValues);
                            elseif ($importType === 'replace'):
                                $desiredValues = ['normal' => $data4, 'foil' => $data5, 'etched' => $data6];
                                $desiredValuesString = implode(',',$desiredValues);
                            elseif ($importType === 'subtract'):
                                if($data4 > $currentValues['normal']):
                                    $excessNormal = $data4 - $currentValues['normal'];
                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Can't subtract $data4 cards from {$currentValues['normal']} Normal cards");
                                    $newwarning = "ERROR - can't subtract Normal, $row_no, $data0, $data1, $data2, (setting {$currentValues['normal']} to 0 - excess: $excessNormal), N/A, N/A, $data7, N/A, N/A"."\n";
                                    $warningsummary = $warningsummary.$newwarning;
                                endif;
                                if($data5 > $currentValues['foil']):
                                    $excessFoil = $data5 - $currentValues['foil'];
                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Can't subtract $data5 cards from {$currentValues['foil']} Foil cards");
                                    $newwarning = "ERROR - can't subtract Foil, $row_no, $data0, $data1, $data2, N/A, (setting {$currentValues['foil']} to 0 - excess: $excessFoil), N/A, $data7, N/A, N/A"."\n";
                                    $warningsummary = $warningsummary.$newwarning;
                                endif;
                                if($data5 > $currentValues['etched']):
                                    $excessEtched = $data6 - $currentValues['etched'];
                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Can't subtract $data6 cards from {$currentValues['etched']} Etched cards");
                                    $newwarning = "ERROR - can't subtract Etched, $row_no, $data0, $data1, $data2, N/A, N/A, (setting {$currentValues['etched']} to 0 - excess: $excessEtched), $data7, N/A, N/A"."\n";
                                    $warningsummary = $warningsummary.$newwarning;
                                endif;
                                $desiredValues = ['normal' => max(0,$currentValues['normal'] - $data4), 'foil' => max(0,$currentValues['foil'] - $data5), 'etched' => max(0,$currentValues['etched'] - $data6)];
                                $desiredValuesString = implode(',',$desiredValues);
                            else:
                                trigger_error('[ERROR] profile.php: Invalid ImportType', E_USER_ERROR);
                            endif;
                            $stmt = $this->db->prepare("INSERT INTO `$mytable` (id,normal,foil,etched)
                                    VALUES
                                        (?,?,?,?)
                                    ON DUPLICATE KEY UPDATE
                                        normal = ?,
                                        foil   = ?,
                                        etched = ?
                                ");
                            $this->message->logMessage('[DEBUG]',"Row $row_no: $importType request: desired outcome: $desiredValuesString for $data7");
                            $bind = $stmt->bind_param("sssssss",
                                            $data7,
                                            $desiredValues['normal'],
                                            $desiredValues['foil'],
                                            $desiredValues['etched'],
                                            $desiredValues['normal'],
                                            $desiredValues['foil'],
                                            $desiredValues['etched']
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
                                $this->message->logMessage('[DEBUG]',"Row $row_no: Import query has been run - checking");
                                if($sqlcheckqry = $this->db->execute_query("SELECT normal,foil,etched FROM $mytable WHERE id = ? LIMIT 1",[$data7])):
                                    $rowcount = $sqlcheckqry->num_rows;
                                    if($rowcount > 0):
                                        $sqlcheck = $sqlcheckqry->fetch_assoc();
                                        $this->message->logMessage('[DEBUG]',"Row $row_no: Check result = Normal: {$sqlcheck['normal']}; Foil: {$sqlcheck['foil']}; Etched: {$sqlcheck['etched']}");
                                        if (($sqlcheck['normal'] == $desiredValues['normal']) AND ($sqlcheck['foil'] == $desiredValues['foil']) AND ($sqlcheck['etched'] == $desiredValues['etched'])):
                                            $this->message->logMessage('[DEBUG]',"Row $row_no: Check result = OK, new result qties match desired result qties");
                                            $total = $total + $data4 + $data5 + $data6 - ($excessNormal + $excessFoil + $excessEtched);
                                            $count = $count + 1;
                                            $idimport = 1;
                                        else:
                                            $this->message->logMessage('[DEBUG]',"Row $row_no: Check result = FAIL, new result qties do not match desired result qties");
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

                    // ID import has not been successful, try with setcode, number, name

                    if (!empty($data0) AND !empty($data1) AND !empty($data2) AND !empty($data3) AND $idimport === 0): 
                        $this->message->logMessage('[DEBUG]',"Row $row_no: Data place 1 (setcode - $data0), place 2 (number - $data1) place 3 (name - $data2) without ID - trying setcode/number/lang");
                        if ($data3 === 'unspecified'):
                            $this->message->logMessage('[DEBUG]',"Row $row_no: Import language not specified, searching for primary language match");
                            $stmt = $this->db->execute_query("SELECT id,name,printed_name,flavor_name,f1_name,f1_printed_name,f1_flavor_name,f2_name,f2_printed_name,f2_flavor_name,finishes FROM cards_scry WHERE setcode = ? AND number_import = ? AND primary_card = 1 LIMIT 1", [$data0,$data1]);
                        else:
                            $this->message->logMessage('[DEBUG]',"Row $row_no: Import language specified ('$data3'), searching for a match");
                            $stmt = $this->db->execute_query("SELECT id,name,printed_name,flavor_name,f1_name,f1_printed_name,f1_flavor_name,f2_name,f2_printed_name,f2_flavor_name,finishes FROM cards_scry WHERE setcode = ? AND number_import = ? AND lang = ? LIMIT 1", [$data0,$data1,$data3]);
                        endif;

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
                                        $this->message->logMessage('[DEBUG]',"Row $row_no: Supplied card setcode and number do not match primary db name for id {$result['id']}, checking other db names");
                                        if(!in_array($data2,$db_all_names)):
                                            $this->message->logMessage('[ERROR]',"Row $row_no: No db name match for {$result['id']} (db names: $db_all_names[0], $db_all_names[1], $db_all_names[2], $db_all_names[3], $db_all_names[4], $db_all_names[5], $db_all_names[6], $db_all_names[7], $db_all_names[8])");
                                            echo "Row $row_no: ERROR: ID and Name not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                            echo "<img src='/images/error.png' alt='Error'><br>";
                                            $newwarning = "ERROR - name mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7, $db_name, $db_id \n";
                                            $warningsummary = $warningsummary.$newwarning;
                                            $i = $i + 1;
                                            continue;
                                        else:
                                            $importtype = 'alternate_name';
                                            $data7 = $result['id'];
                                            $this->message->logMessage('[ERROR]',"Row $row_no: Supplied name $data2 matches with a secondary name for id {$result['id']}, will import");
                                        endif;
                                    else:
                                        if(isset($result['finishes'])):
                                            $this->message->logMessage('[DEBUG]',"Row $row_no: Card setcode and number matches on supplied name ($data2) for db id {$result['id']}, looking up finishes");
                                            $data7 = $result['id'];
                                            $finishes = json_decode($result['finishes'], TRUE);
                                            $cardtype = cardtypes($finishes);
                                            $this->message->logMessage('[DEBUG]',"Row $row_no: Card type is: $cardtype");
                                            if($cardtype != 'none'):
                                                if($cardtype == 'normalfoiletched'):
                                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Normal/Foil/Etched ID, no restrictions on card import");
                                                elseif($cardtype == 'normalfoil'):
                                                    if($data6 > 0):
                                                        $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Normal and Foil ID, but import contains Etched cards");
                                                        echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                                        echo "<img src='/images/error.png' alt='Error'><br>";
                                                        $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7, $db_name, $db_id \n";
                                                        $warningsummary = $warningsummary.$newwarning;
                                                        $i = $i + 1;
                                                        continue;
                                                    endif; 
                                                elseif($cardtype == 'normaletched'):
                                                    if($data5 > 0):
                                                        $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Normal and Etched ID, but import contains Foil cards");
                                                        echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                                        echo "<img src='/images/error.png' alt='Error'><br>";
                                                        $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7, $db_name, $db_id \n";
                                                        $warningsummary = $warningsummary.$newwarning;
                                                        $i = $i + 1;
                                                        continue;
                                                    endif; 
                                                elseif($cardtype == 'foiletched'):
                                                    if($data4 > 0):
                                                        $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Foil and Etched ID, but import contains Normal cards");
                                                        echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                                        echo "<img src='/images/error.png' alt='Error'><br>";
                                                        $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7, $db_name, $db_id \n";
                                                        $warningsummary = $warningsummary.$newwarning;
                                                        $i = $i + 1;
                                                        continue;
                                                    endif; 
                                                elseif($cardtype == 'etchedonly'):
                                                    if($data4 > 0 or $data5 > 0):
                                                        $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Etched-only ID, but import contains Normal and/or Foil cards");
                                                        echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                                        echo "<img src='/images/error.png' alt='Error'><br>";
                                                        $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7, $db_name, $db_id \n";
                                                        $warningsummary = $warningsummary.$newwarning;
                                                        $i = $i + 1;
                                                        continue;
                                                    endif;                                                
                                                elseif($cardtype == 'foilonly'):
                                                    if($data4 > 0 or $data6 > 0):
                                                        $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Foil-only ID, but import contains Normal and/or Etched cards");
                                                        echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                                        echo "<img src='/images/error.png' alt='Error'><br>";
                                                        $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7, $db_name, $db_id \n";
                                                        $warningsummary = $warningsummary.$newwarning;
                                                        $i = $i + 1;
                                                        continue;
                                                    endif;
                                                elseif($cardtype == 'normalonly'):
                                                    if($data5 > 0 or $data6 > 0):
                                                        $this->message->logMessage('[DEBUG]',"Row $row_no: Card matches to a Foil-only ID, but import contains Foil and/or Etched cards");
                                                        echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                                        echo "<img src='/images/error.png' alt='Error'><br>";
                                                        $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7, $db_name, $db_id \n";
                                                        $warningsummary = $warningsummary.$newwarning;
                                                        $i = $i + 1;
                                                        continue;
                                                    endif;
                                                endif;
                                            endif;    
                                        endif; 
                                    endif;
                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Setcode ($data0)/collector number ($data1) with supplied ID ($supplied_id) matched on name and importing as ID $data7");
                                endif;
                            else: //if ($stmt->num_rows > 0)
                                $this->message->logMessage('[ERROR]',"Card setcode and number do not match a card in db");
                                echo "Row $row_no: ERROR: ID and name not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) ";
                                echo "<img src='/images/error.png' alt='Error'><br>";
                                $newwarning = "ERROR - failed to find an ID and name match, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7, N/A, N/A \n";
                                $warningsummary = $warningsummary.$newwarning;
                                $i = $i + 1;
                                continue;
                            endif;
                        endif;    

                        if (!empty($data7)): //write the import
                            // Get existing values
                            $beforeStmt = $this->db->prepare("SELECT normal, foil, etched FROM `$mytable` WHERE id = ? LIMIT 1");
                            $beforeStmt->bind_param("s", $data7);
                            $beforeStmt->execute();
                            $result = $beforeStmt->get_result();
                            if ($result->num_rows > 0):
                                $currentValues = $result->fetch_assoc();
                                if($currentValues['normal'] === '' || $currentValues['normal'] === null):
                                    $currentValues['normal'] = 0;
                                endif;
                                if($currentValues['foil'] === '' || $currentValues['foil'] === null):
                                    $currentValues['foil'] = 0;
                                endif;
                                if($currentValues['etched'] === '' || $currentValues['etched'] === null):
                                    $currentValues['etched'] = 0;
                                endif;
                            else:
                                $currentValues = ['normal' => 0, 'foil' => 0, 'etched' => 0];
                            endif;
                            $this->message->logMessage('[DEBUG]',"Row $row_no: ID $data7 has existing quantities of '{$currentValues['normal']}'/'{$currentValues['foil']}'/'{$currentValues['etched']}'");
                            $this->message->logMessage('[DEBUG]',"Row $row_no: Running '$importType' import");
                            if ($importType === 'add'):
                                $desiredValues = ['normal' => $currentValues['normal'] + $data4, 'foil' => $currentValues['foil'] + $data5, 'etched' => $currentValues['etched'] + $data6];
                                $desiredValuesString = implode(',',$desiredValues);
                            elseif ($importType === 'replace'):
                                $desiredValues = ['normal' => $data4, 'foil' => $data5, 'etched' => $data6];
                                $desiredValuesString = implode(',',$desiredValues);
                            elseif ($importType === 'subtract'):
                                if($data4 > $currentValues['normal']):
                                    $excessNormal = $data4 - $currentValues['normal'];
                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Can't subtract $data4 cards from {$currentValues['normal']} Normal cards");
                                    $newwarning = "ERROR - can't subtract Foil, $row_no, $data0, $data1, $data2, (setting {$currentValues['normal']} to 0 - excess: $excessNormal), N/A, N/A, $data7, $db_name, $db_id"."\n";
                                    $warningsummary = $warningsummary.$newwarning;
                                endif;
                                if($data5 > $currentValues['foil']):
                                    $excessFoil = $data5 - $currentValues['foil'];
                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Can't subtract $data5 cards from {$currentValues['foil']} Foil cards");
                                    $newwarning = "ERROR - can't subtract Foil, $row_no, $data0, $data1, $data2, N/A, (setting {$currentValues['foil']} to 0 - excess: $excessFoil), N/A, $data7, $db_name, $db_id"."\n";
                                    $warningsummary = $warningsummary.$newwarning;
                                endif;
                                if($data6 > $currentValues['etched']):
                                    $excessEtched = $data6 - $currentValues['etched'];
                                    $this->message->logMessage('[DEBUG]',"Row $row_no: Can't subtract $data6 cards from {$currentValues['etched']} Etched cards");
                                    $newwarning = "ERROR - can't subtract Etched, $row_no, $data0, $data1, $data2, N/A, N/A, (setting {$currentValues['etched']} to 0 - excess: $excessEtched), $data7, $db_name, $db_id"."\n";
                                    $warningsummary = $warningsummary.$newwarning;
                                endif;
                                $desiredValues = ['normal' => max(0,$currentValues['normal'] - $data4), 'foil' => max(0,$currentValues['foil'] - $data5), 'etched' => max(0,$currentValues['etched'] - $data6)];
                                $desiredValuesString = implode(',',$desiredValues);
                            else:
                                trigger_error('[ERROR] profile.php: Invalid ImportType', E_USER_ERROR);
                            endif;
                            $stmt = $this->db->prepare("  INSERT INTO
                                        `$mytable`
                                        (id,normal,foil,etched)
                                    VALUES
                                        (?,?,?,?)
                                    ON DUPLICATE KEY UPDATE
                                        normal = ?,
                                        foil   = ?,
                                        etched = ?
                                ");
                            $this->message->logMessage('[DEBUG]',"Row $row_no: $importType request: desired outcome: $desiredValuesString for $data7");
                            $bind = $stmt->bind_param("sssssss",
                                            $data7,
                                            $desiredValues['normal'],
                                            $desiredValues['foil'],
                                            $desiredValues['etched'],
                                            $desiredValues['normal'],
                                            $desiredValues['foil'],
                                            $desiredValues['etched']
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
                                if($sqlcheckqry = $this->db->execute_query("SELECT normal,foil,etched FROM $mytable WHERE id = ? LIMIT 1",[$data7])):
                                    $rowcount = $sqlcheckqry->num_rows;
                                    if($rowcount > 0):
                                        $sqlcheck = $sqlcheckqry->fetch_assoc();
                                        $this->message->logMessage('[DEBUG]',"Row $row_no: Check result = Normal: {$sqlcheck['normal']}; Foil: {$sqlcheck['foil']}; Etched: {$sqlcheck['etched']}");
                                        if (($sqlcheck['normal'] == $desiredValues['normal']) AND ($sqlcheck['foil'] == $desiredValues['foil']) AND ($sqlcheck['etched'] == $desiredValues['etched'])):
                                            $this->message->logMessage('[DEBUG]',"Row $row_no: Check result = OK, new result qties match desired result qties");
                                            if(isset($importtype) AND $importtype == 'alternate_name'):
                                                $newwarning = "WARNING - card matched to alternate card name, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $supplied_id, $db_name, $db_id \n";
                                                $warningsummary = $warningsummary.$newwarning;
                                            else:
                                                // echo "Row $row_no: NORMAL: Setcode/number matched, successful import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7) <img src='/images/success.png' alt='Success'><br>";
                                            endif;
                                            $total = $total + $data4 + $data5 + $data6 - ($excessNormal + $excessFoil + $excessEtched);
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
                        $newwarning = "ERROR - not enough data to identify card, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $data7, N/A, N/A \n";
                        $warningsummary = $warningsummary.$newwarning;
                    endif;
                endif;
                $i = $i + 1;
            endwhile;
        // Remove any orphan rows left after removals
        $this->deleteOrphans($mytable);
        
        fclose($handle);
        $summary = "Import done - $count unique cards, $importType total: $total.";
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
    
    private function deleteOrphans($mytable)
    {
        if($query = $this->db->execute_query("DELETE FROM $mytable WHERE COALESCE(normal,0) + COALESCE(foil,0) + COALESCE(etched,0) = 0")):
            $this->message->logMessage('[NOTICE]',"Deleted {$this->db->affected_rows} orphan rows");
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
        endif;
    }

    public function importCollectionRegex($filename, $mytable, $importType, $useremail, $serveremail) 
    {
        // Import type = add, replace or remove
        // Import format = 'regex'
        // 'regex' may have no header row, and content like '1 All Is Dust [M3C 152]' or any other style that input_interpreter() can assess
        $importFormat = 'regex';
        $this->message->logMessage('[DEBUG]',"Import starting in '$importType' mode, '$importFormat' format");

        $handle = fopen($filename, "r");
        $fileContent = fread($handle,filesize($filename));
        $i = 0;
        $count = 0;
        $total = 0;
        $warningsummary = '';
        $lines = explode("\n", $fileContent);
        $qtyLines = count($lines);
        $this->message->logMessage('[DEBUG]',"Regex deck import has $qtyLines lines");

        foreach ($lines as $line):
            $row_no = $i + 1;
            $this->message->logMessage('[DEBUG]',"Reviewing line $row_no");
            $linestring = htmlspecialchars($line,ENT_NOQUOTES);
            $interpreted_string = input_interpreter($linestring);
            if($interpreted_string === 'header'):
                $this->message->logMessage('[DEBUG]',"Row: $row_no: Header row");
            elseif($interpreted_string === 'empty line'):
                $this->message->logMessage('[DEBUG]',"Row: $row_no: Empty row");
            elseif($interpreted_string === false || (empty($interpreted_string['uuid']) && (empty($interpreted_string['set']) || empty($interpreted_string['number'])))):
                $this->message->logMessage('[DEBUG]',"Row: $row_no: Not enough usable card info (or empty row)");
                $newwarning = "$row_no, Not enough info to identify card (row detail: '$line') \n";
                $warningsummary = $warningsummary.$newwarning;
            else:
                $this->message->logMessage('[DEBUG]',"Row: $row_no: Possible card");
                $count = $count + 1; //Increment unique card row count

                // UUID
                if (isset($interpreted_string['uuid']) AND $interpreted_string['uuid'] !== ''):
                    $quickaddUUID = $interpreted_string['uuid'];
                else:
                    $quickaddUUID = '';
                endif;
                
                // Quantity
                if (isset($interpreted_string['normal']) AND $interpreted_string['normal'] !== ''):
                    $quickaddnormal = $interpreted_string['normal'];
                else:
                    $quickaddnormal = 0;
                endif;
                if (isset($interpreted_string['foil']) AND $interpreted_string['foil'] !== ''):
                    $quickaddfoil = $interpreted_string['foil'];
                else:
                    $quickaddfoil = 0;
                endif;
                if (isset($interpreted_string['etched']) AND $interpreted_string['etched'] !== ''):
                    $quickaddetched = $interpreted_string['etched'];
                else:
                    $quickaddetched = 0;
                endif;
                
                // Name
                if (isset($interpreted_string['name']) AND $interpreted_string['name'] !== ''):
                    $quickaddcard = $interpreted_string['name'];
                else:
                    $quickaddcard = '';
                endif;
                
                // Set
                if (isset($interpreted_string['set']) AND $interpreted_string['set'] !== ''):
                    $quickaddset = strtoupper($interpreted_string['set']);
                else:
                    $quickaddset = '';
                endif;
                
                // Lang
                if (isset($interpreted_string['lang']) AND $interpreted_string['lang'] !== ''):
                    $quickaddlang = strtoupper($interpreted_string['lang']);
                else:
                    $quickaddlang = '';
                endif;
                
                // Collector number
                if (isset($interpreted_string['number']) AND $interpreted_string['number'] !== ''):
                    $quickaddNumber = $interpreted_string['number'];
                else:
                    $quickaddNumber = '';
                endif;
                
                $quickaddcard = htmlspecialchars_decode($quickaddcard,ENT_QUOTES);
                $this->message->logMessage('[DEBUG]',
                          "Row: $row_no: Quick add interpreted as: "
                        . "Normal: [$quickaddnormal] "
                        . "Foil: [$quickaddfoil] "
                        . "Etched: [$quickaddetched] "
                        . " x Card: [$quickaddcard] Set: [$quickaddset] "
                        . "Collector number: [$quickaddNumber] Language: [$quickaddlang] UUID: [$quickaddUUID]");
                $stmt = null;

                if ($quickaddUUID !== '' && valid_uuid($quickaddUUID) !== false):
                    // Card UUID provided and valid UUID
                    $this->message->logMessage('[DEBUG]',"Row: $row_no: Quick add proceeding with provided UUID: [$quickaddUUID]");
                    $query = "SELECT id,finishes,name,setcode,number FROM cards_scry WHERE id = ? LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = [$quickaddUUID];
                    $stmt->bind_param('s', $params[0]);

                elseif ($quickaddcard !== '' AND $quickaddset !== '' AND $quickaddNumber !== '' AND $quickaddlang !== ''):
                    // Card name, setcode, and collector number provided
                    $this->message->logMessage('[DEBUG]',"Row: $row_no: Quick add proceeding with provided name, set, number and specified language");
                    $query = "SELECT id,finishes FROM cards_scry WHERE (name = ? OR
                                                               f1_name = ? OR 
                                                               f2_name = ? OR 
                                                               printed_name = ? OR 
                                                               f1_printed_name = ? OR 
                                                               f2_printed_name = ? OR 
                                                               flavor_name = ? OR
                                                               f1_flavor_name = ? OR 
                                                               f2_flavor_name = ?) AND 
                                                               setcode = ? AND number_import = ? AND 
                                                               lang LIKE ? 
                                                               ORDER BY release_date DESC LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = array_fill(0, 9, $quickaddcard);
                    array_push($params, $quickaddset, $quickaddNumber, $quickaddlang);
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);

                elseif ($quickaddcard !== '' AND $quickaddset !== '' AND $quickaddNumber !== ''):
                    // Card name, setcode, and collector number provided
                    $this->message->logMessage('[DEBUG]',"Row: $row_no: Quick add proceeding with provided name, set, number and primary language");
                    $query = "SELECT id,finishes FROM cards_scry WHERE (name = ? OR
                                                               f1_name = ? OR 
                                                               f2_name = ? OR 
                                                               printed_name = ? OR 
                                                               f1_printed_name = ? OR 
                                                               f2_printed_name = ? OR 
                                                               flavor_name = ? OR
                                                               f1_flavor_name = ? OR 
                                                               f2_flavor_name = ?) AND 
                                                               setcode = ? AND number_import = ? AND
                                                               primary_card = 1
                                                               ORDER BY release_date DESC LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = array_fill(0, 9, $quickaddcard);
                    array_push($params, $quickaddset, $quickaddNumber);
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);

                elseif ($quickaddcard !== '' AND $quickaddset !== '' AND $quickaddNumber === ''):
                    // Card name and setcode provided
                    $this->message->logMessage('[DEBUG]',"Row: $row_no: Quick add proceeding with provided name, set");
                    $query = "SELECT id,finishes FROM cards_scry WHERE (name = ? OR
                                                               f1_name = ? OR 
                                                               f2_name = ? OR 
                                                               printed_name = ? OR 
                                                               f1_printed_name = ? OR 
                                                               f2_printed_name = ? OR 
                                                               flavor_name = ? OR
                                                               f1_flavor_name = ? OR 
                                                               f2_flavor_name = ?) AND 
                                                               setcode = ? AND
                                                               primary_card = 1
                                                               ORDER BY release_date DESC, number ASC LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = array_fill(0, 9, $quickaddcard);
                    array_push($params, $quickaddset);
                    $params = array_merge($params, $noQuickAddLayouts);
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);

                elseif ($quickaddcard === '' AND $quickaddset !== '' AND $quickaddNumber !== ''):
                    // Card name not provided, setcode, and collector number provided
                    $this->message->logMessage('[DEBUG]',"Row: $row_no: Quick add proceeding with provided set and number");
                    $query = "SELECT id,finishes FROM cards_scry WHERE
                                                            setcode = ? AND 
                                                            number_import = ? AND
                                                            primary_card = 1
                                                            ORDER BY release_date DESC LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = [$quickaddset, $quickaddNumber];
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);

                else:
                    // Not enough info, cannot add
                    $this->message->logMessage('[NOTICE]',"Row: $row_no: Quick add - Not enough info to identify a card to add");
                    $cardtoadd = 'cardnotfound';
                    $newwarning = "$row_no, Not enough info to identify card (row detail: '$line') \n";
                    $warningsummary = $warningsummary.$newwarning;
                endif;
                if ($stmt !== null AND $stmt->execute()):
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0):
                        $row = $result->fetch_assoc();
                        $stmt->close();
                        $cardtoadd = $row['id'];
                        $finishes = $row['finishes'];
                        $this->message->logMessage('[DEBUG]',"Row: $row_no: Quick add result: UUID result is '$cardtoadd', adding to batch");
                        $this->batchedCardIds[] = ['line' => $line, 'row' => $row_no, 'id' => $cardtoadd, 'finishes' => $finishes, 'normal' => $quickaddnormal, 'foil' => $quickaddfoil, 'etched' => $quickaddetched];
                        $total = $total + $quickaddnormal + $quickaddfoil + $quickaddetched;
                    else:
                        $stmt->close();
                        $this->message->logMessage('[NOTICE]',"Row: $row_no: Quick add - Card not found");
                        $cardtoadd = 'cardnotfound';
                        $newwarning = "$row_no, Card not found (row detail: '$line') \n";
                        $warningsummary = $warningsummary.$newwarning;
                    endif;
                else:
                    $stmt->close();
                    $this->message->logMessage('[ERROR]',"Quick add - SQL error: " . $stmt->error);
                    $cardtoadd = 'cardnotfound';
                    $newwarning = "$row_no, Unknown error (row detail: '$line') \n";
                    $warningsummary = $warningsummary.$newwarning;
                endif;
            endif;
            $i = $i + 1;
        endforeach;
        if($count === 0):
            $this->message->logMessage('[DEBUG]',"No cards in the file to import");
            fclose($handle);
            return 'emptyfile';
        endif;
        
        // Finalise any warnings from file scan phase
        if ($warningsummary === ''):
            $warningsummary = "Input file scan warnings or errors\n\nNone\n\n";
        else:
            $warningsummary = "Input file scan warnings or errors (Row number, Warning/error)\n\n".$warningsummary;
        endif;
        
        // If batched card array is not empty, perform batch insert
        if (!empty($this->batchedCardIds)):
            $batchOutput = $this->addCardsBatch($mytable, $importType, $count, $total, $this->batchedCardIds);
            if($batchOutput['warnings'] !== 'none'):
                $warningsummary = $warningsummary.$batchOutput['warnings'];
            else:
                //
            endif;
            $total = $batchOutput['total'];
            // Clear array after batch insert
            $this->batchedCardIds = [];
        endif;
        // Remove any orphan rows left after removals
        $this->deleteOrphans($mytable);
        
        fclose($handle);
        $summary = "Import done - $count unique cards, $importType total: $total.";
        print $summary;
        $from = "From: $serveremail\r\nReturn-path: $serveremail"; 
        $subject = "Import failures / warnings"; 
        $message = "$warningsummary \n \n$summary";
        mail($useremail, $subject, $message, $from); 
        $this->message->logMessage('[NOTICE]',"Import process called with '$importType' ($count unique cards, $total total cards)"); ?>
        <script type="text/javascript">
            (function() {
                fetch('/valueupdate.php?table=<?php echo("$mytable"); ?>');
            })();

            alert('Import completed - a full collection value resync is being run, and can also take several minutes. Accessing your Profile page while this is running will take longer than usual.');
            window.onload=function(){document.body.style.cursor='wait';}
        </script> <?php
    }

    public function addCardsBatch($mytable, $importType, $count, $total, $batchedCardIds) {
        $this->message->logMessage('[DEBUG]',"Batch import process called with '$importType' ($count unique cards, $total total cards)");
        $values = [];
        $placeholders = [];
        $batchWarnings = '';

        foreach ($batchedCardIds as $batchedCard):
            $line = $batchedCard['line'];
            $row_no = $batchedCard['row'];
            $id = $batchedCard['id'];
            $finishes = json_decode($batchedCard['finishes'], TRUE);
            $cardtype = cardtypes($finishes);
            $normal = $batchedCard['normal'];
            $foil = $batchedCard['foil'];
            $etched = $batchedCard['etched'];
            
            // Validate card types
            if($normal > 0 && !str_contains($cardtype, 'normal')):
                $this->message->logMessage('[ERROR]',"Row: $row_no: Batch import finish mapping error (normal)");
                $newWarning = "$row_no, Normal qty cannot be mapped to card without normal finish (row detail: '$line') \n";
                $batchWarnings = $batchWarnings.$newWarning;
                $fail = true;
                $total = $total - $normal;
            endif;
            if($foil > 0 && !str_contains($cardtype, 'foil')):
                $this->message->logMessage('[ERROR]',"Row: $row_no: Batch import finish mapping error (foil)");
                $newWarning = "$row_no, Foil qty cannot be mapped to card without foil finish (row detail: '$line') \n";
                $batchWarnings = $batchWarnings.$newWarning;
                $fail = true;
                $total = $total - $foil;
            endif;
            if($etched > 0 && !str_contains($cardtype, 'etched')):
                $this->message->logMessage('[ERROR]',"Row: $row_no: Batch import finish mapping error (etched)");
                $newWarning = "$row_no, Etched qty cannot be mapped to card without etched finish (row detail: '$line') \n";
                $batchWarnings = $batchWarnings.$newWarning;
                $fail = true;
                $total = $total - $etched;
            endif;
            if(isset($fail) && $fail === true):
                $this->message->logMessage('[ERROR]',"Row: $row_no: Batch import - No valid finishes to add to batch, skipping ('$line')");
                continue;
            else:
                // Add each card to the batch
                $this->message->logMessage('[DEBUG]',"Row: $row_no: Batch import - adding to batch ('$line')");
                $values[] = "($id, $normal, $foil, $etched)";
                $placeholders[] = '(?, ?, ?, ?)';
            endif;
        endforeach;
        $this->message->logMessage('[DEBUG]',"Batch import warnings: '$batchWarnings'");
        if (!empty($values)):
            $this->message->logMessage('[DEBUG]',"Batch import: Assessing import type variations ($importType)");
            $placeholdersString = implode(', ', $placeholders);

            if($importType === 'add'):
                $query = "INSERT INTO $mytable (id, normal, foil, etched) VALUES $placeholdersString 
                            ON DUPLICATE KEY 
                            UPDATE 
                            normal = normal + VALUES(normal), 
                            foil = foil + VALUES(foil), 
                            etched = etched + VALUES(etched)";
            elseif($importType === 'subtract'):
                $query = "INSERT INTO $mytable (id, normal, foil, etched) VALUES $placeholdersString 
                            ON DUPLICATE KEY 
                            UPDATE 
                            normal = greatest(normal - VALUES(normal),0), 
                            foil = greatest(foil - VALUES(foil),0),  
                            etched = greatest(etched - VALUES(etched),0)";
            elseif($importType === 'replace'):
                $query = "INSERT INTO $mytable (id, normal, foil, etched) VALUES $placeholdersString 
                            ON DUPLICATE KEY 
                            UPDATE 
                            normal = VALUES(normal), 
                            foil = VALUES(foil),  
                            etched = VALUES(etched)";                
            endif;
            // Bind parameters and execute the query
            $stmt = $this->db->prepare($query);

            // Generate the type definition string dynamically based on the number of batched cards
            $typeDefinition = str_repeat('siii', count($batchedCardIds));

            // Prepare an array with the values to be bound
            $bindValues = [];
            foreach ($batchedCardIds as $batchedCard):
                $bindValues[] = $batchedCard['id'];
                $bindValues[] = $batchedCard['normal'];
                $bindValues[] = $batchedCard['foil'];
                $bindValues[] = $batchedCard['etched'];
            endforeach;

            // Bind the parameters dynamically
            $stmt->bind_param($typeDefinition, ...$bindValues);

            if ($stmt->execute()):
                $this->message->logMessage('[DEBUG]',"importCollectionRegex batch process completed");
                $stmt->close();
                if($batchWarnings === ''):
                    $batchWarnings = "\nBatch import warnings or errors\n\nNone\n\n";
                else:
                    $batchWarnings = "\nBatch import warnings or errors (Row number, Warning/error)\n\n".$batchWarnings;
                endif;
                return array('warnings' => $batchWarnings, 'total' => $total);
            else:
                $this->message->logMessage('[ERROR]',"Error executing batch insert query: ".$stmt->error);
                $stmt->close();
            endif;

            $stmt->close();
        else:
            $this->message->logMessage('[DEBUG]',"importCollectionRegex batch process completed (no writes made)");
            if($batchWarnings === ''):
                $batchWarnings = "\nBatch import warnings or errors\n\nNone\n\n";
            else:
                $batchWarnings = "\nBatch import warnings or errors (Row number, Warning/error)\n\n".$batchWarnings;
            endif;
            return array('warnings' => $batchWarnings, 'total' => $total);
        endif;
    }

    public function __toString() {
        $this->message->logMessage("[ERROR]","Called as string");
        return "Called as a string";
    }
}