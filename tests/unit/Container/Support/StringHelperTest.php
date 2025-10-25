<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Support;

use Maduser\Argon\Container\Support\StringHelper;
use PHPUnit\Framework\TestCase;

final class StringHelperTest extends TestCase
{
    public function testInvokeServiceMethodSanitizesProperly(): void
    {
        $result = StringHelper::invokeServiceMethod('App\\Service\\UserController', 'edit-user');

        $this->assertSame('invoke_App_Service_UserController__edit_user', $result);
    }

    public function testInvokeServiceMethodDefaultsToInvoke(): void
    {
        $result = StringHelper::invokeServiceMethod('My\\Cool\\Handler');

        $this->assertSame('invoke_My_Cool_Handler____invoke', $result);
    }

    public function testInvokeServiceMethodAllowsAlphaNumUnderscore(): void
    {
        $result = StringHelper::invokeServiceMethod('Test_123', 'someMethod_42');

        $this->assertSame('invoke_Test_123__someMethod_42', $result);
    }

    public function testSanitizeIdentifierExposed(): void
    {
        $this->assertSame('foo_bar', StringHelper::sanitizeIdentifier('foo-bar'));
    }
}
