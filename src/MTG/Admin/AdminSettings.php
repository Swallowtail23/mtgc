<?php

/*
Version:     1.1
Date:        28/04/26
Name:        AdminSettings.php
Purpose:     Admin settings helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Admin;

use MTG\Core\AppConfig;
use MTG\Core\Message;

class AdminSettings
{
    /**
     * @param \mysqli|object $db
     */
    public static function getCssVersionSuffix($db, AppConfig $appConfig): string
    {
        $msg = new Message($appConfig);
        if (!is_object($db) || !method_exists($db, 'execute_query')) :
            $msg->logMessage(
                '[WARNING]',
                "CSS version check skipped, defaulting to minified CSS: database unavailable"
            );
            return "-min";
        endif;
        $sql = "SELECT usemin FROM admin LIMIT 1";
        $result = $db->execute_query($sql);
        if ($result === false || !is_object($result)) :
            $msg->logMessage(
                '[ERROR]',
                "CSS version check failed, defaulting to minified CSS: " . ($db->error ?? 'unknown error')
            );
            return "-min";
        else :
            $row = $result->fetch_assoc();
            if (method_exists($result, 'free')) :
                $result->free();
            endif;
            if (!empty($row) && (int) $row['usemin'] === 1) :
                return "-min";
            else :
                return "";
            endif;
        endif;
    }

    /**
     * @param \mysqli|object $db
     */
    public static function setMaintenanceMode(string $toggle, $db, AppConfig $appConfig): bool
    {
        $msg = new Message($appConfig);
        $toggle = strtolower(trim((string) $toggle));

        if ($toggle === 'off') :
            $msg->logMessage('[NOTICE]', "Setting maintenance mode off");
            $mtcequery = 0;
        elseif ($toggle === 'on') :
            $msg->logMessage('[NOTICE]', "Setting maintenance mode on");
            $mtcequery = 1;
        else :
            $msg->logMessage('[ERROR]', "Invalid maintenance mode toggle: '{$toggle}'");
            return false;
        endif;

        $query = 'UPDATE admin SET mtce=?';

        $stmt = $db->prepare($query);
        if ($stmt === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . " Function " . __FUNCTION__
                    . ": Prepare SQL failed: " . $db->error
            );
        endif;

        $bound = $stmt->bind_param('i', $mtcequery);
        if ($bound === false) :
            $stmt->close();
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . " Function " . __FUNCTION__
                    . ": Bind SQL failed: " . $stmt->error
            );
        endif;

        $exec = $stmt->execute();
        if ($exec === false) :
            $msg->logMessage('[ERROR]', "Setting mtce mode to {$mtcequery} failed: " . $stmt->error);
            $stmt->close();
            return false;
        else :
            $msg->logMessage('[NOTICE]', "Set mtce mode to {$mtcequery}");
            $stmt->close();
            return true;
        endif;
    }

    /**
     * @param \mysqli|object $db
     */
    public static function checkMaintenanceMode(int $user, $db, AppConfig $appConfig): int
    {
        $msg = new Message($appConfig);

        $msg->logMessage('[DEBUG]', "Checking maintenance mode, user $user");
        $sql1 = "SELECT mtce FROM admin LIMIT 1";
        $result1 = $db->execute_query($sql1);
        if ($result1 === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $db->error
            );
        else :
            $row1 = $result1->fetch_assoc();
            if (!empty($row1) && $row1['mtce'] == 1) :
                $msg->logMessage('[DEBUG]', "Maintenance mode on, running admin check");
                $sql2 = "SELECT admin FROM users WHERE usernumber = ?";
                $result2 = $db->execute_query($sql2, [$user]);
                if ($result2 === false) :
                    throw new \Exception(
                        '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                            . ": SQL failure: " . $db->error
                    );
                else :
                    $row2 = $result2->fetch_assoc();
                    if (!empty($row2)) :
                        if ($row2['admin'] == 1) :
                            $msg->logMessage('[DEBUG]', "Maintenance mode on, user is admin, ignoring (return 2)");
                            return 2;
                        else :
                            $msg->logMessage(
                                '[DEBUG]',
                                "Maintenance mode on, user is not admin (return 1, destroy session)"
                            );
                            return 1;
                        endif;
                    else :
                        throw new \Exception(
                            '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                                . ": SQL failure: " . $db->error
                        );
                    endif;
                endif;
            else :
                $msg->logMessage('[DEBUG]', "Maintenance mode not set");
                return 0; // maintenance mode not set
            endif;
        endif;
    }
}
