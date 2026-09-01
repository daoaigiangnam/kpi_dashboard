<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TicketTemplateController extends Controller
{
    public function download()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tickets');

        $headers = [
            'ID', 'Priority (Ưu tiên)', 'Created on', 'Started on', 'Finished on',
            'Pause(min)', 'Reopen', 'Company/Dept',
            'Chi tiết nội dung đã xử lý', 'File chụp màn hình kết quả xử lý',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $sampleRows = [
            ['1001','P1','1/9/2025 8:00','1/9/2025 8:05','',0,0,'','',''],
            ['1002','P2','2/9/2025 9:00','2/9/2025 9:30','',60,0,'','',''],
            ['1003','P3','3/9/2025 10:00','3/9/2025 10:40','',0,0,'','',''],
            ['1004','P4','4/9/2025 9:00','4/9/2025 11:10','',0,0,'','',''],
            ['1005','P2','5/9/2025 8:00','5/9/2025 8:30','',0,0,'','',''],
            ['1006','P3','6/9/2025 9:00','6/9/2025 10:40','6/9/2025 18:00',120,1,'HelpDesk','Có','Có'],
            ['1007','P1','7/9/2025 8:00','7/9/2025 8:50','7/9/2025 20:00',180,3,'HelpDesk','Có','Có'],
            ['1008','P4','8/9/2025 9:00','8/9/2025 9:40','8/9/2025 18:00',0,2,'HelpDesk','Có','Có'],
            ['1009','P3','9/9/2025 9:00','9/9/2025 9:20','9/9/2025 11:00',0,0,'HelpDesk','Có','Có'],
            ['1010','P2','10/9/2025 8:00','10/9/2025 8:10','10/9/2025 16:00',60,0,'HelpDesk','Có','Có'],
        ];

        $sheet->fromArray($sampleRows, null, 'A2');

        // Provide 1,000 data-entry rows while keeping the approved sample rows at the top.
        for ($row = 12; $row <= 1001; $row++) {
            $sheet->fromArray([array_fill(0, 10, '')], null, 'A'.$row);
        }

        $sheet->getStyle('A1:J1001')->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FF000000');
        $sheet->getStyle('A1:J1')->getFont()->setBold(true)->setName('Times New Roman')->setSize(11);
        $sheet->getStyle('A1:J1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFC6E7F5');
        $sheet->getStyle('A1:J1001')->getFont()->setName('Times New Roman')->setSize(11);
        $sheet->getStyle('A1:J1001')->getAlignment()->setHorizontal('center')->setVertical('center');
        $sheet->getStyle('A1:J1')->getAlignment()->setWrapText(true);

        $widths = ['A'=>10,'B'=>19,'C'=>20,'D'=>20,'E'=>21,'F'=>13,'G'=>13,'H'=>17,'I'=>28,'J'=>34];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getRowDimension(1)->setRowHeight(30);
        for ($row = 2; $row <= 1001; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(21);
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:J1001');

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            'ticket-import-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}
