<?php

/*
Version:     1.9
Date:        25/07/26
Name:        INI.php
Purpose:     Simple PHP class to manage INI files (read/write).
Notes:       Third-party code from IT-radionica.com.
Author:      Radovan Janjic <rade@it-radionica.com>
Copyright:   2011 IT-radionica.com
To do:       -
*/

/*
Examples

// Parse config.ini
$ini = new INI('config.ini');

echo '<pre>';
echo 'Content of: config.ini' . PHP_EOL;
print_r($ini->data);

// Update settings
$ini->data['first_section']['animal'] = 'COW';

// Save settings to file
$ini->write();

// Update settings
$ini->data['first_section']['animal'] = 'HORSE';

// Add new setting to section third_section
$ini->data['third_section']['phpversion'][] = 5.4;

// Add new section third_section and new item something
$ini->data['fourth_section']['something'] = 'some data';

// Save settings to new file
$ini->write('config-2.ini');

// INI obj is now using ini 2 file
echo '<hr>Content of: config-2.ini' . PHP_EOL;
print_r($ini->data);

// Parse config.ini
$ini->read('config.ini');

// Remove item from second_section
unset($ini->data['second_section']['URL']);

// Remove third_section from second ini file and save to third file
unset($ini->data['third_section']);

// Save settings to new file
$ini->write('config-3.ini');

// INI obj is now using ini 3 file
echo '<hr>Content of: config-3.ini' . PHP_EOL;
print_r($ini->data);
*/

namespace MTG\Core;

class INI
{
    /** INI file path @var string */
    public ?string $file = null;

    /** INI data @var array */
    public array|false $data = array();

    /** Process sections @var bool */
    public bool $sections = true;

    private string $lastError = '';

    /**
     * Parse INI file.
     *
     * @param string|null $file     INI file path
     * @param bool        $sections Process sections
     */
    public function __construct(?string $file = null, bool $sections = true)
    {
        if ($file !== null) {
            $this->read($file, $sections);
        }
    }

    /**
     * Parse INI file.
     *
     * @param string|null $file     INI file path
     * @param bool        $sections Process sections
     */
    public function read(?string $file = null, bool $sections = true): array|false
    {
        $this->file = ($file) ? $file : $this->file;
        $this->sections = $sections;
        $this->data = parse_ini_file(realpath($this->file), $this->sections);
        return $this->data;
    }

    /**
     * Write INI file.
     *
     * @param string|null $file     INI file path
     * @param array       $data     Data (associative array)
     * @param bool        $sections Process sections
     */
    public function write(?string $file = null, array $data = array(), bool $sections = true): bool
    {
        $this->data = (!empty($data)) ? $data : $this->data;
        $this->file = ($file) ? $file : $this->file;
        $this->sections = $sections;
        $this->lastError = '';
        if ($this->file === null || $this->file === '') :
            $this->lastError = 'No configuration file was specified.';
            return false;
        endif;
        if (
            (is_file($this->file) && !is_writable($this->file))
            || (!is_file($this->file) && !is_writable(dirname($this->file)))
        ) :
            $this->lastError = 'Configuration file is not writable.';
            return false;
        endif;
        $content = null;

        if ($this->sections) {
            foreach ($this->data as $section => $sectionData) {
                $content .= '[' . $section . ']' . PHP_EOL;
                foreach ($sectionData as $key => $val) {
                    if (is_array($val)) {
                        foreach ($val as $v) {
                            $content .= $key . '[] = ' . (is_numeric($v) ? $v : '"' . $v . '"') . PHP_EOL;
                        }
                    } elseif (empty($val)) {
                        $content .= $key . ' = ' . PHP_EOL;
                    } else {
                        $content .= $key . ' = ' . (is_numeric($val) ? $val : '"' . $val . '"') . PHP_EOL;
                    }
                }
                $content .= PHP_EOL;
            }
        } else {
            foreach ($this->data as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $v) {
                        $content .= $key . '[] = ' . (is_numeric($v) ? $v : '"' . $v . '"') . PHP_EOL;
                    }
                } elseif (empty($val)) {
                    $content .= $key . ' = ' . PHP_EOL;
                } else {
                    $content .= $key . ' = ' . (is_numeric($val) ? $val : '"' . $val . '"') . PHP_EOL;
                }
            }
        }

        $handle = @fopen($this->file, 'w');
        if ($handle === false) :
            $this->lastError = 'Unable to open configuration file for writing.';
            return false;
        endif;

        $written = fwrite($handle, trim($content));
        $closed = fclose($handle);
        if ($written === false || $closed === false) :
            $this->lastError = 'Unable to write configuration file.';
            return false;
        endif;

        return true;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }
}
