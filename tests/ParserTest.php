<?php

namespace Survos\GridGroupBundle\Tests;

use League\Csv\Reader;
use PHPUnit\Framework\TestCase;
use Survos\GridGroupBundle\CsvSchema\Parser;
use Symfony\Component\Yaml\Yaml;

use function PHPUnit\Framework\assertEquals;

class ParserTest extends TestCase
{
    public function testParser()
    {
        $yaml = Yaml::parseFile(__DIR__ . '/parser-test.yaml');
        foreach ($yaml['tests'] as $test) {
            $csvString = $test['source'];
            $csvReader = Reader::createFromString($csvString)->setHeaderOffset(0);
            $schema = Parser::createSchemaFromMap($test['map'] ?? [], $csvReader->getHeader());
            $config['schema'] = $schema;
            $parser = new Parser($config);
            foreach ($parser->fromString($csvString) as $row) {
                $expects = json_decode($test['expects'], true);
                assert($expects, "invalid json: " . $test['expects']);
                assertEquals($expects, $row, json_encode($expects) . '<>' . json_encode($row));
            }
        }

    }

    public static function csvTests()
    {
        $data = Yaml::parseFile(__DIR__ . '/parser-test.yaml');
        foreach ($data['tests'] as $key => $test) {
            yield [$key => $test];
        }
    }
}
