<?php

namespace Survos\GridGroupBundle\Service;

use Flintstone\Cache\ArrayCache;
use Flintstone\Config;
use Flintstone\Database;
use Flintstone\Exception;
use Flintstone\Flintstone;
use Flintstone\Line;
use Flintstone\Validation;
use Psr\Log\LoggerInterface;

use Survos\GridGroupBundle\Service\Bedrock\BedrockConfig;
use Survos\GridGroupBundle\Service\Bedrock\BedrockDatabase;
use Survos\GridGroupBundle\Service\Bedrock\BedrockRow;
use Survos\GridGroupBundle\Service\Bedrock\CountableArrayCache;

class CsvCache extends Flintstone
{
    /**
     * Flintstone version.
     *
     * @var string
     */
    const VERSION = '2.3';

    /**
     * Database class.
     *
     * @var BedrockDatabase
     */
    protected $database;

    /**
     * Config class.
     *
     * @var Config
     */
    protected $config;

    /**
     * Bedrock Config class, with headers and key
     *
     * @var BedrockConfig
     */
    protected $bedrockConfig;
    
    /**
     * Constructor.
     *
     * @param Database|string $database
     * @param Config|array $config
     */
    public function __construct(BedrockDatabase|string $database,
                                BedrockConfig|array $config,
    private ?LoggerInterface $logger=null
    )
    {
        // headers is required

        if (is_array($config)) {
            $headers = $config['headers']??[];
            $keyName = $config['keyName']??null;
            $config = new BedrockConfig($config);
        }
//
//        $bedrockConfig = new BedrockConfig($headers, $keyName);
//        $this->setBedrockConfig($config);
        if (is_string($database)) {
            if ($ext = pathinfo($database, PATHINFO_EXTENSION)) {
                $config->setExt($ext);
            }
            if ($dir = pathinfo($database, PATHINFO_DIRNAME)) {
                $config->setDir($dir);
            }
            $database = pathinfo($database, PATHINFO_FILENAME);
            $database = new BedrockDatabase($database);
        }
        assert($database::class == BedrockDatabase::class);



        if (count($headers) == 0) {
            throw new \LogicException("the 'headers' property must be an array with at least one field");
        }

        parent::__construct($database, $config);
//        $keyName = $config['keyName']??'_id';

//        $bedrockConfig = new BedrockConfig($headers, $keyName);
        $this->setBedrockConfig($config);

//        $this->setDatabase($database);
//        $this->setConfig($config);
    }

    public function contains(string $key): bool
    {
//        try {
//            Validation::validateKey($key);
//        } catch (\Exception $exception) {
//            $key = md5($key);
//        }
//
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
//        try {
//            Validation::validateKey($key);
//        } catch (\Exception $exception) {
//            $key = md5($key);
//        }

        // If the key already exists we need to replace it
        if ($this->contains($key)) {
            $this->replace($key, $data);
            return;
        }

        // Write the key to the database
        $this->getDatabase()->appendToFile($this->getLineString($key, $data));

        // Delete the key from cache
        if ($cache = $this->getConfig()->getCache()) {
            $cache->set($key, $data);
//            $cache->delete($key);
        }
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


        // Fetch the key from cache
        if ($cache = $this->getConfig()->getCache()) {
            $this->logger?->warning(" Looking for $key in " . $this->getDatabase()->getPath());
            if ($cache->contains($key)) {
                $this->logger?->warning(" In CACHE! $key!");
                return $cache->get($key);
            }
        }

        // Fetch the key from database
        $file = $this->getDatabase()->readFromFile();
        $data = false;


        assert(false, "what calls get");
        $this->logger?->warning("Partial scan for $key...");
        foreach ($file as $bedrockRow) {
            if ($bedrockRow->getKeyValue() == $key) {
                $data = $bedrockRow->getData();
                break;
            }
        }

        // Save the data to cache
        if ($cache && $data !== false) {
            $cache->set($key, $data);
        }
//        dd($cache, $cache->get($key));

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
            $tmpFile->fputcsv($this->getBedrockConfig()->getHeaders());
            /** @var BedrockRow $line */
            if ($row->getKeyValue() == $key) {
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

        // Delete the key from cache, which might have the old data.
        if ($cache = $this->getConfig()->getCache()) {
            assert(false, "update the offset cache " . $key . ' ' . $this->getDatabase()->getPath());
            $cache->delete($key);
        }
    }



    /**
     * Set the config.
     *
     * @param Config $config
     */
    public function setBedrockConfig(BedrockConfig $config)
    {
        $this->bedrockConfig = $config;
        $this->getDatabase()->setBedrockConfig($config);
    }


    public function getBedrockConfig(): BedrockConfig
    {
        return $this->bedrockConfig;
    }

    public function getDatabase(): BedrockDatabase
    {
        return $this->database;
//        return parent::getDatabase(); // TODO: Change the autogenerated stub
    }
    


    protected function getLineString(string $key, $data): string
    {
        $headers = $this->getBedrockConfig()->getHeaders();
        $keyName = $this->getBedrockConfig()->getKeyName();
        if (!array_key_exists($keyName, $headers)) {
            $data = array_merge([$keyName => $key], $data);
        }
        $x = [];
        foreach ($this->getBedrockConfig()->getHeaders() as $header) {
            $x[$header] = $data[$header];
        }
        return json_encode($x); // because getLineString must be a string.

        dump($data, $this->getBedrockConfig()->getHeaders());
        assert(array_keys($data) == $this->getBedrockConfig()->getHeaders());
        // @todo: loop through headers to make sure the order is right, no new keys, etc.
    }



}
