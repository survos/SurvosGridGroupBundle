<?php

namespace Survos\GridGroupBundle\Service;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use League\Csv\Stream;
use League\Csv\Writer;
use Symfony\Component\String\Slugger\AsciiSlugger;

class CsvWriter
{
    private Collection $headers;
    private Collection $aliases;
    public function __construct(
        private ?Writer $writer=null,
        array $headers=[],
        array $aliases=[],
    )
    {
        $this->headers = new Collection($headers);
        $this->aliases = new Collection($aliases);
    }

    /**
     * @return array
     */
    public function getHeaders(): Collection
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     * @return CsvDatabase
     */
    public function setHeaders(array $headers): self
    {
        $this->headers->add($headers);
        return $this;
    }

    public function addHeader(string $label): self
    {
        // @todo: define column schema
        $slugger = new AsciiSlugger();
        $header = $slugger->slug($label)->toString();
        $this->headers[] = $header;
        if ($header <> $label) {
            $this->addAlias($label, $header);
        }
        return $this;
    }

    public function addAlias(string $inputKey, string $header): self
    {
        $this->aliases->add($inputKey, $header);
        return $this;
    }




}
