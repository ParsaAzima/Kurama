<?php

declare(strict_types=1);

namespace Kurama\Tests;

use InvalidArgumentException;
use Kurama\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testPassesAndReturnsValidatedFields(): void
    {
        $validator = Validator::make(
            ['name' => 'Kurama', 'email' => 'hi@kurama.test', 'age' => '42'],
            ['name' => 'required|string|min:3', 'email' => 'email', 'age' => 'integer'],
        );

        self::assertTrue($validator->passes());
        self::assertFalse($validator->fails());
        self::assertSame(
            ['name' => 'Kurama', 'email' => 'hi@kurama.test', 'age' => '42'],
            $validator->validated(),
        );
    }

    public function testErrorsAreGroupedByField(): void
    {
        $validator = Validator::make(
            ['name' => 'x', 'email' => 'bad'],
            ['name' => 'required|min:3', 'email' => 'email'],
        );

        self::assertTrue($validator->fails());
        $errors = $validator->errors();
        self::assertArrayHasKey('name', $errors);
        self::assertArrayHasKey('email', $errors);
        self::assertSame([], $validator->validated());
    }

    public function testInNumericMaxAndArrayRules(): void
    {
        self::assertTrue(Validator::make(['role' => 'admin'], ['role' => 'in:admin,user'])->passes());
        self::assertTrue(Validator::make(['n' => 5], ['n' => 'numeric|max:10'])->passes());
        self::assertTrue(Validator::make(['tags' => ['a']], ['tags' => 'array|min:1'])->passes());
        self::assertFalse(Validator::make(['role' => 'guest'], ['role' => 'in:admin,user'])->passes());
    }

    public function testConfirmedAndSameRules(): void
    {
        self::assertTrue(Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => 'confirmed'],
        )->passes());
        self::assertTrue(Validator::make(
            ['a' => 'x', 'b' => 'x'],
            ['a' => 'same:b'],
        )->passes());
    }

    public function testNullableSkipsOtherRules(): void
    {
        $validator = Validator::make(['name' => null], ['name' => 'nullable|string|min:3']);

        self::assertTrue($validator->passes());
    }

    public function testCustomMessage(): void
    {
        $validator = new Validator(
            ['name' => ''],
            ['name' => 'required'],
            ['name.required' => 'Name is mandatory.'],
        );

        self::assertSame(['name' => ['Name is mandatory.']], $validator->errors());
    }

    public function testUnknownRuleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::make(['x' => 1], ['x' => 'unknown'])->passes();
    }
}
