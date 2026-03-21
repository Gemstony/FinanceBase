<?php

namespace App\Services\Reports\Customers;

use App\Models\Customers;
use App\Models\SubShop;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerDemographicsService
{
    /**
     * Build the customer demographics report data
     */
    public function build(array $filters, array $accessibleSubshopIds): array
    {
        $subshopId = $filters['subshop_id'] ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $gender = $filters['gender'] ?? null;
        $region = $filters['region'] ?? null;
        $category = $filters['category'] ?? null;
        $isActive = $filters['is_active'] ?? null;

        // Get base customer query
        $customerQuery = $this->buildCustomerQuery(
            $subshopId,
            $fromDate,
            $toDate,
            $gender,
            $region,
            $category,
            $isActive,
            $accessibleSubshopIds
        );

        // Get total metrics
        $metrics = $this->getTotalMetrics($customerQuery);

        // Get gender distribution
        $genderDistribution = $this->getGenderDistribution($customerQuery, $metrics['total_customers']);

        // Get age group distribution
        $ageDistribution = $this->getAgeDistribution($customerQuery, $metrics['total_customers']);

        // Get geographic distribution (by region)
        $regionDistribution = $this->getRegionDistribution($customerQuery, $metrics['total_customers']);

        // Get occupation distribution
        $occupationDistribution = $this->getOccupationDistribution($customerQuery, $metrics['total_customers']);

        // Get customer category distribution
        $categoryDistribution = $this->getCategoryDistribution($customerQuery, $metrics['total_customers']);

        // Get ID type distribution
        $idTypeDistribution = $this->getIdTypeDistribution($customerQuery, $metrics['total_customers']);

        // Get registration trends (monthly)
        $registrationTrends = $this->getRegistrationTrends($customerQuery);

        // Get top regions
        $topRegions = $this->getTopRegions($customerQuery, 5);

        // Get chart data
        $chartData = $this->getChartData(
            $genderDistribution,
            $ageDistribution,
            $regionDistribution,
            $registrationTrends
        );

        // Get filter options
        $subshops = $this->getSubshops($accessibleSubshopIds);
        $regions = $this->getUniqueRegions($accessibleSubshopIds);
        $categories = $this->getUniqueCategories($accessibleSubshopIds);

        return [
            'metrics' => $metrics,
            'gender_distribution' => $genderDistribution,
            'age_distribution' => $ageDistribution,
            'region_distribution' => $regionDistribution,
            'occupation_distribution' => $occupationDistribution,
            'category_distribution' => $categoryDistribution,
            'id_type_distribution' => $idTypeDistribution,
            'registration_trends' => $registrationTrends,
            'top_regions' => $topRegions,
            'chart_data' => $chartData,
            'subshops' => $subshops,
            'regions' => $regions,
            'categories' => $categories,
        ];
    }

    /**
     * Build base customer query with filters
     */
    private function buildCustomerQuery(
        ?int $subshopId,
        ?string $fromDate,
        ?string $toDate,
        ?string $gender,
        ?string $region,
        ?string $category,
        ?string $isActive,
        array $accessibleSubshopIds
    ) {
        $query = Customers::query()
            ->whereIn('subshop_id', $accessibleSubshopIds);

        // Apply subshop filter
        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        }

        // Apply date range filter
        if ($fromDate) {
            $query->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
        }
        if ($toDate) {
            $query->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
        }

        // Apply gender filter
        if ($gender) {
            $query->where('gender', $gender);
        }

        // Apply region filter
        if ($region) {
            $query->where('region', $region);
        }

        // Apply category filter
        if ($category) {
            $query->where('category', $category);
        }

        // Apply active status filter
        if ($isActive !== null && $isActive !== '') {
            $query->where('is_active', $isActive === 'active');
        }

        return $query;
    }

    /**
     * Get total customer metrics
     */
    private function getTotalMetrics($customerQuery): array
    {
        $totalCustomers = (clone $customerQuery)->count();
        $activeCustomers = (clone $query = $customerQuery)->where('is_active', true)->count();
        $inactiveCustomers = $totalCustomers - $activeCustomers;

        return [
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'inactive_customers' => $inactiveCustomers,
        ];
    }

    /**
     * Get gender distribution
     */
    private function getGenderDistribution($customerQuery, int $totalCustomers): array
    {
        $results = (clone $customerQuery)
            ->select('gender', DB::raw('COUNT(*) as count'))
            ->groupBy('gender')
            ->orderByDesc('count')
            ->get();

        $distribution = [];
        foreach ($results as $result) {
            $gender = $result->gender ?? 'Unknown';
            $count = $result->count;
            $distribution[] = [
                'gender' => $gender,
                'count' => $count,
                'percentage' => $totalCustomers > 0 ? round(($count / $totalCustomers) * 100, 2) : 0,
            ];
        }

        return $distribution;
    }

    /**
     * Get age group distribution
     */
    private function getAgeDistribution($customerQuery, int $totalCustomers): array
    {
        $customers = (clone $customerQuery)
            ->whereNotNull('birth_date')
            ->where('birth_date', '!=', '')
            ->get(['birth_date']);

        $ageGroups = [
            '18-25' => ['min' => 18, 'max' => 25, 'count' => 0],
            '26-35' => ['min' => 26, 'max' => 35, 'count' => 0],
            '36-45' => ['min' => 36, 'max' => 45, 'count' => 0],
            '46-60' => ['min' => 46, 'max' => 60, 'count' => 0],
            '60+' => ['min' => 60, 'max' => 200, 'count' => 0],
        ];

        foreach ($customers as $customer) {
            if ($customer->birth_date) {
                $age = Carbon::parse($customer->birth_date)->age;
                if ($age >= 18 && $age <= 25) {
                    $ageGroups['18-25']['count']++;
                } elseif ($age >= 26 && $age <= 35) {
                    $ageGroups['26-35']['count']++;
                } elseif ($age >= 36 && $age <= 45) {
                    $ageGroups['36-45']['count']++;
                } elseif ($age >= 46 && $age <= 60) {
                    $ageGroups['46-60']['count']++;
                } elseif ($age > 60) {
                    $ageGroups['60+']['count']++;
                }
            }
        }

        $distribution = [];
        foreach ($ageGroups as $group => $data) {
            $distribution[] = [
                'age_group' => $group,
                'count' => $data['count'],
                'percentage' => $totalCustomers > 0 ? round(($data['count'] / $totalCustomers) * 100, 2) : 0,
            ];
        }

        return $distribution;
    }

    /**
     * Get region distribution
     */
    private function getRegionDistribution($customerQuery, int $totalCustomers): array
    {
        $results = (clone $customerQuery)
            ->select('region', DB::raw('COUNT(*) as count'))
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->groupBy('region')
            ->orderByDesc('count')
            ->get();

        $distribution = [];
        foreach ($results as $result) {
            $count = $result->count;
            $distribution[] = [
                'region' => $result->region ?? 'Unknown',
                'count' => $count,
                'percentage' => $totalCustomers > 0 ? round(($count / $totalCustomers) * 100, 2) : 0,
            ];
        }

        return $distribution;
    }

    /**
     * Get occupation distribution
     */
    private function getOccupationDistribution($customerQuery, int $totalCustomers): array
    {
        $results = (clone $customerQuery)
            ->select('work', DB::raw('COUNT(*) as count'))
            ->whereNotNull('work')
            ->where('work', '!=', '')
            ->groupBy('work')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        $distribution = [];
        foreach ($results as $result) {
            $count = $result->count;
            $distribution[] = [
                'occupation' => $result->work ?? 'Unknown',
                'count' => $count,
                'percentage' => $totalCustomers > 0 ? round(($count / $totalCustomers) * 100, 2) : 0,
            ];
        }

        return $distribution;
    }

    /**
     * Get customer category distribution
     */
    private function getCategoryDistribution($customerQuery, int $totalCustomers): array
    {
        $results = (clone $customerQuery)
            ->select('category', DB::raw('COUNT(*) as count'))
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->groupBy('category')
            ->orderByDesc('count')
            ->get();

        $distribution = [];
        foreach ($results as $result) {
            $count = $result->count;
            $distribution[] = [
                'category' => $result->category ?? 'Unknown',
                'count' => $count,
                'percentage' => $totalCustomers > 0 ? round(($count / $totalCustomers) * 100, 2) : 0,
            ];
        }

        return $distribution;
    }

    /**
     * Get ID type distribution
     */
    private function getIdTypeDistribution($customerQuery, int $totalCustomers): array
    {
        $results = (clone $customerQuery)
            ->select('id_type', DB::raw('COUNT(*) as count'))
            ->whereNotNull('id_type')
            ->where('id_type', '!=', '')
            ->groupBy('id_type')
            ->orderByDesc('count')
            ->get();

        $distribution = [];
        foreach ($results as $result) {
            $count = $result->count;
            $distribution[] = [
                'id_type' => $result->id_type ?? 'Unknown',
                'count' => $count,
                'percentage' => $totalCustomers > 0 ? round(($count / $totalCustomers) * 100, 2) : 0,
            ];
        }

        return $distribution;
    }

    /**
     * Get registration trends (monthly)
     */
    private function getRegistrationTrends($customerQuery): array
    {
        $results = (clone $customerQuery)
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
            ->orderBy('year')
            ->orderBy('month')
            ->limit(24)
            ->get();

        $trends = [];
        foreach ($results as $result) {
            $monthName = Carbon::createFromDate($result->year, $result->month)->format('M Y');
            $trends[] = [
                'month' => $monthName,
                'year' => $result->year,
                'month_num' => $result->month,
                'count' => $result->count,
            ];
        }

        return $trends;
    }

    /**
     * Get top regions
     */
    private function getTopRegions($customerQuery, int $limit = 5): array
    {
        $results = (clone $customerQuery)
            ->select('region', DB::raw('COUNT(*) as count'))
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->groupBy('region')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();

        return $results->map(function ($result) {
            return [
                'region' => $result->region ?? 'Unknown',
                'count' => $result->count,
            ];
        })->toArray();
    }

    /**
     * Get subshops for filter dropdown
     */
    public function getSubshops(array $accessibleSubshopIds): Collection
    {
        return SubShop::whereIn('id', $accessibleSubshopIds)
            ->orderBy('name')
            ->get(['id', 'name', 'shop_id']);
    }

    /**
     * Get unique regions from all customers
     */
    public function getUniqueRegions(array $accessibleSubshopIds): Collection
    {
        return Customers::whereIn('subshop_id', $accessibleSubshopIds)
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region');
    }

    /**
     * Get unique categories from all customers
     */
    public function getUniqueCategories(array $accessibleSubshopIds): Collection
    {
        return Customers::whereIn('subshop_id', $accessibleSubshopIds)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }

    /**
     * Get chart data
     */
    private function getChartData(
        array $genderDistribution,
        array $ageDistribution,
        array $regionDistribution,
        array $registrationTrends
    ): array {
        // Gender distribution (Pie chart)
        $genderChart = [
            'labels' => array_column($genderDistribution, 'gender'),
            'values' => array_column($genderDistribution, 'count'),
            'colors' => $this->generateColors(count($genderDistribution)),
        ];

        // Age distribution (Bar chart)
        $ageChart = [
            'labels' => array_column($ageDistribution, 'age_group'),
            'values' => array_column($ageDistribution, 'count'),
            'colors' => $this->generateColors(count($ageDistribution)),
        ];

        // Region distribution (Bar chart)
        $regionChart = [
            'labels' => array_column($regionDistribution, 'region'),
            'values' => array_column($regionDistribution, 'count'),
            'colors' => $this->generateColors(count($regionDistribution)),
        ];

        // Registration trends (Line chart)
        $trendChart = [
            'labels' => array_column($registrationTrends, 'month'),
            'values' => array_column($registrationTrends, 'count'),
        ];

        return [
            'gender_chart' => $genderChart,
            'age_chart' => $ageChart,
            'region_chart' => $regionChart,
            'trend_chart' => $trendChart,
        ];
    }

    /**
     * Generate colors for charts
     */
    private function generateColors(int $count): array
    {
        $colors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
            '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384',
            '#3cb371', '#db7093', '#1e90ff', '#ffa500', '#9370db',
        ];

        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $colors[$i % count($colors)];
        }

        return $result;
    }

    /**
     * Get drill-down data for a specific region
     */
    public function getCustomersByRegion(string $region, array $accessibleSubshopIds): Collection
    {
        return Customers::whereIn('subshop_id', $accessibleSubshopIds)
            ->where('region', $region)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get drill-down data for a specific gender
     */
    public function getCustomersByGender(string $gender, array $accessibleSubshopIds): Collection
    {
        return Customers::whereIn('subshop_id', $accessibleSubshopIds)
            ->where('gender', $gender)
            ->orderBy('name')
            ->get();
    }
}
