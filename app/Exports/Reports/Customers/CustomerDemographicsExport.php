<?php

namespace App\Exports\Reports\Customers;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

class CustomerDemographicsExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    protected $reportData;
    protected $subshopName;
    protected $filters;

    public function __construct(array $reportData, string $subshopName = 'All Branches', array $filters = [])
    {
        $this->reportData = $reportData;
        $this->subshopName = $subshopName;
        $this->filters = $filters;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $metrics = $this->reportData['metrics'] ?? [];
        $genderDistribution = $this->reportData['gender_distribution'] ?? [];
        $ageDistribution = $this->reportData['age_distribution'] ?? [];
        $regionDistribution = $this->reportData['region_distribution'] ?? [];
        $occupationDistribution = $this->reportData['occupation_distribution'] ?? [];
        $categoryDistribution = $this->reportData['category_distribution'] ?? [];
        $idTypeDistribution = $this->reportData['id_type_distribution'] ?? [];
        $registrationTrends = $this->reportData['registration_trends'] ?? [];

        $data = [];

        // Add title section
        $data[] = ['CUSTOMER DEMOGRAPHICS REPORT'];
        $data[] = ['Branch: ' . $this->subshopName];
        $data[] = ['Date Range: ' . ($this->filters['from_date'] ?? 'All') . ' to ' . ($this->filters['to_date'] ?? 'All')];
        $data[] = [];

        // Add summary metrics
        $data[] = ['SUMMARY METRICS'];
        $data[] = ['Total Customers', number_format($metrics['total_customers'] ?? 0)];
        $data[] = ['Active Customers', number_format($metrics['active_customers'] ?? 0)];
        $data[] = ['Inactive Customers', number_format($metrics['inactive_customers'] ?? 0)];
        $data[] = [];

        // Gender Distribution
        $data[] = ['GENDER DISTRIBUTION'];
        $data[] = ['Gender', 'Count', 'Percentage'];
        foreach ($genderDistribution as $item) {
            $data[] = [$item['gender'], $item['count'], $item['percentage'] . '%'];
        }
        $data[] = [];

        // Age Distribution
        $data[] = ['AGE GROUP DISTRIBUTION'];
        $data[] = ['Age Group', 'Count', 'Percentage'];
        foreach ($ageDistribution as $item) {
            $data[] = [$item['age_group'], $item['count'], $item['percentage'] . '%'];
        }
        $data[] = [];

        // Region Distribution
        $data[] = ['GEOGRAPHIC DISTRIBUTION (by Region)'];
        $data[] = ['Region', 'Count', 'Percentage'];
        foreach ($regionDistribution as $item) {
            $data[] = [$item['region'], $item['count'], $item['percentage'] . '%'];
        }
        $data[] = [];

        // Occupation Distribution
        $data[] = ['OCCUPATION DISTRIBUTION'];
        $data[] = ['Occupation', 'Count', 'Percentage'];
        foreach ($occupationDistribution as $item) {
            $data[] = [$item['occupation'], $item['count'], $item['percentage'] . '%'];
        }
        $data[] = [];

        // Category Distribution
        $data[] = ['CUSTOMER CATEGORY DISTRIBUTION'];
        $data[] = ['Category', 'Count', 'Percentage'];
        foreach ($categoryDistribution as $item) {
            $data[] = [$item['category'], $item['count'], $item['percentage'] . '%'];
        }
        $data[] = [];

        // ID Type Distribution
        $data[] = ['ID TYPE DISTRIBUTION'];
        $data[] = ['ID Type', 'Count', 'Percentage'];
        foreach ($idTypeDistribution as $item) {
            $data[] = [$item['id_type'], $item['count'], $item['percentage'] . '%'];
        }
        $data[] = [];

        // Registration Trends
        $data[] = ['MONTHLY REGISTRATION TRENDS'];
        $data[] = ['Month', 'New Customers'];
        foreach ($registrationTrends as $trend) {
            $data[] = [$trend['month'], $trend['count']];
        }

        return collect($data);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Customer Demographics';
    }

    /**
     * @param Worksheet $sheet
     */
    public function styles(Worksheet $sheet)
    {
        // Style the title row
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setSize(11);
        $sheet->getStyle('A3')->getFont()->setSize(11);

        // Find and style section headers
        $row = 6; // Start after title
        $data = $this->collection()->toArray();

        foreach ($data as $key => $rowData) {
            if (isset($rowData[0]) && in_array($rowData[0], [
                'SUMMARY METRICS',
                'GENDER DISTRIBUTION',
                'AGE GROUP DISTRIBUTION',
                'GEOGRAPHIC DISTRIBUTION (by Region)',
                'OCCUPATION DISTRIBUTION',
                'CUSTOMER CATEGORY DISTRIBUTION',
                'ID TYPE DISTRIBUTION',
                'MONTHLY REGISTRATION TRENDS'
            ])) {
                $sheet->getStyle('A' . ($key + 1))->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A' . ($key + 1))->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $sheet->getStyle('A' . ($key + 1))->getFill()->getStartColor()->setRGB('007BFF');
                $sheet->getStyle('A' . ($key + 1))->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
            }
        }

        return [];
    }
}
