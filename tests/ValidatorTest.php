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

    public function testCustomRulesAndMessages(): void
    {
        $validator = Validator::make(
            ['code' => 'abc123'],
            ['code' => 'starts_with_ab'],
        )->extend(
            'starts_with_ab',
            static fn (mixed $value): bool => is_string($value) && str_starts_with($value, 'ab'),
            'Code must start with ab.',
        );

        self::assertTrue($validator->passes());

        $failing = Validator::make(['code' => 'zz'], ['code' => 'starts_with_ab'])
            ->extend(
                'starts_with_ab',
                static fn (mixed $value): bool => is_string($value) && str_starts_with($value, 'ab'),
                'Code must start with ab.',
            );

        self::assertSame(['code' => ['Code must start with ab.']], $failing->errors());
    }

    public function testDottedFields(): void
    {
        $validator = Validator::make(
            ['user' => ['email' => 'bad']],
            ['user.email' => 'required|email'],
        );

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('user.email', $validator->errors());
    }

    public function testAdditionalRules(): void
    {
        self::assertTrue(Validator::make(['id' => '550e8400-e29b-41d4-a716-446655440000'], ['id' => 'uuid'])->passes());
        self::assertTrue(Validator::make(['url' => 'https://kurama.test'], ['url' => 'url'])->passes());
        self::assertTrue(Validator::make(['json' => '{"a":1}'], ['json' => 'json'])->passes());
        self::assertTrue(Validator::make(['code' => 'AB12'], ['code' => 'alpha_num'])->passes());
        self::assertTrue(Validator::make(['n' => 5], ['n' => 'between:1,10'])->passes());
        self::assertTrue(Validator::make(['a' => 'x', 'b' => 'y'], ['a' => 'different:b'])->passes());
        self::assertTrue(Validator::make(['role' => 'admin'], ['role' => 'required_if:type,admin'])->passes());
        self::assertFalse(Validator::make(['code' => 'a1'], ['code' => 'alpha'])->passes());
    }

    public function testUnknownRuleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::make(['x' => 1], ['x' => 'unknown'])->passes();
    }
}
