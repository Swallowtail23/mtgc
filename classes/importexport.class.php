<?php
/* Version:     5.0
    Date:       05/07/24
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
 * 
 *  5.0         05/07/24
 *              Major import routine rewrite
 *              MTGC-100
 * 
 *  5.1         06/07/24
 *              Catch fringe import cases, and improve return notices
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

                $subject = "$this->siteTitle weekly collection export";
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
            $this->message->logMessage('[DEBUG]',"Row: $row_no: Reviewing line");
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
            ?>
            <script type="text/javascript">
                alert('WARNING\n\nNo cards in the file to import');
            </script> <?php
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
            $actionedCards = $batchOutput['total'];
            $actionedRows = $batchOutput['batchRows'];
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
        $this->message->logMessage('[NOTICE]',"Import process run with '$importType' ($actionedRows of $count card rows actioned, $actionedCards of $total cards actioned)"); 
        if($actionedCards === 0):?>
            <script type="text/javascript">
                alert('WARNING\n\nNo actions were taken, check your file\n\nEmail has been sent to you with warnings/error details');
                window.onload=function(){document.body.style.cursor='wait';}
            </script> <?php
        elseif($count === $actionedRows && $total === $actionedCards): ?>
            <script type="text/javascript">
                (function() {
                    fetch('/valueupdate.php?table=<?php echo("$mytable"); ?>');
                })();

                alert('Import type: <?php echo $importType;?>\n<?php echo $count;?> card rows found in file with <?php echo $total;?> cards\nAll card rows and cards actioned\n\nCollection value is now being resynced, this can take some time for large collections, please wait');
                window.onload=function(){document.body.style.cursor='wait';}
            </script> <?php
        else: ?>
            <script type="text/javascript">
                (function() {
                    fetch('/valueupdate.php?table=<?php echo("$mytable"); ?>');
                })();

                alert('Import type: <?php echo $importType;?>\n<?php echo $count;?> card rows found in file with <?php echo $total;?> cards\n<?php echo $actionedRows;?> card rows actioned with <?php echo $actionedCards;?> cards\n\nDetails have been emailed to you with warnings/error details\n\nCollection value is now being resynced, this can take some time for large collections, please wait');
                window.onload=function(){document.body.style.cursor='wait';}
            </script> <?php
        endif;

    }

    public function addCardsBatch($mytable, $importType, $count, $total, $batchedCardIds) {
        $this->message->logMessage('[DEBUG]',"Batch import process called with '$importType' ($count unique cards, $total total cards)");
        $values = [];
        $placeholders = [];
        $batchWarnings = '';
                
        foreach ($batchedCardIds as $key => $batchedCard):
            $line = $batchedCard['line'];
            $row_no = $batchedCard['row'];
            $id = $batchedCard['id'];
            $finishes = json_decode($batchedCard['finishes'], TRUE);
            $cardtype = cardtypes($finishes);
            $normal = $batchedCard['normal'];
            $foil = $batchedCard['foil'];
            $etched = $batchedCard['etched'];
            $qty = $normal + $foil + $etched;
            
            // Validate card types, 'continue' out of this 'foreach' if there are any issues, logging an error
            if($normal > 0 && !str_contains($cardtype, 'normal')):
                $this->message->logMessage('[ERROR]',"Row: $row_no: Batch import finish mapping error (normal) - skipping row");
                $newWarning = "$row_no, Normal qty cannot be mapped to card without normal finish - row skipped (row detail: '$line') \n";
                $batchWarnings = $batchWarnings.$newWarning;
                $total = $total - $qty;         // Deduct cards from total card count
                $count = $count - 1;            // Deduct the entire row from the row count
                unset($batchedCardIds[$key]);   // Remove this row from the batch
                continue;
            endif;
            if($foil > 0 && !str_contains($cardtype, 'foil')):
                $this->message->logMessage('[ERROR]',"Row: $row_no: Batch import finish mapping error (foil) - skipping row");
                $newWarning = "$row_no, Foil qty cannot be mapped to card without foil finish - row skipped (row detail: '$line') \n";
                $batchWarnings = $batchWarnings.$newWarning;
                $total = $total - $foil;
                $count = $count - 1;
                unset($batchedCardIds[$key]);
                continue;
            endif;
            if($etched > 0 && !str_contains($cardtype, 'etched')):
                $this->message->logMessage('[ERROR]',"Row: $row_no: Batch import finish mapping error (etched) - skipping row");
                $newWarning = "$row_no, Etched qty cannot be mapped to card without etched finish - row skipped (row detail: '$line') \n";
                $batchWarnings = $batchWarnings.$newWarning;
                $total = $total - $etched;
                $count = $count - 1;
                unset($batchedCardIds[$key]);
                continue;
            endif;
            // Add each card to the batch
            $this->message->logMessage('[DEBUG]',"Row: $row_no: Batch import - adding to batch ('$line')");
            $values[] = "($id, $normal, $foil, $etched)";
            $placeholders[] = '(?, ?, ?, ?)';
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
                return array('warnings' => $batchWarnings, 'total' => $total, 'batchRows' => $count);
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
            return array('warnings' => $batchWarnings, 'total' => $total, 'batchRows' => $count);
        endif;
    }
    
    private function deleteOrphans($mytable)
    {
        if($query = $this->db->execute_query("DELETE FROM $mytable WHERE COALESCE(normal,0) + COALESCE(foil,0) + COALESCE(etched,0) = 0")):
            $this->message->logMessage('[NOTICE]',"Deleted {$this->db->affected_rows} orphan rows");
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
        endif;
    }

    public function __toString() {
        $this->message->logMessage("[ERROR]","Called as string");
        return "Called as a string";
    }
}