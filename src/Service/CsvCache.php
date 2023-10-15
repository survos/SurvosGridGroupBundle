<?php

namespace Survos\GridGroupBundle\Service;

use Psr\Log\LoggerInterface;

use Survos\GridGroupBundle\Service\CsvDatabase;
use Survos\GridGroupBundle\Service\CountableArrayCache;

class CsvCache
{
    public function __construct(private string $csvFilename,
                                private string $keyName,
                                private array $headers=[],
    private ?CsvDatabase $database = null,
    )
    {
        if (!$this->database) {
            $this->database = new CsvDatabase($this->csvFilename, $this->keyName, $this->headers);
        }
    }

    /**
     * @param string $keyName
     * @return CsvCache
     */
    public function setKeyName(string $keyName): self
    {
        $this->keyName = $keyName;
        return $this;
    }

    /**
     * @param array $headers
     * @return CsvCache
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        $this->getDatabase()->setHeaders($headers);
        return $this;
    }

    /**
     * @return string
     */
    public function getCsvFilename(): string
    {
        return $this->csvFilename;
    }

    /**
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->keyName;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function contains(string $key): bool
    {
        return $this->getDatabase()->has($key);
    }


    /**
     * Set a key in the database.
     *
     * @param string $key
     * @param mixed $data
     */
    public function set(string $key, array|object $data)
    {
        return $this->getDatabase()->set($data, $key);
    }

    public function get(string $key)
    {
        return $this->getDatabase()->get($key);
    }


    protected function replace(string $key, $data): self
    {
        $this->getDatabase()->replace($key, $data);
        return $this;
    }



    public function getDatabase(): CsvDatabase
    {
        return $this->database;
    }

}
