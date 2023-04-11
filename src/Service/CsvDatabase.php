<?php

namespace Survos\GridGroupBundle\Service;

use App\Service\AppService;
use \Exception;
use SplFileObject;
use SplTempFileObject;
use Survos\GridGroupBundle\Service\Reader;

class CsvDatabase
{
    /**
     * File read flag.
     *
     * @var int
     */
    const FILE_READ = 1;

    /**
     * File write flag.
     *
     * @var int
     */
    const FILE_WRITE = 2;

    /**
     * File append flag.
     *
     * @var int
     */
    const FILE_APPEND = 3;

    /**
     * File access mode.
     *
     * @var array
     */
    protected $fileAccessMode = [
        self::FILE_READ => [
            'mode' => 'rb',
            'operation' => LOCK_SH,
        ],
        self::FILE_WRITE => [
            'mode' => 'wb',
            'operation' => LOCK_EX,
        ],
        self::FILE_APPEND => [
            'mode' => 'ab',
            'operation' => LOCK_EX,
        ],
    ];

    /**
     * Database name.
     *
     * @var string
     */
    protected $name;


    private CountableArrayCache $offsetCache;
    private int $currentSize = 0;

    public function __construct(private string $filename, private string $keyName, private array $headers=[], private bool $useGZip=false)
    {
        $this->offsetCache = new CountableArrayCache();
        if (!in_array($this->keyName, $this->headers)) {
            array_unshift($this->headers, $this->keyName);
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
     * @return CsvDatabase
     */
    public function setFilename(string $filename): CsvDatabase
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->keyName;
    }

    /**
     * @param string $keyName
     * @return CsvDatabase
     */
    public function setKeyName(string $keyName): CsvDatabase
    {
        $this->keyName = $keyName;
        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     * @return CsvDatabase
     */
    public function setHeaders(array $headers): CsvDatabase
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Append a line to the database file.
     *
     * @param string $line
     */
    public function appendToFile(string $key, array $data)
    {
        $headers = $this->getHeaders();
        $file = $this->openFile(static::FILE_APPEND);
        $file->fseek(0, SEEK_END);
        $pos = $file->ftell();

        // if it's empty, first write the headers
        if (count($headers) ==0 || ($headers = [$this->getKeyName()])) {
            $headers = array_keys($data);
            $this->setHeaders($headers);
        }

        if ( ($pos == 0)) {
            $file->fputcsv($headers);
        }

        // if no headers, then use the headers of the first row passed in.
        if (array_keys($data) <> $headers) {
            // fix the order if the data keys don't match the headers
            $dataValues = [];
            foreach ($headers as $key) {
                $dataValues[] = $data[$key]??null;
            }
        } else {
            $dataValues = array_values($data);
        }
        if (!array_key_exists($key, $data)) {
            $data[$this->getKeyName()] = $key;
        }
        // before writing the data, save the position in the offset cache.
        $position =  $file->ftell();

        assert(array_key_exists($this->getKeyName(), $data), json_encode($data));
        $this->offsetCache->set($key, $position);

        $file->fputcsv($dataValues);
        $this->currentSize = $file->ftell();
        $this->closeFile($file);
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
            $reader = new Reader($this->getPath());
            foreach ($reader->getRow() as $data) {
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
        if ($this->offsetCache->count()==0) {
            $this->loadOffsetCache();
        }
        return $this->offsetCache->contains($key) ? $this->offsetCache->get($key) : null;
    }


    public function loadOffsetCache()
    {
        $existingFile = $this->getPath();
        if (file_exists($existingFile)) {
            $reader = new Reader($existingFile, strict: false);
            foreach ($reader->getRow() as $row) {
                try {
                    // this could be a trait, too.
                    AppService::assertKeyExists($this->getKeyName(), $row);
                } catch (\Exception) {
                    // only during dev
                }
                $this->offsetCache->set($row[$this->getKeyName()], $reader->getCurrentBufferPosition());
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
     * @throws Exception
     *
     * @return SplFileObject
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
        return new SplTempFileObject($this->getSwapMemoryLimit());
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


}
