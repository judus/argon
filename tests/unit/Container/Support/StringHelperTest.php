<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support {
    if (!function_exists(__NAMESPACE__ . '\\preg_replace')) {
        /**
         * @param array<int, non-empty-string>|non-empty-string $pattern
         * @param array<int, string>|string                     $replacement
         * @param array<int, string>|string                     $subject
         * @return array<int, string>|string|null
         * @psalm-suppress ArgumentTypeCoercion
         */
        function preg_replace(
            array|string $pattern,
            array|string $replacement,
            array|string $subject,
            int $limit = -1,
            ?int &$count = null
        ): array|string|null {
            if ($subject === '__force_failure__') {
                return null;
            }

            /** @psalm-suppress PossiblyInvalidArgument */
            return \preg_replace($pattern, $replacement, $subject, $limit, $count);
        }
    }
}

namespace Tests\Unit\Container\Support {

    use Maduser\Argon\Container\Exceptions\ContainerException;
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

        /**
         * @throws ContainerException
         */
        public function testSanitizeIdentifierExposed(): void
        {
            $this->assertSame('foo_bar', StringHelper::sanitizeIdentifier('foo-bar'));
        }

        public function testSanitizeIdentifierThrowsWhenPregReplaceFails(): void
        {
            $this->expectException(ContainerException::class);
            $this->expectExceptionMessage('Failed to sanitize identifier: __force_failure__');

            StringHelper::sanitizeIdentifier('__force_failure__');
        }
    }

}
