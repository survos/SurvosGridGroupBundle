<?php

namespace Survos\GridGroupBundle\Service;

class Reader //  extends \EasyCSV\Reader
{

    private int $headerCount;
    private bool $init;
    private  $buffer;
    private int $currentBufferPosition=0;
    private $rawRow;

    /**
     * @return mixed
     */
    public function getRawRow()
    {
        return $this->rawRow;
    }

    /**
     * @param mixed $rawRow
     */
    public function setRawRow($rawRow): void
    {
        $this->rawRow = $rawRow;
    }

    public function __construct(
        private string $path,
        private        $mode = 'r+',
        private        $headersInFirstRow = true,
        private        $delimiter = ',',
        private int $currentLine = 0,
        private bool  $strict = true, // faster
        private ?array $headers=null, // if not set, headers come from the first row.
    )
    {
        $this->init = false;
        if (!is_null($this->headers)) {
            $this->headerCount = count($this->headers);
        }
//        parent::__construct($path, $mode);
//        $this->headersInFirstRow = $headersInFirstRow;
    }

    protected function init()
    {
        if (true === $this->init) {
            return;
        }
        $this->init = true;

        $this->buffer = fopen($this->path, 'r+');
        if (is_null($this->headers)) {
            // we could also get the first row and see if there's a tab in it..
            $headers = fgetcsv($this->buffer, separator: $this->delimiter);
            if (!$headers || count($headers) == 0) {

                throw new \Exception($this->path . " Headers are emtpy " . $headers);
            }
            $headers[0] = trim($headers[0], "\xEF\xBB\xBF");
            $this->headerCount = count($headers);
            $this->headers = $headers;
        }
    }

    /**
     * @return int "Number of rows read (not including headers)"
     */
    public function close(): int
    {
        fclose($this->buffer);
        return $this->currentLine;

    }

    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    public function getCsvCount(): int
    {
        $this->init(0);
        $c=0;
        while ($row = fgetcsv($this->buffer, separator: $this->delimiter)) {
            $c++;
        }
        return $c;
    }

    public function getRow(): \Generator
    {
        $this->init();
        $this->currentLine++;
        while ($row = fgetcsv($this->buffer, separator: $this->delimiter)) {
            $this->setRawRow($row);
            if (!$this->strict) {
                $headersCount = $this->headerCount;
                $fieldCount = count($row); // how many fields
                if ($fieldCount > $headersCount) {
                    $row = array_slice($row, 0, $headersCount);
                } elseif ($fieldCount < $headersCount) {
                    $row = array_pad($row, $headersCount, null);
                }
            } else {
                assert(count($row) ==  $this->headerCount, $this->path . "\n\n" . join("\n", $this->headers) . "\n\n" . join("\n", $row));
            }

            $data = array_combine($this->headers, $row);
            yield $data;
        }
    }

    /**
     * @return array|null
     */
    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    /**
     * @return int
     */
    public function getCurrentBufferPosition(): int
    {
        return ftell($this->buffer);
    }

    public function setCurrentBufferPosition($position): int
    {
        return fseek($this->buffer, $position);
    }




}
