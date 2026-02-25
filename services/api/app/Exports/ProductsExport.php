<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

final readonly class ProductsExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithCustomChunkSize, WithEvents, WithHeadings, WithMapping
{
    public function __construct(
        private int $organizationId,
        private array $filters,
        private string $format = 'csv',
    ) {}

    public function query(): Builder
    {
        $query = Product::query()->forOrg($this->organizationId);

        if (array_key_exists('is_active', $this->filters) && $this->filters['is_active'] !== null) {
            $query->where('is_active', filter_var($this->filters['is_active'], FILTER_VALIDATE_BOOL));
        }

        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $needle = '%'.strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereRaw('LOWER(sku) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$needle]);
            });
        }

        return $query->orderBy('id');
    }

    public function headings(): array
    {
        return ['sku', 'name', 'sale_price', 'description', 'is_active'];
    }

    public function map($row): array
    {
        return [
            (string) $row->sku,
            (string) $row->name,
            (string) $row->sale_price,
            $row->description,
            (bool) $row->is_active,
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                if ($this->format !== 'xlsx') {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $highestRow = max(1, $sheet->getHighestRow());
                $range = sprintf('A1:E%d', $highestRow);

                $sheet->getStyle($range)->getFont()
                    ->setName('Aptos')
                    ->setSize(15);

                $sheet->getStyle('A1:E1')->applyFromArray([
                    'font' => [
                        'name' => 'Aptos Display',
                        'size' => 16,
                        'bold' => true,
                        'color' => ['argb' => 'FFFFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF1F4E78'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFE3E8EE'],
                        ],
                    ],
                ]);

                if ($highestRow > 1) {
                    $sheet->getStyle(sprintf('A2:E%d', $highestRow))->applyFromArray([
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_HAIR,
                                'color' => ['argb' => 'FFE3E8EE'],
                            ],
                        ],
                    ]);

                    $sheet->getStyle(sprintf('C2:C%d', $highestRow))->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle(sprintf('E2:E%d', $highestRow))->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->freezePane('A2');
                $sheet->setAutoFilter(sprintf('A1:E%d', $highestRow));
            },
        ];
    }
}
