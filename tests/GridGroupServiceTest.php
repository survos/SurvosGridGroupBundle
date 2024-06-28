<?php

namespace Survos\GridGroupBundle\Tests;

use Generator;
use Hoa\File\Read;
use PHPUnit\Framework\Attributes\DataProvider;
use Survos\GridGroupBundle\Service\GridGroupService;
use Survos\GridGroupBundle\Service\Reader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Yaml\Yaml;

class GridGroupServiceTest extends KernelTestCase
{
    #[DataProvider('steps')]
    public function testTrim(array $test): void
    {
        $result = GridGroupService::trim($test['raw'], $test['headerRegex'] ?? null);

        $this->assertSame($test['expected'], $result);
    }

    public static function steps(): Generator
    {
        $data = Yaml::parseFile(__DIR__ . '/excel-trim-test.yaml');
        foreach ($data['trim'] as $test) {
            yield ['test' => $test];
        }
    }
}
