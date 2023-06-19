<?php

// this is really the CsvTable, the database is more like the GridGroup

// likely due for a refactoring, especially when PHP support sqlite 3.38+, which has JSON support

namespace Survos\GridGroupBundle\Service;

use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use SplFileObject;
use SplTempFileObject;
use Survos\GridGroupBundle\CsvSchema\Parser;
use Survos\GridGroupBundle\Model\Property;
use Survos\GridGroupBundle\Service\CountableArrayCache;
use Survos\GridGroupBundle\Service\Reader;
use Symfony\Component\String\Slugger\AsciiSlugger;
use League\Csv\Reader as LeagueCsvReader;

class GridDatabase implements LoggerAwareInterface
{
    use LoggerAwareTrait;


    public function __construct(
        private string  $filename,
        private ?string $keyName = null,
        private array   $headers = [],
        private bool $purge = false,
        private bool    $useGZip = false,
        private ?Reader $reader = null,
        private array   $schema = [], // an ordered list of properties, derived from headers
    )
    {
        $this->offsetCache = new CountableArrayCache();
        $this->aliases = new CountableArrayCache();
        if ($this->headers) {
            $this->setSchema($this->headers);
//            assert(false, "are we using headers anywhere?  can we use schema config?");
//            dd($headers, $this->schema);
        }
        if ($this->purge) {
            $this->reset();
        } else {
            $this->refresh();
        }
        // if headers were passed in, it's the schema.
//        $this->headers=[];
//        foreach ($this->schema as $code=>$property) {
//            $this->headers[] = $code;
//        }
    }

    public function setSchema(array $headers): array
    {
        $this->schema = [];
        foreach ($headers as $dottedConfig) {
            $property = Parser::parseConfigHeader($dottedConfig);
            $this->schema[$property->getCode()] = $property;
            // hackish, we should leverage the Schema class
            if ($property->getSubType()=='code') {
                $this->setKeyName($property->getCode());
            }
        }
        return $this->schema;
    }

    // for debugging
    public function getOffsetCache(): CountableArrayCache
    {
        return $this->offsetCache;
    }

    public function reset(): self
    {
        $filename = $this->getPath();
        if (file_exists($filename)) {
            unlink($filename);
        }
        $this->refresh();
        return $this;
    }

    public function refresh()
    {
        $this->offsetCache->flush();
        $this->aliases->flush();
        $this->headers = [];

        if (file_exists($this->filename)) {
            $this->reader = new Reader($this->getFilename());
            $this->setHeaders($this->reader->getHeaders());
        }

        if (!is_null($this->keyName)) {
            if (!in_array($this->keyName, $this->headers)) {
//                array_unshift($this->headers, $this->keyName);
            }
        }
    }

    /**
     * @return bool
     */
    public function isUseGZip(): bool
    {
        return $this->useGZip;
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * @param string $filename
     * @return
     */
    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getKeyName(): ?string
    {
        return $this->keyName;
    }

    static public function calculateKey($source): string
    {
        return hash('xxh3', $source);
    }

    /**
     * @param string $keyName
     * @return CsvDatabase
     */
    public function setKeyName(string $keyName): self
    {
        $this->keyName = $keyName;
        return $this;
    }

    /**
     * @return string|false|int
     */
    public function getKeyAlias(): string|false|int
    {
        return array_search($this->getKeyName(), $this->aliases->getCache());
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeaders(): bool
    {
        return count($this->headers) > 0;
    }

    /**
     * @param array $headers
     * @return CsvDatabase
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    public function addHeader(string $label): self
    {
//        assert(false);
        $slugger = new AsciiSlugger();
        $header = $slugger->slug($label)->toString();
        $this->headers[] = $header;
        if ($header <> $label) {
            $this->addAlias($label, $header);
        }

        $tempCsv = $this->createCopy();
        $this->flushFile();

        foreach ($tempCsv->readFromFile() as $row) {
            $this->appendToFile($this->getKeyName(), $row);
        }

        $tempCsv->purge();

        return $this;
    }

    public function addAlias(string $inputKey, string $header): self
    {
        $this->aliases->set($inputKey, $header);
        return $this;
    }

    /**
     * Append a line to the database file.
     *
     * @param string $key
     * @param array $data
     * @throws Exception
     */
    public function appendToFile(string $key, array $data): void
    {
        $headers = $this->getHeaders();
        $file = $this->openFile(static::FILE_APPEND);
        $file->fseek(0, SEEK_END);
        $pos = $file->ftell();
        $emptyFile = $pos == 0;
        $keyName = $this->getKeyName();

        if (empty($data[$keyName])) {
            assert(false, "data must contain key " . $keyName);
            $data = [$keyName => $key] + $data;
        }

        // if it's empty, first write the headers
        if (count($headers) <= 1) {
            $headers = array_keys($data);
            $this->setHeaders($headers);
        }

        if (($pos == 0)) {
            // rewriting the database needs this too!
            $schemaHeaders = array_map(fn(Property $property) => $property->__toString(), array_values($this->schema));
//            dd($headers, $schemaHeaders, $this->schema);
            $file->fputcsv($headers);
//            $file->fputcsv($schemaHeaders);
        }

        // if no headers, then use the headers of the first row passed in.
        if (array_keys($data) <> $headers) {
            // fix the order if the data keys don't match the headers
            $dataValues = [];
            foreach ($headers as $header) {
                $dataValues[] = $data[$header] ?? null;
            }
        } else {
            $dataValues = array_values($data);
        }

        // before writing the data, save the position in the offset cache.
        $position = $file->ftell();

        assert(array_key_exists($this->getKeyName(), $data), json_encode($data));
        $this->offsetCache->set($key, $position);

        $file->fputcsv($dataValues);
        $this->currentSize = $file->ftell();
        $this->closeFile($file);

        if ($emptyFile) {
            $this->refresh();
        }

    }

    public function getSize(): int
    {
        return $this->currentSize;
    }

    /**
     * Read lines from the database file.
     *
     * @return \Generator
     */
    public function readFromFile(): \Generator
    {
        if (file_exists($this->getPath())) {
            $reader = \League\Csv\Reader::createFromPath($this->getPath())->setHeaderOffset(0);
//            $reader = new Reader($this->getPath());
            foreach ($reader->getRecords() as $data) {
                yield $data;
            }
        }
    }

    public function calculateCount(): ?int
    {
        $existingFile = $this->getPath();
        if (file_exists($existingFile)) {
            $reader = (new Reader($existingFile, strict: true))->getCsvCount();
        }
        return null;
    }

    public function keyOffset(string $key): ?int
    {
        if ($this->offsetCache->count() == 0) {
            $this->loadOffsetCache();
        }
        return $this->offsetCache->contains($key) ? $this->offsetCache->get($key) : null;
    }

    /**
     * @param array $data
     * @return void
     * @throws \League\Csv\CannotInsertRecord
     * @throws \League\Csv\Exception
     * @throws \League\Csv\UnavailableStream
     */
    public function checkIfNewHeadersProvidedAndAddThemIfSo(array $data): void
    {
        if (file_exists($this->getFilename()) && $diff = array_diff(array_keys($data), $this->getHeaders())) {
//            if (file_exists($this->getFilename()) && !empty($diff)) {
            if (!empty($diff)) {
                $existingReader = \League\Csv\Reader::createFromPath($this->getFilename());
                $writer = \League\Csv\Writer::createFromPath($newFilename = $this->getFilename() . '-new', 'w+');
//                assert($this->logger, "call setLogger()");
                if ($this->logger) {
                    $this->logger->warning("adding headers " . join(',', $diff) . ' to ' . $this->getFilename());
                }
                $newRows = [];
                foreach ($existingReader->getIterator() as $idx => $row) {

                    if ($idx == 0) {
                        $newHeaders = array_merge($row, $diff);
//                        dd($newHeaders);
                        $writer->insertOne($newHeaders);
                    } else {
                        $row = array_pad($row, count($newHeaders), json_encode($diff));
                        assert(count($row) == count($newHeaders));
                        $newRows[] = $row;
                    }
                }
                $writer->insertAll($newRows); // also does flush

                // remove the original and rename the new one.
                unlink($this->getFilename());
                rename($newFilename, $this->getFilename());
                $this->refresh();

//                dd($diff, 'need to rewrite ' . $this->getFilename());
//                foreach ($diff as $header) {
//                    if (file_exists($this->getFilename())) {
//                        $this->addHeader($header);
//                    } else {
//                        $this->headers[] = $header;
//                    }
//                }
            }
        }
    }

    public function getReader(): Reader
    {
        static $readers = [];
        $path = $this->getPath();
        if (!array_key_exists($path, $readers)) {
            $readers[$path] = new Reader($this->getPath());
        }
        return $readers[$path];

    }

    public function loadOffsetCache()
    {

//        assert(count($this->headers), "missing headers");
        $existingFile = $this->getPath();
        if (file_exists($existingFile)) {
            $reader = new Reader($existingFile, strict: false);
            foreach ($reader->getRow() as $row) {
//            foreach ($reader->getRecords() as $row) {
//                dd($row, $existingFile);
                try {
                    // this could be a trait, too.
//                    AppService::assertKeyExists($this->getKeyName(), $row);
                } catch (\Exception) {
                    // only during dev
                }
                GridGroupService::assertKeyExists($this->getKeyName(), $row, $this->getFilename());
//                dd($row, array_keys($row), $this->getKeyName());
                $this->offsetCache->set($row[$this->getKeyName()], $reader->getRowOffset());
            }
        }
    }


    /**
     * Get the database name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the database name.
     *
     * @param string $name
     *
     * @throws Exception
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * Get the path to the database file.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->getFilename();
    }

    /**
     * Open the database file.
     *
     * @param int $mode
     *
     * @return SplFileObject
     * @throws Exception
     *
     */
    protected function openFile(int $mode): SplFileObject
    {
        $path = $this->getPath();

        if (!is_file($path) && !@touch($path)) {
            throw new Exception('Could not create file: ' . $path);
        }

        if (!is_readable($path) || !is_writable($path)) {
            throw new Exception('File does not have permission for read and write: ' . $path);
        }

        if ($this->isUseGZip()) {
            assert(false, 'gzip not tested.');
            $path = 'compress.zlib://' . $path;
        }

        $res = $this->fileAccessMode[$mode];
        $file = new SplFileObject($path, $res['mode']);

        if ($mode === self::FILE_READ) {
            $file->setFlags(SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        }

        if (!$this->isUseGZip() && !$file->flock($res['operation'])) {
            $file = null;
            throw new Exception('Could not lock file: ' . $path);
        }

        return $file;
    }

    /**
     * Open a temporary file.
     *
     * @return SplTempFileObject
     */
    public function openTempFile(): SplTempFileObject
    {
        return new SplTempFileObject();
    }

    public function delete(string $key): self
    {
        $offset = $this->keyOffset($key);

        $tempFile = $this->openTempFile();
        $file = $this->openFile(static::FILE_READ);

        $firstPart = $file->fread($offset);

        $file->rewind();
        while (!$file->eof()) {
            $file->next();
        }
        $endOfFile = $file->ftell();

        $file->rewind();
        $file->fseek($offset);
        $file->next();

        $secondPart = $file->fread($endOfFile - $offset);

        $tempFile->fwrite($firstPart);
        $tempFile->fwrite($secondPart);

        $this->closeFile($file);

        $tempFile->rewind();
        $this->writeTempToFile($tempFile);

        $this->loadOffsetCache();
        return $this;
        // get the file from the beginning to the offset.  Fetch the line (to move the offset), then get the rest of the file.
    }

    /**
     * Close the database file.
     *
     * @param SplFileObject $file
     *
     * @throws Exception
     */
    protected function closeFile(SplFileObject &$file)
    {
        if (!$this->isUseGZip() && !$file->flock(LOCK_UN)) {
            $file = null;
            throw new Exception('Could not unlock file');
        }

        $file = null;
    }


    /**
     * Flush the database file.
     */
    public function flushFile()
    {
        $file = $this->openFile(static::FILE_WRITE);
        $this->closeFile($file);
    }

    /**
     * Write temporary file contents to database file.
     *
     * @param SplTempFileObject $tmpFile
     * @throws Exception
     */
    public function writeTempToFile(SplTempFileObject &$tmpFile)
    {
        $file = $this->openFile(static::FILE_WRITE);

        foreach ($tmpFile as $line) {
            $file->fwrite($line);
        }

        $this->closeFile($file);
        $tmpFile = null;
    }

    /**
     * Get a key from the database.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key): mixed
    {
        // Fetch the offset
        if ($position = $this->keyOffset($key)) {
//            assert($this->reader, $this->getFilename());
            $this->reader->setCurrentBufferPosition($position);
            $row = $this->reader->getCsvRow();
            $headers = $this->getHeaders();

//            if (empty($this->schema) && empty($headers)) {
//                $this->setSchema(array_keys($data));
//            }
//            $headers = array_keys($this->schema);
            assert(count($headers) == count($row),
                json_encode($row) .
                sprintf("\nmismatch at %d %d headers %d columns\n%s",
                    $position,
                    count($headers), count($row), $this->getFilename()));
            // @todo: pass through parser to get correct types
            return array_combine($headers, $row);
            // @todo: move the buffer pointer using fseek and get the record there.
        }
        return null;
        assert(false, "maybe call has before get?  data does not exist");

        // Fetch the key from database
        $file = $this->readFromFile();
        $data = false;

        foreach ($file as $row) {
            if ($row[$this->getKeyName()] == $key) {
                $data = $row;
                break;
            }
        }

        return $data;
    }

    public function has(string|array $key): bool
    {
        // if we know the key name, we can simply pass the whole array
        if (is_array($key)) {
            $key = $key[$this->getKeyName()];
        }
        $keyOffset = $this->keyOffset($key);
        return !is_null($keyOffset); // 0 is a valid offset, though realistically that will always be the headers.
    }

    /**
     * @param string|null $key
     * @param array $data
     * @return CsvDatabase
     * @throws Exception
     */
    public function set(array|object|null $data = null, string $key = null): self
    {
        if (!$key) {
//            $key = $this->getKeyName();
        }
//        assert($key = $this->getKeyName());

//        if (is_array($key)) {
//            assert(!array_is_list($key), "Must pass a hash as key, not a list");
//            $data = $key;
//            assert($this->getKeyName(), "missing keyname in " . $this->getFilename());
//            $key = $data[$this->getKeyName()];
//        } else {
//            if ($this->getKeyName()) {
//                assert($key = $this->getKeyName());
//            } else {
//                if (is_string($key)) {
//                    $this->setKeyName($key);
//                }
//            }
//            // throw deprecation?
//        }
//        assert($key <> 'inscription', "Bad key " . $key);
        // Check if keyName was set and if not search for id field in data array

        if (!$key) {
            // we need a keyname.
            if (!$keyName = $this->getKeyName()) {
                foreach (array_keys($data) as $item) {
                    if (preg_match('/id$/i', $item)) {
                        $this->setKeyName($item);
                        break;
                    }
                }
            }
        }

        if (!$this->getKeyName()) {
            throw new Exception("You need to set 'keyName' parameter or provide id field in data array!");
        }


        assert(array_key_exists($this->getKeyName(), $data), sprintf("Missing key %s in %s", $this->getKeyName(), $this->getFilename()));
        $keyValue = $data[$this->getKeyName()];

        if (!$this->hasHeaders()) {
            $this->setHeaders(array_keys($data));
        }

        // Check if new headers provided and add them if so
        $this->checkIfNewHeadersProvidedAndAddThemIfSo($data);

        // If the key already exists we need to replace it
        if ($this->has($keyValue)) {
            $this->replace($keyValue, $data);
            return $this;
        }

        // Write the key to the database
        $this->appendToFile($keyValue, $data);
        return $this;
    }

    /**
     * Replace a key in the database.
     *
     * @param string $key
     * @param mixed $data
     * @return CsvDatabase
     * @throws Exception
     */
    public function replace(string $key, mixed $data): self
    {
        // better way is to get the current key, copy the file up to the offset, insert the key, grab the rest of the file, reload offsets.

        //
        // Write a new database to a temporary file
        $tmpFile = $this->openTempFile();
        $file = $this->readFromFile();

        $tmpFile->fputcsv($this->getHeaders());
        foreach ($file as $row) {
            if ($row[$this->getKeyName()] == $key) {
                if ($data !== false) {
                    $tmpFile->fputcsv($data);
                }
            } else {
                $tmpFile->fputcsv($row);
            }
        }

        $tmpFile->rewind();

        // Overwrite the database with the temporary file
        $this->writeTempToFile($tmpFile);
        return $this;
    }

    /**
     * Resets database file
     *
     * @return bool
     */
    public function purge(): bool
    {
        if (file_exists($this->getPath())) {
            unlink($this->getPath());
        }

        return true;
    }

    /**
     * Create a copy of this csv database file
     *
     * @return self
     */
    protected function createCopy(): self
    {
        $tempFileName = md5(time() . $this->filename) . '.csv';
        copy($this->getFilename(), $tempFileName);

        return new self($tempFileName, $this->keyName, $this->headers, $this->useGZip);
    }

}
