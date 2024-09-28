<?php

namespace Tests\Unit\Mocks;

use Exception;
use Tests\App\Request\RequestValidation;
use PHPUnit\Framework\TestCase;

class RequestValidationTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testOptionalFields(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
        ];

        $request = new class ($data) extends RequestValidation {
            /**
             * @return string[]
             *
             * @psalm-return array{name: 'required|string', email: 'required|email', age: 'integer'}
             */
            public function rules(): array
            {
                return [
                    'name' => 'required|string',
                    'email' => 'required|email',
                    'age' => 'integer', // Optional field
                ];
            }
        };

        $request->validate();
        $this->assertEmpty($request->errors());

        // Validate the validated() method returns only the validated fields
        $this->assertEquals($data, $request->validated());
    }

    public function testInvalidEmail(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
        ];

        $request = new class ($data) extends RequestValidation {
            /**
             * @return string[]
             *
             * @psalm-return array{name: 'required|string', email: 'required|email'}
             */
            public function rules(): array
            {
                return [
                    'name' => 'required|string',
                    'email' => 'required|email',
                ];
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Validation failed: {"email":["email must be a valid email address."]}');
        $request->validate();

        // Ensure validated fields exclude the invalid 'email' field
        $this->assertEquals(['name' => 'John Doe'], $request->validated());
    }

    public function testMissingRequiredField(): void
    {
        $data = [
            'email' => 'johndoe@example.com',
        ];

        $request = new class ($data) extends RequestValidation {
            /**
             * @return string[]
             *
             * @psalm-return array{name: 'required|string', email: 'required|email'}
             */
            public function rules(): array
            {
                return [
                    'name' => 'required|string',
                    'email' => 'required|email',
                ];
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('{"name":["name is required.","name must be a string."]}');
        $request->validate();
    }

    public function testInvalidIntegerField(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'age' => 'not-an-integer',
        ];

        $request = new class ($data) extends RequestValidation {
            /**
             * @return string[]
             *
             * @psalm-return array{name: 'required|string', email: 'required|email', age: 'integer'}
             */
            public function rules(): array
            {
                return [
                    'name' => 'required|string',
                    'email' => 'required|email',
                    'age' => 'integer',
                ];
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Validation failed: {"age":["age must be an integer."]}');
        $request->validate();

        // Ensure validated fields exclude the invalid 'age' field
        $this->assertEquals([
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
        ], $request->validated());
    }

    public function testMultipleValidationErrors(): void
    {
        $data = [
            'name' => '', // triggers "name is required" and possibly "name must be a string"
            'email' => 'invalid-email', // triggers "email must be a valid email address"
            'age' => 'not-an-integer', // triggers "age must be an integer"
        ];

        $request = new class ($data) extends RequestValidation {
            /**
             * @return string[]
             *
             * @psalm-return array{name: 'required|string', email: 'required|email', age: 'integer'}
             */
            public function rules(): array
            {
                return [
                    'name' => 'required|string',  // Two rules for the name field
                    'email' => 'required|email',  // Two rules for the email field
                    'age' => 'integer',           // One rule for the age field
                ];
            }
        };

        // Since validation stops at first error for "name", expect only the first error for "name"
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            '{"name":["name is required."],' .
            '"email":["email must be a valid email address."],' .
            '"age":["age must be an integer."]}'
        );
        $request->validate();
    }
}
