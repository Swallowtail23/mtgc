<?php

/*
Version:     1.0
Date:        06/05/26
Name:        ProfileTwoFactorSecurityTest.php
Purpose:     Tests profile two-factor authentication security gates.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class ProfileTwoFactorSecurityTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(__DIR__ . '/../profile.php');
    }

    public function testTwoFactorProfileActionsRequireCsrf(): void
    {
        $this->assertStringContainsString('use MTG\\Auth\\SessionManager;', $this->source);
        $this->assertStringContainsString('SessionManager::generateCsrfToken()', $this->source);
        $this->assertStringContainsString('profileHasTwoFactorPostAction()', $this->source);
        $this->assertStringContainsString('profileHasValidCsrfToken()', $this->source);
        $this->assertStringContainsString('SessionManager::validateCsrfToken($posted)', $this->source);
        $this->assertStringContainsString('http_response_code(403);', $this->source);
    }

    public function testTwoFactorFormsRenderCsrfTokens(): void
    {
        $this->assertStringContainsString('name="csrf_token" value="<?php echo $csrfTokenEsc; ?>"', $this->source);
        $this->assertStringContainsString("name='csrf_token' value='{\$csrfTokenEsc}'", $this->source);
    }

    public function testDisableAndVerifyHandlersAreSkippedWhenCsrfFails(): void
    {
        $this->assertStringContainsString(
            "elseif (isset(\$_POST['disable_2fa']) && \$profileTwoFactorCsrfValid) :",
            $this->source
        );
        $this->assertStringContainsString(
            "elseif (isset(\$_POST['verify_2fa']) && \$profileTwoFactorCsrfValid) :",
            $this->source
        );
        $this->assertStringContainsString(
            "elseif (isset(\$_POST['regenerate_backup_codes']) && \$profileTwoFactorCsrfValid) :",
            $this->source
        );
    }

    public function testSetupCancelOnlyAppliesToUnverifiedAppProvisioning(): void
    {
        $this->assertStringContainsString("\$setupCancel", $this->source);
        $this->assertStringContainsString("\$canCancelSetup", $this->source);
        $this->assertStringContainsString("\$tfa_method === 'app'", $this->source);
        $this->assertStringContainsString("isset(\$_SESSION['tfa_provisioning_uri'])", $this->source);
        $this->assertStringContainsString("empty(\$_SESSION['2fa_verified'])", $this->source);
        $this->assertStringContainsString("unset(\$_SESSION['tfa_provisioning_uri']);", $this->source);
    }
}
