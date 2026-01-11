<?php

use MTG\Admin\AdminSettings;
use MTG\Core\AppConfig;
use PHPUnit\Framework\TestCase;

class AdminSettingsTest extends TestCase
{
    private $appConfig;

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
            public function prepare($query)
            {
                return false;
            }
        };

        $this->assertFalse(AdminSettings::setMaintenanceMode('invalid', $db, $this->appConfig));
    }

    public function testSetMaintenanceModeSucceeds()
    {
        $db = new class {
            public function prepare($query)
            {
                return new class {
                    public function bind_param($types, &...$vars)
                    {
                        return true;
                    }
                    public function execute()
                    {
                        return true;
                    }
                    public function close()
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
            private $calls = 0;
            public $error = 'fail';
            public function execute_query($sql, $params = [])
            {
                $this->calls++;
                if ($this->calls === 1) {
                    return new class {
                        public function fetch_assoc()
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
            private $calls = 0;
            public $error = 'fail';
            public function execute_query($sql, $params = [])
            {
                $this->calls++;
                if ($this->calls === 1) {
                    return new class {
                        public function fetch_assoc()
                        {
                            return ['mtce' => 1];
                        }
                    };
                }
                return new class {
                    public function fetch_assoc()
                    {
                        return ['admin' => 1];
                    }
                };
            }
        };

        $this->assertSame(2, AdminSettings::checkMaintenanceMode(1, $db, $this->appConfig));

        $db = new class {
            private $calls = 0;
            public $error = 'fail';
            public function execute_query($sql, $params = [])
            {
                $this->calls++;
                if ($this->calls === 1) {
                    return new class {
                        public function fetch_assoc()
                        {
                            return ['mtce' => 1];
                        }
                    };
                }
                return new class {
                    public function fetch_assoc()
                    {
                        return ['admin' => 0];
                    }
                };
            }
        };

        $this->assertSame(1, AdminSettings::checkMaintenanceMode(1, $db, $this->appConfig));
    }
}
