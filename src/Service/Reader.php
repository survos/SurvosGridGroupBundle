<?php

namespace Survos\GridGroupBundle\Service;

class Reader //  extends \EasyCSV\Reader
{

    private int $headerCount;
    private bool $init;
    private $buffer;
    private int $currentBufferPosition = 0;
    private $rawRow;
    private $rowOffset;

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
        private int    $currentLine = 0,
        private bool   $strict = true, // faster
        private ?array $headers = null, // if not set, headers come from the first row.
    )
    {
        $this->init = false;
        if (!is_null($this->headers)) {
            $this->headerCount = count($this->headers);
        }
//        parent::__construct($path, $mode);
//        $this->headersInFirstRow = $headersInFirstRow;
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function init()
    {
        if (true === $this->init) {
            return;
        }
        $this->init = true;
        assert(file_exists($this->path), "Missing " . $this->path);
        $this->buffer = fopen($this->path, 'r+');
        if (is_null($this->headers)) {
            // we could also get the first row and see if there's a tab in it..
            $headers = fgetcsv($this->buffer, separator: $this->delimiter);
            $this->rowOffset = $this->getCurrentBufferPosition();
            if (!$headers || count($headers) == 0) {

                throw new \Exception($this->path . " Headers are emtpy " . $headers);
            }
            $headers[0] = trim($headers[0], "\xEF\xBB\xBF");
            $this->headerCount = count($headers);
            $this->headers = $headers;
        }
    }

    /**
     * Sets rowOffset and return current csv row
     *
     * @return bool|array
     */
    protected function getCsvRow(): bool|array
    {
        $this->rowOffset = $this->getCurrentBufferPosition();
        return fgetcsv($this->buffer, separator: $this->delimiter);
    }

    /**
     * @return int "Number of rows read (not including headers)"
     */
    public function close(): int
    {
        fclose($this->buffer);
        return $this->currentLine;

    }

    /**
     * @param string $delimiter
     * @return $this
     */
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function getCsvCount(): int
    {
        $this->init(0);
        $c = 0;
        while ($row = fgetcsv($this->buffer, separator: $this->delimiter)) {
            $c++;
        }
        return $c;
    }

    /**
     * @return \Generator
     * @throws \Exception
     */
    public function getRow(): \Generator
    {
        $this->init();
        $this->currentLine++;
        while ($row = $this->getCsvRow()) {
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
                assert(count($row) == $this->headerCount, $this->path . "\n\n" . join("\n", $this->headers) . "\n\n" . join("\n", $row));
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
        $this->init();
        return $this->headers;
//        $headers = fgetcsv($this->buffer, separator: $this->delimiter);
////        $this->setCurrentBufferPosition(0); // reset?
//        return $headers;

    }

    /**
     * @return int
     */
    public function getCurrentBufferPosition(): int
    {
        return ftell($this->buffer);
    }

    /**
     * @param $position
     * @return int
     */
    public function setCurrentBufferPosition($position): int
    {
        return fseek($this->buffer, $position);
    }

    /**
     * @return int
     */
    public function getRowOffset(): int
    {
        return $this->rowOffset;
    }
}
