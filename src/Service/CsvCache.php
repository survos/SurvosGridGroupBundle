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
    public function setKeyName(string $keyName): CsvCache
    {
        $this->keyName = $keyName;
        return $this;
    }

    /**
     * @param array $headers
     * @return CsvCache
     */
    public function setHeaders(array $headers): CsvCache
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
        $keyOffset = $this->getDatabase()->keyOffset($key);
        return !is_null($keyOffset); // 0 is a valid offset, though realistically that will always be the headers.
    }


    /**
     * Set a key in the database.
     *
     * @param string $key
     * @param mixed $data
     */
    public function set(string $key, $data)
    {
        // If the key already exists we need to replace it
        if ($this->contains($key)) {
            $this->replace($key, $data);
            return;
        }

        // Write the key to the database
        $this->getDatabase()->appendToFile($key, $data);
    }

    /**
     * Get a key from the database.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key)
    {
//        try {
//            Validation::validateKey($key);
//        } catch (\Exception $exception) {
//            $key = md5($key);
//        }


        // Fetch the offset
        if ($position = $this->getDatabase()->keyOffset($key)) {
            // @todo: move the buffer pointer using fseek and get the record there.

        }

        // Fetch the key from database
        $file = $this->getDatabase()->readFromFile();
        $data = false;


        foreach ($file as $row) {
            if ($row[$this->getKeyName()] == $key) {
                $data = $row;
                break;
            }
        }

        return $data;
    }

    /**
     * Replace a key in the database.
     *
     * @param string $key
     * @param mixed $data
     */
    protected function replace(string $key, $data)
    {
        //
        // Write a new database to a temporary file
        $tmpFile = $this->getDatabase()->openTempFile();
        $file = $this->getDatabase()->readFromFile();

        foreach ($file as $row) {
            $tmpFile->fputcsv($this->getHeaders());
            if ($row[$this->getKeyName()] == $key) {
                if ($data !== false) {
                    $tmpFile->fputcsv($data);
                }
            } else {
                $tmpFile->fputcsv($data);
            }
        }

        $tmpFile->rewind();

        // Overwrite the database with the temporary file
        $this->getDatabase()->writeTempToFile($tmpFile);


    }



    public function getDatabase(): CsvDatabase
    {
        return $this->database;
    }

}
