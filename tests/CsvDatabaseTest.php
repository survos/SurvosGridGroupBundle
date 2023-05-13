<?php

namespace Survos\GridGroupBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\GridGroupBundle\Service\CsvDatabase;
use Symfony\Component\Yaml\Yaml;

class CsvDatabaseTest extends TestCase
{
    /**
     * @dataProvider csvSteps
     */
    public function testCsvDatabase(array $test): void
    {
        $csvDatabase = new CsvDatabase($test['db'], $test['key'], $test['headers'] ?? []);
        $csvDatabase->flushFile(); // purge?  reset? We need to start with a clean file.
        $csvDatabase->purge();
        foreach ($test['steps'] as $step) {
            $key = $step['key'] ?? null;
            $data = $step['data'] ?? [];
            $expects = $step['expects'] ?? null;
            $csv = $step['csv'] ?? null;
            $actual = match ($operation = $step['operation']) {
                'has' => $csvDatabase->has($key),
                'get' => $csvDatabase->get($key),
                'delete' => $csvDatabase->delete($key),
                'replace' => $csvDatabase->replace($key, $data),
                'set' => $csvDatabase->set($key, (array)$data),
//                'add_headers' => $csvDatabase->setHeaders(),

                default =>
                assert(false, "Operation not supported " . $operation)
            };
            if (!is_null($expects)) {
                $this->assertSame($expects, $actual);
            }
            if (!is_null($csv)) {
                $this->assertSame($csv, file_get_contents($csvDatabase->getFilename()));
            }
        }
    }

    public static function csvSteps()
    {
        $data = Yaml::parseFile(__DIR__ . '/movie-test.yaml');
        foreach ($data['cache'] as $test) {
            yield [$test['db'] => $test];
        }
    }
}