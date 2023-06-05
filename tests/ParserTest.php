<?php

namespace Survos\GridGroupBundle\Tests;

use League\Csv\Reader;
use PHPUnit\Framework\TestCase;
use Survos\GridGroupBundle\CsvSchema\Parser;
use Symfony\Component\Yaml\Yaml;

use function PHPUnit\Framework\assertEquals;

class ParserTest extends TestCase
{
    /**
     * @dataProvider csvTests
     */
    public function testParser(array $test)
    {
            $csvString = $test['source'];
            $csvReader = Reader::createFromString($csvString)->setHeaderOffset(0);
            $config = Parser::createConfigFromMap($test['map'] ?? [], $csvReader->getHeader());
            $config['valueRules'] = $test['valueRules'] ?? [];
            $parser = new Parser($config);

            $expectsJson = $test['expects'] ?? null;

            foreach ($parser->fromString($csvString) as $actual) {
                $expects = json_decode($expectsJson, true);
                assert($expects, "invalid json string: " . $expectsJson);
                $this->assertSame($expects, $actual, json_encode($expects) . '<>' . json_encode($actual));
                assert($expects, "invalid json: " . $test['expects']);
            }

            if ($expectedSchema = $test['schema']??false) {
                $expects = json_decode($expectedSchema, true);
                assert($expects, "invalid json string: " . $expectsJson);
                $this->assertSame($expects, $actual, json_encode($expects) . '<>' . json_encode($actual));
                assert($expects, "invalid json: " . $test['expects']);

            }

    }

    public function testDottedConfig()
    {
        $this->assertEquals(Parser::parseDottedConfig('header'), ['header', null]);
        $this->assertEquals(Parser::parseDottedConfig('header:int'), ['header', 'int']);
        $this->assertEquals(Parser::parseDottedConfig('header:rel.per'), ['header', 'rel.per']);

        $this->assertEquals(Parser::parseConfigHeader('header:rel.per'), ['header', 'rel.per', []]);
        $this->assertEquals(Parser::parseConfigHeader('header:rel.per?'), ['header', 'rel.per', []]);
        $this->assertEquals(
            $actual=Parser::parseConfigHeader('header:rel.per?max=10'),
            ['header', 'rel.per', ['max' => 10]],
            json_encode($actual));

    }



    public static function csvTests()
    {
        $data = Yaml::parseFile(__DIR__ . '/parser-test.yaml');
        foreach ($data['tests'] as $key => $test) {
            yield [$key => $test];
        }
    }
}
