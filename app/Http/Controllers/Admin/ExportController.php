<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    // The amount column is typed as a number by streamExcel() so Excel can
    // sum it; AMOUNT_COLUMN below is what keeps the two in step, so columns
    // can be inserted anywhere as long as both lists move together.
    private const HEADERS = [
        'Date', 'Type', 'Company', 'Project', 'Person', 'Title', 'Description',
        'Category', 'Amount', 'Payment Method', 'Payment By', 'Location', 'Notes', 'Created By',
    ];

    private const AMOUNT_COLUMN = 8;

    public function __construct(private readonly TransactionController $transactions) {}

    /**
     * CSV / Excel export of the *currently filtered* transaction set, so a
     * 1-31 August filter exports only August rows.
     */
    public function __invoke(Request $request, string $format): StreamedResponse
    {
        abort_unless(in_array($format, ['csv', 'excel'], true), 404);

        $range = DateRange::fromRequest($request, 'all');
        $query = $this->transactions->filtered($request, $range)
            ->with(['category', 'creator', 'paymentBy', 'company', 'project', 'person'])
            ->orderBy('transaction_date')
            ->orderBy('id');

        $filename = sprintf(
            'transactions_%s%s',
            $range->preset === 'custom' ? ($range->from ?? 'start').'_to_'.($range->to ?? 'end') : $range->preset,
            $format === 'csv' ? '.csv' : '.xls'
        );

        return response()->streamDownload(
            fn () => $format === 'csv' ? $this->streamCsv($query) : $this->streamExcel($query),
            $filename,
            [
                'Content-Type' => $format === 'csv'
                    ? 'text/csv; charset=UTF-8'
                    : 'application/vnd.ms-excel; charset=UTF-8',
            ],
        );
    }

    private function streamCsv($query): void
    {
        $out = fopen('php://output', 'w');

        // BOM so Excel opens UTF-8 (and the rupee sign) correctly.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::HEADERS);

        $query->chunk(500, function ($chunk) use ($out) {
            foreach ($chunk as $row) {
                fputcsv($out, $this->row($row));
            }
        });

        fclose($out);
    }

    /**
     * SpreadsheetML - a real Excel format that needs no external library.
     */
    private function streamExcel($query): void
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        echo '<?mso-application progid="Excel.Sheet"?>'."\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            .'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'."\n";
        echo '<Styles><Style ss:ID="head"><Font ss:Bold="1"/></Style></Styles>'."\n";
        echo '<Worksheet ss:Name="Transactions"><Table>'."\n";

        echo '<Row>';
        foreach (self::HEADERS as $header) {
            echo '<Cell ss:StyleID="head"><Data ss:Type="String">'.$e($header).'</Data></Cell>';
        }
        echo '</Row>'."\n";

        $query->chunk(500, function ($chunk) use ($e) {
            foreach ($chunk as $row) {
                echo '<Row>';
                foreach ($this->row($row) as $index => $value) {
                    // Keep the amount numeric so Excel can sum the column.
                    $type = $index === self::AMOUNT_COLUMN ? 'Number' : 'String';
                    echo '<Cell><Data ss:Type="'.$type.'">'.$e($value).'</Data></Cell>';
                }
                echo '</Row>'."\n";
            }
        });

        echo '</Table></Worksheet></Workbook>';
    }

    private function row(Transaction $row): array
    {
        return [
            $row->transaction_date->format('Y-m-d'),
            ucfirst($row->type),
            $row->company?->name,
            $row->project?->name,
            $row->person?->name,
            $row->title,
            $row->description,
            $row->category?->name,
            $row->amount,
            $row->payment_method_label,
            $row->paymentBy?->name,
            $row->location,
            $row->notes,
            $row->creator?->name,
        ];
    }
}
