<?php

/*
Version:     1.26
Date:        29/04/26
Name:        ImportExport.php
Purpose:     Import/export management class.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Cards;

use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use MTG\Core\Message;
use MTG\Core\MyPHPMailer;
use MTG\Core\Validation;

class ImportExport
{
    /**
    * @var \mysqli|object
    */
    private $db;
    private AppConfig $appConfig;
    private GameRules $gameRules;
    private string $userEmail;
    private string $serverEmail;
    private Message $message;
    private string $siteTitle;
    private bool $emailEnabled;
    /** @var array<int, array<string, mixed>> */
    private array $batchedCardIds = []; // Array to store batched cards to add

    /**
    * @param \mysqli|object $db
    */
    public function __construct($db, AppConfig $appConfig, GameRules $gameRules, string $userEmail)
    {
        $this->db = $db;
        $this->appConfig = $appConfig;
        $this->gameRules = $gameRules;
        $this->userEmail = $userEmail;
        $this->serverEmail = (string) $this->appConfig->email('serverEmail', '');
        $this->message = new Message($this->appConfig);
        $this->siteTitle = (string) $this->appConfig->general('title', '');
        $this->emailEnabled = (bool) $this->appConfig->email('enabled', false);
    }

    public function exportCollectionToCsv(
        string $table,
        string $myURL,
        string $format = 'echo',
        string $filename = 'export.csv',
        string $userName = '',
        string $userEmail = '',
        array $extraAttachments = []
    ): bool|string {
        $out = $this->buildCollectionCsv($table);
        if ($out === false) :
            return false;
        endif;

        if ($format === 'echo') :
                header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                header("Content-Length: " . strlen($out));
                header("Content-type: text/x-csv; charset=UTF-8");
                header("Content-Disposition: attachment; filename=$filename");
                echo "\xEF\xBB\xBF"; // UTF-8 BOM
                echo $out;
        elseif ($format === 'email') :
            if ($this->emailEnabled) :
                if (!empty($extraAttachments)) :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Adding " . count($extraAttachments) . " extra attachments to collection export email"
                    );
                endif;
                $mail = new MyPHPMailer(true, $this->appConfig);

                $tempFile = tempnam(sys_get_temp_dir(), 'export_');
                file_put_contents($tempFile, $out);

                $siteTitleEsc = htmlspecialchars($this->siteTitle, ENT_QUOTES, 'UTF-8');
                $subject = "Collection export";
                $emailbody = "Your $siteTitleEsc export is attached. <br><br>"
                    . "Opt out of automated emails in your profile at "
                    . "<a href='$myURL/profile.php'>your $siteTitleEsc profile page</a>";
                $emailaltbody = "Your $this->siteTitle export is attached.\r\n\r\n"
                    . "Opt out of automated emails in your profile at your $this->siteTitle profile page "
                    . "($myURL/profile.php)\r\n\r\n";
                $mailresult = $mail->sendEmail(
                    $this->userEmail,
                    true,
                    $subject,
                    $emailbody,
                    $emailaltbody,
                    $tempFile,
                    $filename,
                    $extraAttachments
                );
                if (isset($tempFile)) :
                    unlink($tempFile);
                endif;
                if ($mailresult === true) :
                    return true;
                else :
                    return $mailresult ?: false;
                endif;
            else :
                $this->message->logMessage(
                    '[NOTICE]',
                    "Email disabled; collection export email not sent to {$this->userEmail}"
                );
                return false;
            endif;
        elseif ($format === 'weekly' && $userName !== '' && $userEmail !== '') :
            if ($this->emailEnabled) :
                if (!empty($extraAttachments)) :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Adding " . count($extraAttachments) . " extra attachments to weekly export email"
                    );
                endif;
                $mail = new MyPHPMailer(true, $this->appConfig);

                $tempFile = tempnam(sys_get_temp_dir(), 'export_');
                file_put_contents($tempFile, $out);

                $siteTitleEsc = htmlspecialchars($this->siteTitle, ENT_QUOTES, 'UTF-8');
                $subject = "$this->siteTitle weekly collection export";
                $emailbody = "Hi $userName, your weekly collection export from $siteTitleEsc is attached."
                    . "<br><br>Opt out of automated emails in your profile at "
                    . "<a href='$myURL/profile.php'>your $siteTitleEsc profile page</a>";
                $emailaltbody = "Hi $userName, please see attached your weekly collection export from "
                    . "$this->siteTitle.\r\n\r\nOpt out of automated emails in your profile at your "
                    . "$this->siteTitle profile page ($myURL/profile.php)\r\n\r\n";
                $mailresult = $mail->sendEmail(
                    $userEmail,
                    true,
                    $subject,
                    $emailbody,
                    $emailaltbody,
                    $tempFile,
                    $filename,
                    $extraAttachments
                );
                if (isset($tempFile)) :
                    unlink($tempFile);
                endif;
                if ($mailresult === true) :
                    return true;
                else :
                    return $mailresult ?: false;
                endif;
            else :
                $this->message->logMessage(
                    '[NOTICE]',
                    "Email disabled; weekly collection email not sent to $userEmail"
                );
                return false;
            endif;
        endif;

        $this->message->logMessage('[ERROR]', "Unsupported collection export format '$format'");
        return false;
    }

    public function buildCollectionCsv(string $table): string|false
    {
        $csv_terminated = "\n";
        $csv_separator = ",";
        $csv_enclosed = '"';
        $csv_escaped = "\\";
        $table = $this->db->real_escape_string($table);
        $sql = "SELECT setcode,number_import,name,lang,normal,$table.foil,$table.etched,$table.id as scryfall_id
            FROM $table JOIN cards_scry ON $table.id = cards_scry.id
            WHERE (($table.normal > 0) OR ($table.foil > 0) OR ($table.etched > 0))";
        $this->message->logMessage('[NOTICE]', "Running Export Collection to CSV: $sql");

        // Gets the data from the database
        $result = $this->db->query($sql);
        if ($result === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
            return false;
        else :
            $fields = method_exists($result, 'fetch_fields') ? $result->fetch_fields() : [];
            if (empty($fields)) :
                $fields_cnt = $result->field_count;
                for ($i = 0; $i < $fields_cnt; $i++) :
                    $fields[] = mysqli_fetch_field_direct($result, $i);
                endfor;
            endif;

            $fields_cnt = count($fields);
            $this->message->logMessage('[DEBUG]', "Number of fields: $fields_cnt");
            $schema_insert = '';
            foreach ($fields as $fieldinfo) :
                $l = $csv_enclosed
                    . str_replace(
                        $csv_enclosed,
                        $csv_escaped . $csv_enclosed,
                        stripslashes($fieldinfo->name)
                    )
                    . $csv_enclosed;
                $schema_insert .= $l;
                $schema_insert .= $csv_separator;
            endforeach;

            $out = trim(substr($schema_insert, 0, -1));
            $out .= $csv_terminated;

            // Format the data
            while ($row = $result->fetch_row()) :
                $schema_insert = '';
                for ($j = 0; $j < $fields_cnt; $j++) :
                    if ($row[$j] == '0' || $row[$j] != '') :
                        if ($csv_enclosed == '') :
                            $schema_insert .= $row[$j];
                        else :
                            $schema_insert .= $csv_enclosed .
                            str_replace($csv_enclosed, $csv_escaped . $csv_enclosed, $row[$j]) . $csv_enclosed;
                        endif;
                    else :
                        $schema_insert .= '';
                    endif;
                    if ($j < $fields_cnt - 1) :
                        $schema_insert .= $csv_separator;
                    endif;
                endfor;
                $out .= $schema_insert;
                $out .= $csv_terminated;
            endwhile;
            $out .= $csv_terminated;
        endif;

        return $out;
    }

    /**
    * @return array<string, mixed>|string|false
    */
    public static function inputInterpreter(
        string $input_string,
        AppConfig $appConfig,
        GameRules $gameRules
    ): array|string|false {
        // Called by quickAdd in deckmanager class, index.php search inputs and collection imports
        // This function takes an input string, either from deck quick add or search strings,
        // and strips it into components:
        // - UUID
        // - qty (not applicable for searches)
        // - cardname
        // - set
        // - collector number
        $msg = new Message($appConfig);
        $bracketsInNames = $gameRules->get('bracketsInNames', []);
        if (!is_array($bracketsInNames)) :
            $bracketsInNames = [];
        endif;
        $importLinestoIgnore = $gameRules->get('importLinestoIgnore', []);
        if (!is_array($importLinestoIgnore)) :
            $importLinestoIgnore = [];
        endif;

        $msg->logMessage('[DEBUG]', "Input interpreter called with '$input_string'");
        $raw_string = $input_string;
        if (strncmp($raw_string, "\xEF\xBB\xBF", 3) === 0) :
            $msg->logMessage('[DEBUG]', "Input interpreter: UTF-8 BOM detected; stripping");
            $raw_string = substr($raw_string, 3);
        endif;
        $sanitised_string = htmlspecialchars($raw_string, ENT_NOQUOTES, 'UTF-8');

        // Define is_csv as a closure
        $is_csv = function ($string) use ($appConfig) {
            $msg = new Message($appConfig);
            // Check if the string contains at least 4 commas
            $comma_count = substr_count($string, ',');
            if ($comma_count < 4) :
                $msg->logMessage('[DEBUG]', "Input is not CSV");
                return false;
            endif;

            // Check if the string can be parsed into fields
            $fields = str_getcsv($string, ',', '"', '\\');

            // If str_getcsv returns an array with more than one element, it's likely a CSV
            $fieldcount = count($fields);
            $msg->logMessage('[DEBUG]', "Input is CSV, returning field count $fieldcount");
            return $fieldcount > 1;
        };

        // Define extract_and_process_csv as a closure
        $extract_and_process_csv = function ($line) use ($appConfig) {
            $msg = new Message($appConfig);

            // Parse the CSV row, with basic sanity checking on where things should be and what they should look like
            $fields = str_getcsv($line, ',', '"', '\\');
            $qtyFields = count($fields);

            // Header checks
            $mtgcHeaderKeywords = ['set', 'number', 'name'];
            $manaboxHeaderKeywords = ['name', 'set code', 'collector number', 'foil', 'quantity', 'scryfall id'];
            $isMtgcHeader = true;
            foreach ($mtgcHeaderKeywords as $keyword) :
                $found = false;
                foreach ($fields as $field) :
                    if (stripos($field, $keyword) !== false) :
                        $found = true;
                        break;
                    endif;
                endforeach;
                if (!$found) :
                    $isMtgcHeader = false;
                    break;
                endif;
            endforeach;
            if ($isMtgcHeader && ($qtyFields === 6 || $qtyFields === 8)) :
                return 'header';
            endif;

            $isManaBoxHeader = true;
            foreach ($manaboxHeaderKeywords as $keyword) :
                $found = false;
                foreach ($fields as $field) :
                    if (stripos($field, $keyword) !== false) :
                        $found = true;
                        break;
                    endif;
                endforeach;
                if (!$found) :
                    $isManaBoxHeader = false;
                    break;
                endif;
            endforeach;
            if ($isManaBoxHeader) :
                return 'header';
            endif;

            // Validate and determine CSV format
            if ($qtyFields === 6) :
                if (
                    !Validation::isValidSetcode($fields[0])
                    || !Validation::isValidCardName($fields[2])
                    || !(is_numeric($fields[3]) || empty($fields[3]))
                    || !(is_numeric($fields[4]) || empty($fields[4]))
                    || !Validation::validUUID($fields[5], $appConfig)
                ) :
                    $csvFormat = 'invalid';
                else :
                    $csvFormat = 'delver';
                endif;
            elseif ($qtyFields === 8) :
                if (
                    !Validation::isValidSetcode($fields[0])
                    || !Validation::isValidCardName($fields[2])
                    || !Validation::isValidLanguageCode($fields[3])
                    || !(is_numeric($fields[4]) || empty($fields[4]))
                    || !(is_numeric($fields[5]) || empty($fields[5]))
                    || !(is_numeric($fields[6]) || empty($fields[6]))
                    || !(Validation::validUUID($fields[7], $appConfig) || empty($fields[7]))
                ) :
                    $csvFormat = 'invalid';
                else :
                    $csvFormat = 'mtgc';
                endif;
            elseif ($qtyFields >= 15) :
                $csvFormat = 'manabox';
            else :
                $csvFormat = 'invalid';
            endif;
            $msg->logMessage('[DEBUG]', "CSV input has $qtyFields fields, format is '$csvFormat'");

            if ($csvFormat === 'invalid') :
                return false;
            endif;

            // Extracting common fields
            $set = '';
            $number = '';
            $name = '';
            $lang = '';
            $param5 = 0;
            $param6 = 0;
            $param7 = 0;
            $uuid = '';

            // Extracting other fields based on format
            if ($csvFormat === 'mtgc') :
                $set    = $fields[0];
                $number = $fields[1];
                $name   = $fields[2];
                $lang   = $fields[3];
                $param5 = isset($fields[4]) ? (int) $fields[4] : 0;
                $param6 = isset($fields[5]) ? (int) $fields[5] : 0;
                $param7 = isset($fields[6]) ? (int) $fields[6] : 0;
                $uuid   = isset($fields[7]) ? $fields[7] : '';
            elseif ($csvFormat === 'delver') : // No etched in Delver Lens files
                $set    = $fields[0];
                $number = $fields[1];
                $name   = $fields[2];
                $lang   = 'unspecified';
                $param5 = isset($fields[3]) ? (int) $fields[3] : 0;
                $param6 = isset($fields[4]) ? (int) $fields[4] : 0;
                $param7 = 0;
                $uuid   = isset($fields[5]) ? $fields[5] : '';
            elseif ($csvFormat === 'manabox') :
                $name = isset($fields[0]) ? trim($fields[0]) : '';
                $set = isset($fields[1]) ? trim($fields[1]) : '';
                $number = isset($fields[3]) ? trim($fields[3]) : '';
                $finish = isset($fields[4]) ? strtolower(trim($fields[4])) : '';
                $qtyRaw = isset($fields[6]) ? trim($fields[6]) : '';
                $uuid = isset($fields[8]) ? trim($fields[8]) : '';
                $languageRaw = isset($fields[13]) ? strtolower(trim($fields[13])) : '';

                $hasValidUuid = ($uuid !== '' && Validation::validUUID($uuid, $appConfig) !== false);
                $hasSetAndNumber = (
                    $set !== ''
                    && Validation::isValidSetcode($set)
                    && $number !== ''
                );

                if (!$hasValidUuid && !$hasSetAndNumber) :
                    $msg->logMessage('[DEBUG]', "ManaBox row rejected: no valid UUID or set/collector number pair");
                    return false;
                endif;

                if (!preg_match('/^\d+$/', $qtyRaw) || (int) $qtyRaw <= 0) :
                    $msg->logMessage('[DEBUG]', "ManaBox row rejected: invalid quantity '$qtyRaw'");
                    return false;
                endif;
                $qty = (int) $qtyRaw;

                if ($finish === 'normal') :
                    $param5 = $qty;
                    $param6 = 0;
                    $param7 = 0;
                elseif ($finish === 'foil') :
                    $param5 = 0;
                    $param6 = $qty;
                    $param7 = 0;
                elseif ($finish === 'etched') :
                    $param5 = 0;
                    $param6 = 0;
                    $param7 = $qty;
                else :
                    $msg->logMessage('[DEBUG]', "ManaBox row rejected: unknown finish '$finish'");
                    return false;
                endif;

                if (Validation::isValidLanguageCode($languageRaw)) :
                    $lang = $languageRaw;
                else :
                    $lang = '';
                endif;

                if (!$hasValidUuid) :
                    $uuid = '';
                endif;
            else :
                return false;
            endif;

            // Sum the values of parameters 5, 6, and 7 for merged quantity input (used in decks)
            $qty = $param5 + $param6 + $param7;

            return [
                'set' => $set,
                'number' => $number,
                'name' => $name,
                'lang' => $lang,
                'qty' => $qty,
                'uuid' => $uuid,
                'normal' => $param5,
                'foil' => $param6,
                'etched' => $param7
            ];
        };

        // MAIN PROCESSING //

        // Is the line CSV with at least 4 fields?
        if ($is_csv($raw_string)) :
            // The line is in CSV format
            $result = $extract_and_process_csv($raw_string);

            if ($result === 'header') :
                return 'header';
            elseif ($result !== false) :
                if (($result['normal'] + $result['foil'] + $result['etched'] === 0) && $result['qty'] > 0) :
                    $result['normal'] = $result['qty'];
                endif;
                $msg->logMessage('[DEBUG]', "Input interpreter result (CSV): Qty: "
                        . "[{$result['qty']} (N: {$result['normal']},"
                        . " F: {$result['foil']}, E: {$result['etched']})] x Card: [{$result['name']}] "
                        . "Set: [{$result['set']}] Collector number: [{$result['number']}] "
                        . "UUID: [{$result['uuid']}]");
                return [
                    'set' => $result['set'],
                    'number' => $result['number'],
                    'name' => $result['name'],
                    'lang' => $result['lang'],
                    'qty' => $result['qty'],
                    'uuid' => $result['uuid'],
                    'normal' => $result['normal'],
                    'foil' => $result['foil'],
                    'etched' => $result['etched']
                ];
            else :
                return false;
            endif;
        elseif (
            trim($sanitised_string) === ''
            || Validation::inArrayCaseInsensitive(trim($sanitised_string), $importLinestoIgnore)
        ) :
            return 'empty line';
        else :
            // Not a CSV
            // Need to interpret a text line
            // as either a moxfield decklist line or a MTGC quick add text line
            // (MTGC has no info on normal/foil/etched)

            // If the string starts with a number < 1000, assume it's a quantity and
            // strip it from the string into a variable,
            // leaving the rest of the string to be assessed for name / set / number.
            // The only card names that start with numbers are Year cards, e.g.
            // 2001 World Championships Ad etc.

            $patternNumber = '/^(\d{1,3})\s+(.*)/'; // Match numbers up to 3 digits, and remove into $qty
            $matches = [];
            if (preg_match($patternNumber, trim($sanitised_string), $matches)) :
                $qty = $matches[1];
                $sanitised_string = trim($matches[2]);
            else :
                $qty = '';
                $sanitised_string = trim($sanitised_string);
            endif;

            if (strpos($sanitised_string, ' / ') !== false) :
                $replaceCount = 0;
                $normalizedString = preg_replace('/\\s\\/\\s/', ' // ', $sanitised_string, -1, $replaceCount);
                if ($replaceCount > 0 && $normalizedString !== null) :
                    $sanitised_string = $normalizedString;
                    $msg->logMessage('[DEBUG]', "Input interpreter normalized split card delimiter to '//'");
                endif;
            endif;

            // If string contains an opening ( or [ but no closing ) or ], then terminate the string with %] and submit
            if (
                strpos($sanitised_string, '(') !== false
                &&
                strpos($sanitised_string, ']') === false
                &&
                strpos($sanitised_string, ')') === false
            ) :
                $sanitised_string = $sanitised_string . "%)";
            elseif (
                strpos($sanitised_string, '[') !== false
                &&
                strpos($sanitised_string, ']') === false
                &&
                strpos($sanitised_string, ')') === false
            ) :
                $sanitised_string = $sanitised_string . "%]";
            endif;

            // Shortcut matches
            $pattern_shortcut1 = '/^[[(]([^)\]]+)[\])]\s+(\d+\S*?)$/';         // e.g. (mh3) 304 or [mh3] 304
            $pattern_shortcut2 = '/^[[(]([^)\]]+)\s+(\d+\S*?)[)\]]$/';         // e.g. (mh3 304) or [mh3 304]

            // Full matches
            $pattern_full_1    = '/^(.+?)\s+[(\[]([^)\]]+)[)\]]\s+(\d+\S*?)(\s\*F\*)?$/';
               // Plains (mh3) 304 or Plains [mh3] 304   Note - quantity already removed
            $pattern_full_2    = '/^(.+?)\s+[(\[]([^)\]]+)\s+(\d+\S*?)[)\]](\s\*F\*)?$/';
               // Plains (mh3 304) or Plains [mh3 304]   Note - quantity already removed

            // Legacy match - catches remaining non-specific cases, e.g. "Plains"
            $pattern_mtgc      = "/^([^()\[\]]+)?(?:[\[\(]\s*([^)\]\s]+)"
                . "(?:\s*([^)\]\s]+(?:\s+[^)\]\s]+)*)?)?\s*[\)\]])?/";

            // Shortcut matches (qty irrelevant)
            if (
                preg_match($pattern_shortcut1, $sanitised_string, $matches)
                ||
                preg_match($pattern_shortcut2, $sanitised_string, $matches)
            ) :
                $msg->logMessage('[DEBUG]', "Input interpreter result: String '$sanitised_string' is shortcut");
                $format = 'shortcut';
                // Set
                if (isset($matches[1])) :
                    $set = strtoupper($matches[1]);
                else :
                    $set = '';
                endif;
                // Collector number
                if (isset($matches[2])) :
                    $number = $matches[2];
                else :
                    $number = '';
                endif;
                $msg->logMessage(
                    '[DEBUG]',
                    "Input interpreter result (Shortcut): Set: [$set] Collector number: [$number]"
                );
                $output = [
                    'set' => $set,
                    'number' => $number,
                    'name' => '',
                    'lang' => '',
                    'qty' => $qty,
                    'uuid' => '',
                    'normal' => 0,
                    'foil' => 0,
                    'etched' => 0
                ];

            // Full matches
            elseif (
                preg_match($pattern_full_1, $sanitised_string, $matches)
                ||
                preg_match($pattern_full_2, $sanitised_string, $matches)
            ) :
                $msg->logMessage('[DEBUG]', "Input interpreter result: String '$sanitised_string' is full string");
                $format = 'full';
                if ($qty === '') :
                    $qty = 1;
                endif;
                $isFoil = isset($matches[4]) ? true : false;
                if ($isFoil) :
                    $normal = 0;
                    $foil = $qty;
                else :
                     $normal = $qty;
                     $foil = 0;
                endif;
                // Name
                if (isset($matches[1])) :
                    $name = trim($matches[1]);
                else :
                    $name = '';
                endif;
                // Set
                if (isset($matches[2])) :
                    $set = strtoupper($matches[2]);
                else :
                    $set = '';
                endif;
                // Collector number
                if (isset($matches[3])) :
                    $number = $matches[3];
                else :
                    $number = '';
                endif;
                $name = htmlspecialchars_decode($name, ENT_QUOTES);
                $msg->logMessage(
                    '[DEBUG]',
                    "Input interpreter result (full): Qty: [$qty (N:$normal / F:$foil)] x Card: [$name] "
                        . "Set: [$set] Collector number: [$number]"
                );
                $output = [
                    'set' => $set,
                    'number' => $number,
                    'name' => $name,
                    'lang' => '',
                    'qty' => $qty,
                    'uuid' => '',
                    'normal' => $normal,
                    'foil' => $foil,
                    'etched' => 0
                    ];
            elseif (preg_match($pattern_mtgc, trim($sanitised_string), $matches)) :
                $msg->logMessage('[DEBUG]', "Input interpreter result: String '$sanitised_string' is mtgc");
                $format = 'mtgc';
                if ($qty === '') :
                    $qty = 1;
                endif;

                // Name
                /// Catch fringe cases where name contains brackets ///
                if (isset($matches[1]) && isset($matches[2])) :
                    if (isset($matches[3])) :
                        $teststring = trim($matches[2]) . " " . trim($matches[3]);
                    else :
                        $teststring = trim($matches[2]);
                    endif;
                endif;
                if (isset($teststring) && Validation::inArrayCaseInsensitive($teststring, $bracketsInNames)) :
                    $msg->logMessage(
                        '[DEBUG]',
                        "Bracket contents match a card with brackets in name, resetting name, set to match"
                    );
                    $matches[1] = $matches[1] . "(" . $teststring . ")";
                    $matches[2] = $matches[3] = '';
                endif;

                if (isset($matches[1])) :
                    $name = trim($matches[1]);
                else :
                    $name = '';
                endif;
                // Set
                if (isset($matches[2])) :
                    $set = strtoupper($matches[2]);
                else :
                    $set = '';
                endif;
                // Collector number
                if (isset($matches[3])) :
                    $number = $matches[3];
                else :
                    $number = '';
                endif;
                $name = htmlspecialchars_decode($name, ENT_QUOTES);
                $msg->logMessage(
                    '[DEBUG]',
                    "Input interpreter result (MTGC Quick add): Qty: [$qty] x Card: [$name] Set: [$set] "
                        . "Collector number: [$number]"
                );
                $output = [
                    'set' => $set,
                    'number' => $number,
                    'name' => $name,
                    'lang' => '',
                    'qty' => $qty,
                    'uuid' => '',
                    'normal' => $qty,
                    'foil' => 0,
                    'etched' => 0
                ];
            else :
                return false;
            endif;
            return $output;
        endif;
    }

    public function importCollectionRegex(
        string $filename,
        string $mytable,
        string $importType,
        string $userEmail
    ) {
        // Import type = add, replace or remove
        // Import format = 'regex'
        // 'regex' may have no header row, and content like '1 All Is Dust [M3C 152]'
        // or any other style that inputInterpreter() can assess
        $importFormat = 'regex';
        $noQuickAddLayouts = $this->gameRules->get('noQuickAddLayouts', []);
        if (!is_array($noQuickAddLayouts)) :
            $noQuickAddLayouts = [];
        endif;
        $this->message->logMessage('[DEBUG]', "Import starting in '$importType' mode, '$importFormat' format");

        $handle = fopen($filename, "r");
        $fileContent = fread($handle, filesize($filename));
        $i = 0;
        $count = 0;
        $total = 0;
        $warningSummary = '';
        $lines = explode("\n", $fileContent);
        $qtyLines = count($lines);
        $this->message->logMessage('[DEBUG]', "Regex deck import has $qtyLines lines");
        $manaboxWarningDetail = function ($line) {
            $fields = str_getcsv($line, ',', '"', '\\');
            if (count($fields) < 15) :
                return '';
            endif;

            $finish = isset($fields[4]) ? strtolower(trim($fields[4])) : '';
            $qtyRaw = isset($fields[6]) ? trim($fields[6]) : '';
            $uuid = isset($fields[8]) ? trim($fields[8]) : '';
            $set = isset($fields[1]) ? trim($fields[1]) : '';
            $number = isset($fields[3]) ? trim($fields[3]) : '';

            if (!in_array($finish, ['normal', 'foil', 'etched'], true)) :
                return "Unknown ManaBox finish '$finish'";
            endif;
            if (!preg_match('/^\d+$/', $qtyRaw) || (int) $qtyRaw <= 0) :
                return "Invalid ManaBox quantity '$qtyRaw'";
            endif;
            if (
                !(
                    ($uuid !== '' && Validation::validUUID($uuid, $this->appConfig) !== false)
                    || ($set !== '' && Validation::isValidSetcode($set) && $number !== '')
                )
            ) :
                return "ManaBox row has no usable identity (need valid Scryfall ID or set code + collector number)";
            endif;
            return '';
        };

        foreach ($lines as $line) :
            $rowNumber = $i + 1;
            $this->message->logMessage('[DEBUG]', "Row: $rowNumber: Reviewing line");
            $linestring = htmlspecialchars($line, ENT_NOQUOTES, 'UTF-8');
            $interpretedString = self::inputInterpreter($linestring, $this->appConfig, $this->gameRules);
            if ($interpretedString === 'header') :
                $this->message->logMessage('[DEBUG]', "Row: $rowNumber: Header row");
            elseif ($interpretedString === 'empty line') :
                $this->message->logMessage('[DEBUG]', "Row: $rowNumber: Empty row");
            elseif (
                $interpretedString === false
                || (
                    empty($interpretedString['uuid'])
                    && (empty($interpretedString['set']) || empty($interpretedString['number']))
                )
            ) :
                $this->message->logMessage('[DEBUG]', "Row: $rowNumber: Not enough usable card info (or empty row)");
                $manaboxIssue = $manaboxWarningDetail($line);
                if ($manaboxIssue !== '') :
                    $newWarning = "$rowNumber, $manaboxIssue (row detail: '$line') \n";
                else :
                    $newWarning = "$rowNumber, Not enough info to identify card (row detail: '$line') \n";
                endif;
                $warningSummary = $warningSummary . $newWarning;
            else :
                $this->message->logMessage('[DEBUG]', "Row: $rowNumber: Possible card");
                $count = $count + 1; //Increment unique card row count

                // UUID
                if (isset($interpretedString['uuid']) and $interpretedString['uuid'] !== '') :
                    $quickAddUuid = $interpretedString['uuid'];
                else :
                    $quickAddUuid = '';
                endif;
                $hasValidUuid = (
                    $quickAddUuid !== ''
                    && Validation::validUUID($quickAddUuid, $this->appConfig) !== false
                );

                // Quantity
                if (isset($interpretedString['normal']) and $interpretedString['normal'] !== '') :
                    $quickaddnormal = $interpretedString['normal'];
                else :
                    $quickaddnormal = 0;
                endif;
                if (isset($interpretedString['foil']) and $interpretedString['foil'] !== '') :
                    $quickaddfoil = $interpretedString['foil'];
                else :
                    $quickaddfoil = 0;
                endif;
                if (isset($interpretedString['etched']) and $interpretedString['etched'] !== '') :
                    $quickaddetched = $interpretedString['etched'];
                else :
                    $quickaddetched = 0;
                endif;

                // Name
                if (isset($interpretedString['name']) and $interpretedString['name'] !== '') :
                    $quickAddCard = $interpretedString['name'];
                else :
                    $quickAddCard = '';
                endif;

                // Set
                if (isset($interpretedString['set']) and $interpretedString['set'] !== '') :
                    $quickAddSet = strtoupper($interpretedString['set']);
                else :
                    $quickAddSet = '';
                endif;

                // Lang
                if (isset($interpretedString['lang']) and $interpretedString['lang'] !== '') :
                    $quickAddLang = strtoupper($interpretedString['lang']);
                else :
                    $quickAddLang = '';
                endif;

                // Collector number
                if (isset($interpretedString['number']) and $interpretedString['number'] !== '') :
                    $quickAddNumber = $interpretedString['number'];
                else :
                    $quickAddNumber = '';
                endif;

                $quickAddCard = htmlspecialchars_decode($quickAddCard, ENT_QUOTES);
                $quickAddCardNormalized = $this->normalizeImportedCardName($quickAddCard);
                if ($quickAddCardNormalized !== $quickAddCard) :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Row: $rowNumber: Quick add normalized escaped quote sequences in card name"
                    );
                    $quickAddCard = $quickAddCardNormalized;
                endif;
                $this->message->logMessage(
                    '[DEBUG]',
                    "Row: $rowNumber: Quick add interpreted as: "
                        . "Normal: [$quickaddnormal] "
                        . "Foil: [$quickaddfoil] "
                        . "Etched: [$quickaddetched] "
                        . " x Card: [$quickAddCard] Set: [$quickAddSet] "
                    . "Collector number: [$quickAddNumber] Language: [$quickAddLang] UUID: [$quickAddUuid]"
                );
                $stmt = null;

                if ($hasValidUuid) :
                    // Card UUID provided and valid UUID
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Row: $rowNumber: Quick add proceeding with provided UUID: [$quickAddUuid]"
                    );
                    $query = "SELECT id,finishes,name,f1_name,f2_name,printed_name,f1_printed_name,f2_printed_name,
                                flavor_name,f1_flavor_name,f2_flavor_name,setcode,number_import
                                FROM cards_scry WHERE id = ? LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = [$quickAddUuid];
                    $stmt->bind_param('s', $params[0]);
                elseif (
                    $quickAddCard !== ''
                    and $quickAddSet !== ''
                    and $quickAddNumber !== ''
                    and $quickAddLang !== ''
                ) :
                    // Card name, setcode, and collector number provided
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Row: $rowNumber: Quick add proceeding with provided name, set, number and specified language"
                    );
                    $query = "SELECT id,finishes FROM cards_scry WHERE (name = ? OR f1_name = ? OR f2_name = ? 
                                                                   OR printed_name = ? OR f1_printed_name = ? OR 
                                                                   f2_printed_name = ? OR flavor_name = ? OR 
                                                                   f1_flavor_name = ? OR f2_flavor_name = ?) AND 
                                                                   setcode = ? AND number_import = ? AND 
                                                                   lang LIKE ? ORDER BY release_date DESC LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = array_fill(0, 9, $quickAddCard);
                    array_push($params, $quickAddSet, $quickAddNumber, $quickAddLang);
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                elseif ($quickAddCard !== '' and $quickAddSet !== '' and $quickAddNumber !== '') :
                    // Card name, setcode, and collector number provided
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Row: $rowNumber: Quick add proceeding with provided name, set, number and primary language"
                    );
                    $query = "SELECT id,finishes FROM cards_scry WHERE (name = ? OR f1_name = ? OR f2_name = ? 
                                                                   OR printed_name = ? OR f1_printed_name = ? OR 
                                                                   f2_printed_name = ? OR flavor_name = ? OR 
                                                                   f1_flavor_name = ? OR f2_flavor_name = ?) AND 
                                                                   setcode = ? AND number_import = ? AND 
                                                                   primary_card = 1 ORDER BY release_date DESC LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = array_fill(0, 9, $quickAddCard);
                    array_push($params, $quickAddSet, $quickAddNumber);
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                elseif ($quickAddCard !== '' and $quickAddSet !== '' and $quickAddNumber === '') :
                    // Card name and setcode provided
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Row: $rowNumber: Quick add proceeding with provided name, set"
                    );
                    $query = "SELECT id,finishes FROM cards_scry WHERE (name = ? OR f1_name = ? OR f2_name = ? 
                                                                   OR printed_name = ? OR f1_printed_name = ? OR 
                                                                   f2_printed_name = ? OR flavor_name = ? OR 
                                                                   f1_flavor_name = ? OR f2_flavor_name = ?) AND 
                                                                   setcode = ? AND primary_card = 1 
                                                                   ORDER BY release_date DESC, number ASC LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = array_fill(0, 9, $quickAddCard);
                    array_push($params, $quickAddSet);
                    $params = array_merge($params, $noQuickAddLayouts);
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                elseif ($quickAddCard === '' and $quickAddSet !== '' and $quickAddNumber !== '') :
                    // Card name not provided, setcode, and collector number provided
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Row: $rowNumber: Quick add proceeding with provided set and number"
                    );
                    $query = "SELECT id,finishes FROM cards_scry WHERE setcode = ? AND number_import = ? 
                                                               AND primary_card = 1 ORDER BY release_date DESC LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = [$quickAddSet, $quickAddNumber];
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                else :
                    // Not enough info, cannot add
                    $this->message->logMessage(
                        '[NOTICE]',
                        "Row: $rowNumber: Quick add - Not enough info to identify a card to add"
                    );
                    $cardtoadd = 'cardnotfound';
                    $newWarning = "$rowNumber, Not enough info to identify card (row detail: '$line') \n";
                    $warningSummary = $warningSummary . $newWarning;
                endif;
                if ($stmt !== null and $stmt->execute()) :
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) :
                        $row = $result->fetch_assoc();
                        $stmt->close();
                        $uuidMismatchReasons = [];
                        if ($hasValidUuid) :
                            if ($quickAddCard !== '') :
                                $nameCandidates = [];
                                foreach (
                                    [
                                        'name',
                                        'f1_name',
                                        'f2_name',
                                        'printed_name',
                                        'f1_printed_name',
                                        'f2_printed_name',
                                        'flavor_name',
                                        'f1_flavor_name',
                                        'f2_flavor_name'
                                    ] as $nameColumn
                                ) :
                                    if (!empty($row[$nameColumn])) :
                                        $nameCandidates[] = $row[$nameColumn];
                                    endif;
                                endforeach;
                                if (!Validation::inArrayCaseInsensitive($quickAddCard, $nameCandidates)) :
                                    $uuidMismatchReasons[] = "name mismatch (file '$quickAddCard')";
                                endif;
                            endif;
                        endif;

                        if (!empty($uuidMismatchReasons)) :
                            $this->message->logMessage(
                                '[NOTICE]',
                                "Row: $rowNumber: UUID cross-check failed: " . implode('; ', $uuidMismatchReasons)
                            );
                            $cardtoadd = 'cardnotfound';
                            $newWarning = "$rowNumber, UUID cross-check failed - " . implode('; ', $uuidMismatchReasons)
                                . " (row detail: '$line') \n";
                            $warningSummary = $warningSummary . $newWarning;
                        else :
                            $cardtoadd = $row['id'];
                            $finishes = $row['finishes'];
                            $this->message->logMessage(
                                '[DEBUG]',
                                "Row: $rowNumber: Quick add result: UUID result is '$cardtoadd', adding to batch"
                            );
                            $this->batchedCardIds[] = [
                                'line' => $line,
                                'row' => $rowNumber,
                                'id' => $cardtoadd,
                                'finishes' => $finishes,
                                'normal' => $quickaddnormal,
                                'foil' => $quickaddfoil,
                                'etched' => $quickaddetched,
                            ];
                            $total = $total + $quickaddnormal + $quickaddfoil + $quickaddetched;
                        endif;
                    else :
                        $stmt->close();
                        $this->message->logMessage('[NOTICE]', "Row: $rowNumber: Quick add - Card not found");
                        $cardtoadd = 'cardnotfound';
                        $newWarning = "$rowNumber, Card not found (row detail: '$line') \n";
                        $warningSummary = $warningSummary . $newWarning;
                    endif;
                else :
                    $stmt->close();
                    $this->message->logMessage('[ERROR]', "Quick add - SQL error: " . $stmt->error);
                    $cardtoadd = 'cardnotfound';
                    $newWarning = "$rowNumber, Unknown error (row detail: '$line') \n";
                    $warningSummary = $warningSummary . $newWarning;
                endif;
            endif;
            $i = $i + 1;
        endforeach;
        if ($count === 0) :
            $this->message->logMessage('[DEBUG]', "No cards in the file to import");
            fclose($handle);
            ?>
            <script type="text/javascript">
                alert('WARNING\n\nNo cards in the file to import');
            </script> <?php
            return 'emptyfile';
        endif;

        // Finalise any warnings from file scan phase
        if ($warningSummary === '') :
            $warningSummary = "Input file scan warnings or errors\n\nNone\n\n";
        else :
            $warningSummary = "Input file scan warnings or errors (Row number, Warning/error)\n\n" . $warningSummary;
        endif;

        $actionedCards = 0;
        $actionedRows = 0;
        // If batched card array is not empty, perform batch insert
        if (!empty($this->batchedCardIds)) :
            $batchOutput = $this->addCardsBatch($mytable, $importType, $count, $total, $this->batchedCardIds);
            if ($batchOutput['warnings'] !== 'none') :
                $warningSummary = $warningSummary . $batchOutput['warnings'];
            else :
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
        $subject = "Import failures / warnings";
        $message = "$warningSummary \n \n$summary";
        if ($this->emailEnabled === true) :
            $mail = new MyPHPMailer(true, $this->appConfig);
            $mail->sendEmail($userEmail, false, $subject, $message);
        else :
            $this->message->logMessage(
                '[NOTICE]',
                "Email disabled; import warning/summary email not sent to $userEmail"
            );
        endif;
                $this->message->logMessage(
                    '[NOTICE]',
                    "Import process run with '$importType' ($actionedRows of $count card rows actioned, "
                        . "$actionedCards of $total cards actioned)"
                );
        if ($actionedCards === 0) :?>
            <script type="text/javascript">
                alert(
                    'WARNING\n\nNo actions were taken, check your file\n\nEmail has been sent to you with '
                    + 'warnings/error details'
                );
                window.onload=function(){document.body.style.cursor='wait';}
            </script> <?php
        elseif ($count === $actionedRows && $total === $actionedCards) : ?>
            <script type="text/javascript">
                (function() {
                    fetch('/valueupdate.php?table=<?php echo("$mytable"); ?>');
                })();

                alert(
                    'Import type: <?php echo $importType;?>\n<?php echo $count;?> card rows found in file with '
                    + '<?php echo $total;?> cards\nAll card rows and cards actioned\n\nCollection value is now '
                    + 'being resynced, this can take some time for large collections, please wait'
                );
                window.onload=function(){document.body.style.cursor='wait';}
            </script> <?php
        else : ?>
            <script type="text/javascript">
                (function() {
                    fetch('/valueupdate.php?table=<?php echo("$mytable"); ?>');
                })();

                alert(
                    'Import type: <?php echo $importType;?>\n<?php echo $count;?> card rows found in file with '
                    + '<?php echo $total;?> cards\n<?php echo $actionedRows;?> card rows actioned with '
                    + '<?php echo $actionedCards;?> cards\n\nDetails have been emailed to you with '
                    + 'warnings/error details\n\nCollection value is now being resynced, this can take some time '
                    + 'for large collections, please wait'
                );
                window.onload=function(){document.body.style.cursor='wait';}
            </script> <?php
        endif;
    }

    /**
    * @param array<int, array<string, mixed>> $batchedCardIds
    * @return array{warnings: string, total: int, batchRows: int}
    */
    public function addCardsBatch(
        string $mytable,
        string $importType,
        int $count,
        int $total,
        array $batchedCardIds
    ): array {
        $this->message->logMessage(
            '[DEBUG]',
            "Batch import process called with '$importType' ($count unique cards, $total total cards)"
        );
        $validBatchedCardIds = [];
        $batchWarnings = '';

        foreach ($batchedCardIds as $batchedCard) :
            $line = $batchedCard['line'];
            $rowNumber = $batchedCard['row'];
            $id = $batchedCard['id'];
            $finishes = json_decode($batchedCard['finishes'], true);
            $cardtype = CardUtils::cardTypes($finishes);
            $normal = $batchedCard['normal'];
            $foil = $batchedCard['foil'];
            $etched = $batchedCard['etched'];
            $qty = $normal + $foil + $etched;

            // Validate card types, 'continue' out of this 'foreach' if there are any issues, logging an error
            if ($normal > 0 && !str_contains($cardtype, 'normal')) :
                $this->message->logMessage(
                    '[ERROR]',
                    "Row: $rowNumber: Batch import finish mapping error (normal) - skipping row"
                );
                $newWarning = "$rowNumber, Normal qty cannot be mapped to card without normal finish - row skipped "
                    . "(row detail: '$line') \n";
                $batchWarnings = $batchWarnings . $newWarning;
                $total = $total - $qty;         // Deduct cards from total card count
                $count = $count - 1;            // Deduct the entire row from the row count
                continue;
            endif;
            if ($foil > 0 && !str_contains($cardtype, 'foil')) :
                $this->message->logMessage(
                    '[ERROR]',
                    "Row: $rowNumber: Batch import finish mapping error (foil) - skipping row"
                );
                $newWarning = "$rowNumber, Foil qty cannot be mapped to card without foil finish - row skipped "
                    . "(row detail: '$line') \n";
                $batchWarnings = $batchWarnings . $newWarning;
                $total = $total - $qty;         // Row skipped, deduct all finish quantities
                $count = $count - 1;
                continue;
            endif;
            if ($etched > 0 && !str_contains($cardtype, 'etched')) :
                $this->message->logMessage(
                    '[ERROR]',
                    "Row: $rowNumber: Batch import finish mapping error (etched) - skipping row"
                );
                $newWarning = "$rowNumber, Etched qty cannot be mapped to card without etched finish - row skipped "
                    . "(row detail: '$line') \n";
                $batchWarnings = $batchWarnings . $newWarning;
                $total = $total - $qty;         // Row skipped, deduct all finish quantities
                $count = $count - 1;
                continue;
            endif;
            // Add each card to the batch
            $this->message->logMessage('[DEBUG]', "Row: $rowNumber: Batch import - adding to batch ('$line')");
            $validBatchedCardIds[] = [
                'id' => $id,
                'normal' => $normal,
                'foil' => $foil,
                'etched' => $etched,
            ];
        endforeach;
        $this->message->logMessage('[DEBUG]', "Batch import warnings: '$batchWarnings'");
        if (!empty($validBatchedCardIds)) :
            $this->message->logMessage('[DEBUG]', "Batch import: Assessing import type variations ($importType)");

            if (
                ($importType !== 'add')
                and ($importType !== 'subtract')
                and ($importType !== 'replace')
            ) :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . " Function " . __FUNCTION__
                    . ": Unsupported import type '$importType'"
                );
            endif;
            if ($importType === 'add') :
                $updateClause = "normal = normal + VALUES(normal),
                            foil = foil + VALUES(foil),
                            etched = etched + VALUES(etched)";
            elseif ($importType === 'subtract') :
                $updateClause = "normal = greatest(normal - VALUES(normal),0),
                            foil = greatest(foil - VALUES(foil),0),
                            etched = greatest(etched - VALUES(etched),0)";
            else :
                $updateClause = "normal = VALUES(normal),
                            foil = VALUES(foil),
                            etched = VALUES(etched)";
            endif;

            $maxPlaceholders = 65000;
            $placeholdersPerRow = 4;
            $maxRowsPerStatement = (int) floor($maxPlaceholders / $placeholdersPerRow);
            if ($maxRowsPerStatement < 1) :
                $maxRowsPerStatement = 1;
            endif;
            $batchChunks = array_chunk($validBatchedCardIds, $maxRowsPerStatement);

            $this->message->logMessage(
                '[DEBUG]',
                "Batch import: executing " . count($batchChunks) . " SQL chunk(s) at max "
                    . "$maxRowsPerStatement rows/chunk"
            );

            if ($this->db->begin_transaction() === false) :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . " Function " . __FUNCTION__
                    . ": SQL begin transaction failure: " . $this->db->error
                );
            endif;

            try {
                foreach ($batchChunks as $chunkIndex => $batchChunk) :
                    $placeholders = array_fill(0, count($batchChunk), '(?, ?, ?, ?)');
                    $placeholdersString = implode(', ', $placeholders);
                    $query = "INSERT INTO $mytable (id, normal, foil, etched) VALUES $placeholdersString
                            ON DUPLICATE KEY
                            UPDATE
                            $updateClause";

                    $stmt = $this->db->prepare($query);
                    if ($stmt === false) :
                        throw new \Exception(
                            '[ERROR]' . basename(__FILE__) . " " . __LINE__ . " Function " . __FUNCTION__
                            . ": SQL prepare failure: " . $this->db->error
                        );
                    endif;

                    $typeDefinition = str_repeat('siii', count($batchChunk));
                    $bindValues = [];
                    foreach ($batchChunk as $chunkCard) :
                        $bindValues[] = $chunkCard['id'];
                        $bindValues[] = $chunkCard['normal'];
                        $bindValues[] = $chunkCard['foil'];
                        $bindValues[] = $chunkCard['etched'];
                    endforeach;

                    if ($stmt->bind_param($typeDefinition, ...$bindValues) === false) :
                        $error = $stmt->error;
                        $stmt->close();
                        throw new \Exception(
                            '[ERROR]' . basename(__FILE__) . " " . __LINE__ . " Function " . __FUNCTION__
                            . ": SQL bind failure: " . $error
                        );
                    endif;
                    if ($stmt->execute() === false) :
                        $error = $stmt->error;
                        $stmt->close();
                        throw new \Exception(
                            '[ERROR]' . basename(__FILE__) . " " . __LINE__ . " Function " . __FUNCTION__
                            . ": SQL execute failure: " . $error
                        );
                    endif;

                    $stmt->close();
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Batch import: SQL chunk " . ($chunkIndex + 1) . " of " . count($batchChunks)
                            . " completed"
                    );
                endforeach;
                if ($this->db->commit() === false) :
                    throw new \Exception(
                        '[ERROR]' . basename(__FILE__) . " " . __LINE__ . " Function " . __FUNCTION__
                        . ": SQL commit failure: " . $this->db->error
                    );
                endif;
            } catch (\Exception $batchSqlException) {
                $this->db->rollback();
                throw $batchSqlException;
            }

            if ($batchWarnings === '') :
                $batchWarnings = "\nBatch import warnings or errors\n\nNone\n\n";
            else :
                $batchWarnings = "\nBatch import warnings or errors (Row number, Warning/error)\n\n"
                    . $batchWarnings;
            endif;
            $this->message->logMessage('[DEBUG]', "importCollectionRegex batch process completed");
            return array('warnings' => $batchWarnings, 'total' => $total, 'batchRows' => $count);
        else :
            $this->message->logMessage('[DEBUG]', "importCollectionRegex batch process completed (no writes made)");
            if ($batchWarnings === '') :
                $batchWarnings = "\nBatch import warnings or errors\n\nNone\n\n";
            else :
                    $batchWarnings = "\nBatch import warnings or errors (Row number, Warning/error)\n\n"
                        . $batchWarnings;
            endif;
                return array('warnings' => $batchWarnings, 'total' => $total, 'batchRows' => $count);
        endif;
    }

    private function deleteOrphans(string $mytable): void
    {
        $queryString = "DELETE FROM $mytable WHERE COALESCE(normal,0) + COALESCE(foil,0) + COALESCE(etched,0) = 0";
        if ($query = $this->db->execute_query($queryString)) :
            $this->message->logMessage('[NOTICE]', "Deleted {$this->db->affected_rows} orphan rows");
        else :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
        endif;
    }

    private function normalizeImportedCardName(mixed $cardName): string
    {
        if (!is_string($cardName)) :
            return '';
        endif;
        $normalized = trim($cardName);
        $replaced = preg_replace('/\\\\([\"\'])/', '$1', $normalized);
        if ($replaced === null) :
            return $normalized;
        endif;
        return $replaced;
    }
}
