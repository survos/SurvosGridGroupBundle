<?php

namespace Survos\GridGroupBundle\Service\Bedrock;

use Flintstone\Config;
use Flintstone\Database;
use SplFileObject;
use SplTempFileObject;
use Survos\GridGroupBundle\Service\Reader;

class BedrockDatabase extends Database
{
    private BedrockConfig $bedrockConfig;
    private CountableArrayCache $offsetCache;
    public function __construct(string $name, Config $config = null)
    {
        parent::__construct($name, $config);
        $this->offsetCache = new CountableArrayCache();

    }

    /**
     * Append a line to the database file.
     *
     * @param string $line
     */
    public function appendToFile(string $line)
    {
        assert(isset($this->bedrockConfig), "bedrock config must be set first");

        $headers = $this->getBedrockConfig()->getHeaders();
        $data = json_decode($line, true);
        $file = $this->openFile(static::FILE_APPEND);
        // if it's empty, first write the headers
        if (!$file->getSize()) {
            $file->fputcsv($headers);
        }
        if (array_keys($data) <> $headers) {
            // fix the order
            $dataValues = [];
            foreach ($headers as $key) {
                $dataValues[] = $data[$key]??null;
            }
        } else {
            $dataValues = array_values($data);
        }
        $position =  $file->ftell();
        $this->offsetCache->set($data[$this->getBedrockConfig()->getKeyName()], $position);

        $file->fputcsv($dataValues);
//        $file->fwrite($line);
        $this->closeFile($file);
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
                yield new BedrockRow($data, $this->getBedrockConfig()->getKeyName());
            }
        }
    }

    public function keyOffset(string $key): ?int
    {
        $config = $this->getBedrockConfig();
        if ($this->offsetCache->count()==0) {
            $this->loadOffsetCache();
        }
//        dd($key, $this->getBedrockConfig()->getKeyName());
        return $this->offsetCache->contains($key) ? $this->offsetCache->get($key) : null;
    }


    public function loadOffsetCache()
    {
        $existingFile = $this->getPath();
        if (file_exists($existingFile)) {
            $reader = new Reader($existingFile, strict: false);
            foreach ($reader->getRow() as $row) {
                $this->offsetCache->set($row[$this->getBedrockConfig()->getKeyName()], $reader->getCurrentBufferPosition());
            }
        }
    }

    public function setBedrockConfig(BedrockConfig $bedrockConfig) {
        $this->bedrockConfig = $bedrockConfig;
    }

    /**
     * @return BedrockConfig
     */
    public function getBedrockConfig(): BedrockConfig
    {
        return $this->bedrockConfig;
    }




}
