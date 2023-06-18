<?php

namespace Survos\GridGroupBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\GridGroupBundle\Service\CsvDatabase;
use Survos\GridGroupBundle\Service\GridGroupService;
use Symfony\Component\Yaml\Yaml;

class CsvDatabaseTest extends TestCase
{
    /**
     * @dataProvider csvSteps
     */
    public function testCsvDatabase(array $test): void
    {
        $purgeFirst = !isset($test['ignore_clear_db']);
        $headers = $test['headers'] ?? []; // really the schema
        $csvDatabase = new CsvDatabase($test['db'], $test['key'] ?? null,
            $headers,
            purge: $purgeFirst
        );
        if ($purgeFirst) {
            assert(!file_exists($csvDatabase->getFilename()));
            if (!count($headers)) {
                assert(empty($csvDatabase->getHeaders()));
            }
        }

        foreach ($test['steps'] as $stepIndex => $step) {
            foreach ($this->applyStep($step, $stepIndex, $csvDatabase) as $msg => $info) {
                [$expects, $actual, $errorMessage] = $info;
//                dd($msg, $info, $expected, $actual);
                $this->assertSame($expects, $actual, $errorMessage);
            }
        }

        if (!isset($test['ignore_flush_db_after'])) {
            $csvDatabase->flushFile();
            $csvDatabase->purge();
        }
    }

    public static function csvSteps()
    {
        $data = Yaml::parseFile(__DIR__ . '/movie-test.yaml');
        foreach ($data['cache'] as $test) {
            yield [$test['db'] => $test];
        }
    }

    /**
     * @param mixed $step
     * @param int|string $stepIndex
     * @param string $csvFilename
     * @param CsvDatabase $csvDatabase
     * @return void
     * @throws \Exception
     */
    private function applyStep(array $step, int|string $stepIndex, CsvDatabase $csvDatabase): \Iterator
    {
        $csvFilename = $csvDatabase->getFilename();

        $key = $step['key'] ?? null;
        $data = $step['data'] ?? [];
        $msg = $step['msg'] ?? "Step # " . $stepIndex . ' ' . $csvFilename;
        $operation = $step['operation'];
        if (isset($step['expects'])) {
            $expectedOperationResult = $step['expects']; // could be null
        }
        if ($expectedCsvContentsAfterOperation = $step['csv'] ?? null) {
            $expectedCsvData = GridGroupService::csvToArray($expectedCsvContentsAfterOperation);
        }
        if (file_exists($csvFilename)) {
//            dump('BEFORE ' . $msg . '/' . $operation, file_get_contents($csvDatabase->getFilename()), $csvDatabase->getFilename());
        } else {
//                dump("$csvFilename does not exist.");
        }

        $operationResult = match ($operation) {
            'has' => $csvDatabase->has($key),
            'get' => $csvDatabase->get($key),
            'delete' => $csvDatabase->delete($key),
            'replace' => $csvDatabase->replace($key, $data),
            'set' => $csvDatabase->set($data),
            'add_header' => $csvDatabase->addHeader($step['header']),
            'get_key_alias' => $csvDatabase->getKeyAlias(),
            'get_key_name' => $csvDatabase->getKeyName(),
            'headers' => $csvDatabase->getHeaders(),

            default =>
            assert(false, "Operation not supported " . $operation)
        };
        if (isset($expectedOperationResult)) {
            yield $msg => [$expectedOperationResult, $operationResult,
                sprintf("%s\n%s(%s, %s) == %s (got %s)",
                    $msg,
                    json_encode($operation),
                    json_encode($key),
                    !empty($data) ? ',' . json_encode($data) : '',
                    is_array($expectedOperationResult) ? json_encode($expectedOperationResult) : json_encode($expectedOperationResult),
                json_encode($operationResult)
                )
                ];
        }

//        if (file_exists($csvFilename)) {
//            dump(step: $stepIndex, contents: file_get_contents($csvFilename), filename: $csvFilename);
//        }

        if (is_null($expectedCsvContentsAfterOperation) && !isset($expectedOperationResult)) {
            dd(msg: 'no tests', step: $step, isset: isset($expectedOperationResult));
        }
        // the expected csv data
        if (!is_null($expectedCsvContentsAfterOperation)) {
            $actualCsvFileDataAfterOperation = GridGroupService::csvToArray($csvDatabase->getFilename(), true);
            yield $msg => [$expectedCsvData, $actualCsvFileDataAfterOperation, sprintf(
                    "Operation %s:\n%s\nexpected:\n%s\noperationResult:\n%s",
                    $operation,
                    Yaml::dump($data),
                    Yaml::dump($expectedCsvData),
                    Yaml::dump($actualCsvFileDataAfterOperation),
                )
                ];
//                    . "\n#" . $stepIndex .
//                    " " . $operation .
//                    ' ' . $csvFilename . "\nOperation $operation with \n" .
//                    Yaml::dump($data) . "\n\nexpecting: " .
//                    $expectedCsvContentsAfterOperation . "\ngot:\n" .
//                    file_get_contents($csvDatabase->getFilename())
//            );
        }
    }
}
