<?php

namespace App\Services\Import;

use App\Exports\ProductsExport;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProductExportService
{
    public function export(int $organizationId, string $format, array $filters): BinaryFileResponse|StreamedResponse
    {
        $normalizedFormat = strtolower(trim($format));

        if (! in_array($normalizedFormat, ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages([
                'format' => ['The selected format is invalid.'],
            ]);
        }

        $writerType = $normalizedFormat === 'xlsx' ? ExcelWriter::XLSX : ExcelWriter::CSV;
        $filename = sprintf('products-%s.%s', now()->format('Ymd_His'), $normalizedFormat);

        return Excel::download(
            export: new ProductsExport($organizationId, $filters, $normalizedFormat),
            fileName: $filename,
            writerType: $writerType,
        );
    }
}
