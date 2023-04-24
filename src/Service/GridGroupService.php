<?php

namespace Survos\GridGroupBundle\Service;

use Doctrine\Persistence\ManagerRegistry;
use Goutte\Client;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use Survos\CrawlerBundle\Model\Link;
use Survos\GridGroupBundle\Model\Grid;
use Survos\GridGroupBundle\Model\GridGroup;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\String\u;

class GridGroupService
{

    public function __construct(
    )
    {
    }

    static public function createFromDirectory(string $groupDir, ?string $excludePattern=null): GridGroup
    {
        $finder = new Finder();
        $groupCode = pathinfo($groupDir, PATHINFO_FILENAME);
        $gridGroup = (new GridGroup($groupCode, dir: $groupDir));
        foreach ($finder->in($groupDir) as $file) {
            assert(!$file->isDir(), "only files (csv, to create a grid or sheet), not " . $file->getRealPath());

            $headers = (new Reader($file->getRealPath()))->getHeaders();
            assert(is_array($headers), $file->getRealPath());
            $name = $file->getFilenameWithoutExtension();
            if ($excludePattern && preg_match($excludePattern, $file->getRealPath())) {
                continue;
            }
            $grid = (new Grid($name, $headers));
            $gridGroup->addGrid($grid);
        }
        return $gridGroup;
    }


    // includes the header row.
    static public function countCsvRows(string $filename)
    {
        $buffer = fopen($filename, 'r+');
        $count = 0;
        while (fgetcsv($buffer)) {
            $count++;
        }
        fclose($buffer);
        return $count;

    }


    static public function fetchRow(string $filename, string $separator = ",", int $limit=null, int $offset=null): \Generator
    {
        static $headers=null;
        static $headersCount=null;
        $buffer = fopen($filename, 'r+');
        while ($row = fgetcsv($buffer, separator: $separator)) {
            if (empty($headers)) {
                // https://stackoverflow.com/questions/54145035/cant-remove-ufeff-from-a-string
//                $row[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $row[0]);
                $row[0]= trim($row[0], "\xEF\xBB\xBF");
                $headers = $row;
                $headersCount = count($headers);
                continue;
            }
            $fieldCount = count($row); // how many fields
            if ($fieldCount > $headersCount) {
                $row = array_slice($row, 0, $headersCount);
            } elseif ($fieldCount < $headersCount) {
                $row = array_pad($row, $headersCount, null);
            }
            assert(count($row) == $headersCount, join(',', $headers) . "\n"  . join(',', $row));
            $data = array_combine($headers, $row);
            yield $data;
        }
    }

    static function getHeadersFromFile(string $filename)
    {
        assert(false, "problematic!  use reader->getHeaders() instead.");
        // hack, because it returns while inside the loop, this is a yield
        foreach (self::fetchRow($filename) as $row) {
            return array_keys($row);
        }

    }



    public function exportAsExcel(GridGroup $gridGroup, string $filename): Xlsx
    {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {

        }
        // @todo: check that class exists and require installation, like twig-extra, so it's not required in every installation
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $tocSheet = $spreadsheet->getActiveSheet();
        $tocSheet->setTitle("TOC");
        $tocData[] = ['sheetName', 'rows'];

        foreach ($gridGroup->getGrids() as $sheetName => $grid) {
            $rowData = [];
//            dd($grid, $grid->getDataAsArray(), $grid->getDataAsString());
            $sheetName = substr($sheetName, 0, 31);
            $sheet = $spreadsheet->createSheet()->setCodeName($sheetName)->setTitle($sheetName);
            $buffer = fopen('php://temp', 'r+');
            rewind($buffer);
            $data = $grid->getDataAsString();
            fwrite($buffer, $data);
            rewind($buffer);
            while ($x = fgetcsv($buffer)) {
                $rowData[] = $x;
            }
            fclose($buffer);

//            $sheet->fromArray($grid->getHeaders()); // includes headers.
            $sheet->fromArray($rowData, startCell: 'A1');
            $tocData[] = [$sheetName, count($rowData) - 1];
            $this->cleanup($sheet);
        }
        $tocSheet->fromArray($tocData);
        $this->cleanup($tocSheet);
//        $sheet = $spreadsheet->getActiveSheet();
//        $sheet->fromArray()
//        $sheet->setCellValue("A1", "Hello World!");
        $writer = new Xlsx($spreadsheet);
        $writer->save($filename);
        return $writer;
    }
    private function cleanup(Worksheet $sheet, array $fieldNames = [])
    {

        // @todo: hide id rows
//        $sheet->getColumnDimension('D')->setVisible(false);

//        $sheet->freezePaneByColumnAndRow(0, 1);
        $sheet->freezePane('B2');
        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }


//Retrieve Highest Column (e.g AE)
        $highestColumn = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $highestColumn . '1' )->getFont()->setBold(true);

//        $sheet->getStyle('B2')
//            ->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED);
//        $spreadsheet->getActiveSheet()->getStyle('B2')
//            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
//        $spreadsheet->getActiveSheet()->getStyle('B2')
//            ->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
//        $spreadsheet->getActiveSheet()->getStyle('B2')
//            ->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
//        $spreadsheet->getActiveSheet()->getStyle('B2')
//            ->getBorders()->getLeft()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
//        $spreadsheet->getActiveSheet()->getStyle('B2')
//            ->getBorders()->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
//        $spreadsheet->getActiveSheet()->getStyle('B2')
//            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
//        $spreadsheet->getActiveSheet()->getStyle('B2')
//            ->getFill()->getStartColor()->setARGB('FFFF0000');


    }


}
