<?php

declare(strict_types=1);

namespace Survos\GridGroupBundle\Model;


use App\Entity\FieldMap;

class Grid
{
    public function __construct(
        public ?string $key = null,
        public array   $headers = [],
        public array   $rowData = [])
    {

    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeaders(): bool
    {
        return count($this->headers) > 0;
    }

    public function getHeadersAsCsvString(): string
    {
        return $this->str_putcsv([$this->headers]);
    }

    /**
     * @return array|string
     */
    public function getData(): array|string
    {
        return $this->rowData;
    }

    public function getRowCount(): int
    {
        return count($this->rowData);
    }

    public function getDataAsString(bool $withHeaders = true, bool $withHeaderCodes = true, $limit=0): string
    {
        if ($withHeaderCodes) {
            $this->headers = array_map(fn($header) => FieldMap::slugify($header), $this->headers);
        }
        $data = $withHeaders ? array_merge([$this->headers], $this->rowData) : $this->rowData;
        return $this->str_putcsv($limit ? array_slice($data, 0, $limit) : $data);
    }

    public function getDataAsArray(): array
    {
        return $this->rowData;
    }

    // probably the same, may need to investigate for memory
    static public function arrayToCSV(array $fields, $delimiter = ",", $enclosure = '"', $escape_char = "\\"): string
    {
        $buffer = fopen('php://temp', 'r+');
        foreach($fields as $row) {
            fputcsv($buffer, $row, $delimiter, $enclosure, $escape_char);
        }
        rewind($buffer);
        $csv = '';
        while ($blob = fread($buffer, 1024)) {
            $csv .= $blob;
        }
//        $csv = fread($buffer);
//        $csv = fgetcsv($buffer);
        fclose($buffer);
        return $csv;
    }



    function str_putcsv($input, $delimiter = ',', $enclosure = '"') {
// https://gist.github.com/johanmeiring/2894568
        $fp = fopen('php://temp', 'r+b');
        foreach ($input as $row) {
            fputcsv($fp, $row, $delimiter, $enclosure);
        }

        $fpSize = ftell($fp);
        rewind($fp);
//        $data = fread($fp, $fpSize);
        $data = rtrim(stream_get_contents($fp), "\n");
        fclose($fp);
        return $data;
    }

    public function setHeaders(array $headers) {
        $this->headers = $headers;
    }

    public function addRow(array $row): self
    {
        // if it's a list of data values ['bob','smith'], the combine it with keys.  Otherwise, checkk the headers and add it ['first' => 'Bob']
        if (count($row) == 0) {
            return $this;
        }
        if (array_is_list($row)) {
//            dd($this->headers, $row);
            assert(count($this->headers) == count($row));
            $this->rowData[] = array_combine($this->headers, $row);
        } else {
            // add new headers, this only works if the row is a key, not the header string.
            foreach (array_keys($row) as $header) {
                if (!in_array($header, $this->headers)) {
//                    dump($header, $this->headers);
                    $this->headers[] = $header;
                }
            }
//            dd($row, $this->headers);
//            assert($this->headers == array_keys($row));
            $this->rowData[] = $row;
        }
        return $this;
    }
    public function loadHeadersAndData(array $headers, array $data): self
    {
        $this->headers = $headers;
        assert(array_is_list($data), "data must be a list (of rows)");
        foreach ($data as $row) {
            $this->addRow($row);
        }
        return $this;
    }
    public function loadString(?string $csvText): self
    {
        if (!is_null($csvText)) {
            $buffer = fopen('php://temp', 'r+');
            rewind($buffer);
            fwrite($buffer, $csvText);
            rewind($buffer);
            $foundHeaders = false;
            while ($x = fgetcsv($buffer)) {
                if ($foundHeaders) {
                    if (count($this->headers) < count($x)) {
                        $x = array_slice($x, 0, count($this->headers));
                    }
                    if (count($this->headers) > count($x)) {
                        $x = array_pad($x, count($this->headers), null);
                    }
                    if (count($this->headers) <> count($x)) {
//                        continue; // skip but we should figure out why
                        dd($this->headers, $x);
                    } else {
                        $this->rowData[] = array_combine($this->headers, $x);
                    }
                } else {
                    $this->headers = $x;
                    $foundHeaders = true;
                }
            }
            fclose($buffer);
        }
        return $this;
    }


}

