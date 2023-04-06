<?php

namespace Survos\GridGroupBundle\Service;

class Reader //  extends \EasyCSV\Reader
{

    private int $headerCount;
    private bool $init;
    private  $buffer;

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
            $headers[0] = trim($headers[0], "\xEF\xBB\xBF");
            $this->headersCount = count($headers);
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

    public function getRow(): \Generator
    {
        $this->init();
        $this->currentLine++;
        while ($row = fgetcsv($this->buffer, separator: $this->delimiter)) {
            if (!$this->strict) {
                $headersCount = $this->headersCount;
                $fieldCount = count($row); // how many fields
                if ($fieldCount > $headersCount) {
                    $row = array_slice($row, 0, $headersCount);
                } elseif ($fieldCount < $headersCount) {
                    $row = array_pad($row, $headersCount, null);
                }
                assert(count($row) == $headersCount, join(',', $this->headers) . "\n" . join(',', $row));
            }
            $data = array_combine($this->headers, $row);
            yield $data;
        }

    }

}
