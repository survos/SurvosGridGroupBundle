<?php declare(strict_types=1);
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\TestWithJson;
final class ExampleTest extends TestCase
{
    #[DataProvider('additionProvider')]
    #[TestDox('Adding $a to $b results in $expected')]
    public function testAdd(int $expected, int $a, int $b)
    {
        $this->assertSame($expected, $a + $b);
    }

    #[TestWithJson('[0, 0, 0]')]
    #[TestWithJson('[0, 1, 1]')]
    #[TestWithJson('[1, 0, 1]')]
    #[TestWithJson('[1, 1, 3]')]
    public function jsonTest(int $a, int $b, int $expected): void
    {
        $this->assertSame($expected, $a + $b);
    }

    public static function additionProvider()
    {
        return [
            'data set 1' => [0, 0, 0],
            'data set 2' => [2, 1, 1],
            'data set 3' => [1, 0, 1],
            'data set 4' => [4, 1, 3]
        ];
    }
}
