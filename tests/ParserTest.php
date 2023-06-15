<?php

namespace Survos\GridGroupBundle\Tests;

use League\Csv\Reader;
use PHPUnit\Framework\TestCase;
use Survos\GridGroupBundle\CsvSchema\Parser;
use Survos\GridGroupBundle\Model\Property;
use Symfony\Component\Yaml\Yaml;

use function PHPUnit\Framework\assertEquals;

class ParserTest extends TestCase
{
    /**
     * @dataProvider csvTests
     */
    public function testParser(array $test)
    {
        $key = $test['name'];
            $csvString = $test['source'];
            $csvReader = Reader::createFromString($csvString)->setHeaderOffset(0);
            $config = Parser::createConfigFromMap($test['map'] ?? [], $csvReader->getHeader());
            $config['valueRules'] = $test['valueRules'] ?? [];
            $parser = new Parser($config);

            $expectsJson = $test['expects'] ?? null;

            foreach ($parser->fromString($csvString) as $actual) {
                $expects = json_decode($expectsJson, true);
                assert($expects, "invalid json string: " . $expectsJson . ' in ' . $key);
                $this->assertSame($expects, $actual, json_encode($expects) . '<>' . json_encode($actual));
                assert($expects, "invalid json: " . $test['expects']);
            }

            if ($expectedSchema = $test['schema']??false) {
                dd($config, $expectedSchema);
                assert(is_array($expectedSchema), "$key: invalid json string: " . json_encode($expectedSchema));

//                $expects = json_decode($expectedSchema, true);
//                assert($expects, "invalid json string: " . $expectsJson);
                $this->assertSame($expects, $actual, json_encode($expects) . '<>' . json_encode($actual));
                assert($expects, "invalid json: " . $test['expects']);

            }

    }

    private function compareProperties(Property $expected, Property $actual)
    {
        $this->assertEquals(
            $expected,
            $actual,
            $actual->__toString() . "\n" . $expected
        );

    }

    /**
     * @dataProvider parserTests
     */
    public function testDottedConfig(string $dottedConfig, Property $property)
    {

        $this->assertEquals(
            $property,
            $actual = Parser::parseConfigHeader($dottedConfig),
            'act:' . $actual->__toString() . "\n" . 'exp:' . $property
        );

    }



    /**
     * @return string[][]          // <-- added typehint here
     */
    public static function csvTests()
    {
        $data = Yaml::parseFile(__DIR__ . '/parser-test.yaml');
        foreach ($data['tests'] as $key => $test) {
            $key = $test['name']??$key;
            yield [$key => $test];
        }
    }

    public static function parserTests()
    {
        return [
            ['genre|', new Property(code: 'genre', type: Property::PROPERTY_ARRAY, settings: ['delim' => '|'])],
            ['actors,',new Property(code: 'actors', type: Property::PROPERTY_ARRAY, settings: ['delim' => ','])],
            ['label:db.label',new Property(code: 'label', type: Property::TYPE_DATABASE, subType: 'label')],
            ['header', new Property('header')],
            ['author:rel.per', new Property('author', Property::TYPE_RELATION, 'per')],
            // we need to pass the relative properties to the schema for this to work.
//            ['author:per', new Property('author', Property::TYPE_RELATION, 'per')],


            ['header:int', new Property('header', Property::TYPE_ATTRIBUTE,  Property::PROPERTY_INT)],
            ['header:att.int', new Property('header', Property::TYPE_ATTRIBUTE,  Property::PROPERTY_INT)],
            ['header:att.int?max=4', new Property('header', Property::TYPE_ATTRIBUTE,  Property::PROPERTY_INT, ['max' => 4])],
            ['header:rel.per', new Property('header', Property::TYPE_RELATION, 'per' )],
            ['header:rel.pla?rule=alpha2', new Property('header', Property::TYPE_RELATION, 'pla',['rule' => 'alpha2'] )],
        ];

    }

}
