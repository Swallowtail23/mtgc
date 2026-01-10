<?php

/*
Version:     1.6
Date:        21/12/25
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
    public $file = null;

    /** INI data @var array */
    public $data = array();

    /** Process sections @var bool */
    public $sections = true;

    /**
     * Parse INI file.
     *
     * @param string|null $file     INI file path
     * @param bool        $sections Process sections
     */
    public function __construct($file = null, $sections = true)
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
    public function read($file = null, $sections = true)
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
    public function write($file = null, $data = array(), $sections = true)
    {
        $this->data = (!empty($data)) ? $data : $this->data;
        $this->file = ($file) ? $file : $this->file;
        $this->sections = $sections;
        $content = null;

        if ($this->sections) {
            foreach ($this->data as $section => $data) {
                $content .= '[' . $section . ']' . PHP_EOL;
                foreach ($data as $key => $val) {
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

        $written = (($handle = fopen($this->file, 'w')) && fwrite($handle, trim($content)) && fclose($handle));
        return $written ? true : false;
    }
}
