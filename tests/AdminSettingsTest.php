<?php

/*
Version:     1.0
Date:        28/04/26
Name:        AdminSettingsTest.php
Purpose:     Tests admin settings helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Admin\AdminSettings;
use MTG\Core\AppConfig;
use PHPUnit\Framework\TestCase;

class AdminSettingsTest extends TestCase
{
    private AppConfig $appConfig;

    protected function setUp(): void
    {
        $ini = [
            'general' => [
                'Logfile' => sys_get_temp_dir() . '/adminsettings_test.log'
            ],
            'security' => [],
            'email' => [],
            'fx' => [],
            'comments' => []
        ];
        $this->appConfig = AppConfig::fromIni($ini);
    }

    public function testSetMaintenanceModeRejectsInvalidToggle()
    {
        $db = new class {
            public function prepare(string $query): false
            {
                unset($query);
                return false;
            }
        };

        $this->assertFalse(AdminSettings::setMaintenanceMode('invalid', $db, $this->appConfig));
    }

    public function testSetMaintenanceModeSucceeds()
    {
        $db = new class {
            public function prepare(string $query): object
            {
                unset($query);
                return new class {
                    public function bind_param(string $types, mixed &...$vars): bool
                    {
                        unset($types, $vars);
                        return true;
                    }
                    public function execute(): bool
                    {
                        return true;
                    }
                    public function close(): void
                    {
                    }
                };
            }
        };

        $this->assertTrue(AdminSettings::setMaintenanceMode('on', $db, $this->appConfig));
        $this->assertTrue(AdminSettings::setMaintenanceMode('off', $db, $this->appConfig));
    }

    public function testCheckMaintenanceModeReturnsExpectedValues()
    {
        $db = new class {
            private int $calls = 0;
            public string $error = 'fail';

            public function execute_query(string $sql, array $params = []): object|false
            {
                unset($sql, $params);
                $this->calls++;
                if ($this->calls === 1) {
                    return new class {
                        public function fetch_assoc(): array
                        {
                            return ['mtce' => 0];
                        }
                    };
                }
                return false;
            }
        };

        $this->assertSame(0, AdminSettings::checkMaintenanceMode(1, $db, $this->appConfig));

        $db = new class {
            private int $calls = 0;
            public string $error = 'fail';

            public function execute_query(string $sql, array $params = []): object
            {
                unset($sql, $params);
                $this->calls++;
                if ($this->calls === 1) {
                    return new class {
                        public function fetch_assoc(): array
                        {
                            return ['mtce' => 1];
                        }
                    };
                }
                return new class {
                    public function fetch_assoc(): array
                    {
                        return ['admin' => 1];
                    }
                };
            }
        };

        $this->assertSame(2, AdminSettings::checkMaintenanceMode(1, $db, $this->appConfig));

        $db = new class {
            private int $calls = 0;
            public string $error = 'fail';

            public function execute_query(string $sql, array $params = []): object
            {
                unset($sql, $params);
                $this->calls++;
                if ($this->calls === 1) {
                    return new class {
                        public function fetch_assoc(): array
                        {
                            return ['mtce' => 1];
                        }
                    };
                }
                return new class {
                    public function fetch_assoc(): array
                    {
                        return ['admin' => 0];
                    }
                };
            }
        };

        $this->assertSame(1, AdminSettings::checkMaintenanceMode(1, $db, $this->appConfig));
    }
}
