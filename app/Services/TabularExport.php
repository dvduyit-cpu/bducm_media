<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class TabularExport
{
    public function download(string $format, string $title, array $headers, array $rows): Response
    {
        $filename = 'bdu-'.now()->format('Ymd-His');
        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($headers, $rows) {
                $book = new Spreadsheet;
                $sheet = $book->getActiveSheet();
                foreach ([$headers, ...$rows] as $index => $row) {
                    foreach (array_values($row) as $column => $value) {
                        $sheet->setCellValueExplicit([$column + 1, $index + 1], (string) ($value ?? ''), DataType::TYPE_STRING);
                    }
                }
                $sheet->getStyle('1:1')->getFont()->setBold(true);
                $sheet->freezePane('A2');
                for ($column = 1; $column <= count($headers); $column++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth($column === 1 ? 42 : 24);
                }
                $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true);
                (new Xlsx($book))->save('php://output');
                $book->disconnectWorksheets();
            }, $filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('tempDir', storage_path('framework/cache'));
        $options->set('fontCache', storage_path('framework/cache'));
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('exports.table', compact('title', 'headers', 'rows'))->render(), 'UTF-8');
        $pdf->setPaper('a4', 'landscape');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"']);
    }
}
