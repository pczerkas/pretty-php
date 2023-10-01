<?php declare(strict_types=1);

class MyClass
{
    public Countable&ArrayAccess $Foo;

    public string|MyClass|null $Bar;

    public const MY_CONSTANT = 'my constant';

    public function MyMethod(
        $mixed,
        ?int $nullableInt,
        string $string,
        Countable&ArrayAccess $intersection,
        MyClass $class,
        ?MyClass $nullableClass,
        ?MyClass &$nullableClassByRef,
        ?MyClass $nullableAndOptionalClass       = null,
        string $optionalString                   = MyClass::MY_CONSTANT,
        string|MyClass $union                    = SELF::MY_CONSTANT,
        string|MyClass|null $nullableUnion       = 'literal',
        array|MyClass $optionalArrayUnion        = ['key' => 'value'],
        string|MyClass|null &$nullableUnionByRef = null,
        string&...$variadicByRef
    ): MyClass|string|null {
        return null;
    }

    public function foo(): Countable&ArrayAccess
    {
        return $this->Foo;
    }

    public function bar(): string|MyClass|null
    {
        return $this->Bar;
    }

    public function qux(string|MyClass|null $qux, Countable&ArrayAccess $quux)
    {
        //
    }

    public function quux(Countable&ArrayAccess $quux, string|MyClass|null $qux)
    {
        //
    }
}
