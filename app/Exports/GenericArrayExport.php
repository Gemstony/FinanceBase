<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class GenericArrayExport implements FromArray, WithHeadings, WithTitle
{
    protected array $rows;
    protected string $title;

    public function __construct(array $rows, string $title = 'Sheet1')
    {
        $this->rows = $rows;
        $this->title = $title;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        if (empty($this->rows)) return [];
        $first = $this->rows[0];
        return array_keys($first);
    }

    public function title(): string
    {
        return $this->title;
    }
}
