<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Customer Demographics Report</title>
    <style>
        @page { margin: 24px 22px; font-family: 'DejaVu Sans', Arial, sans-serif; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #000; }

        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 12px; }
        .header-left { display: inline-block; vertical-align: top; width: 64%; }
        .header-right { display: inline-block; vertical-align: top; width: 35%; text-align: right; }
        .header-logo { display: inline-block; vertical-align: top; width: 90px; }
        .header-info { display: inline-block; vertical-align: top; width: calc(100% - 95px); }
        .logo { max-width: 85px; max-height: 85px; object-fit: contain; }

        .inst-name { font-size: 14px; font-weight: 700; margin: 0 0 3px 0; }
        .inst-meta { font-size: 9px; line-height: 1.35; }
        .report-title { font-size: 12px; font-weight: 700; text-transform: uppercase; text-align: right; margin: 0 0 3px 0; }
        .report-sub { font-size: 9px; text-align: right; line-height: 1.35; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #fff; font-weight: 700; text-align: left; }
        td.num, th.num { text-align: right; }

        .muted { font-weight: bold; }
        .row-head { background: #f4f4f4; }
        .nowrap { white-space: nowrap; }
        
        .summary-box { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 15px; 
            padding: 10px; 
            border: 1px solid #000;
        }
        .summary-item { 
            text-align: center; 
            flex: 1; 
            border-right: 1px solid #000; 
        }
        .summary-item:last-child { border-right: none; }
        .summary-label { font-size: 9px; color: #666; display: block; margin-bottom: 3px; }
        .summary-value { font-size: 12px; font-weight: 700; }
        
        .badge { 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-size: 8px; 
            font-weight: 700;
        }
        .badge-primary { background: #007bff; color: #fff; }
        .badge-success { background: #28a745; color: #fff; }
        .badge-warning { background: #ffc107; color: #000; }
        .badge-danger { background: #dc3545; color: #fff; }
        .badge-secondary { background: #6c757d; color: #fff; }
        .badge-info { background: #17a2b8; color: #fff; }
        
        .text-right { text-align: right !important; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-info { color: #17a2b8; }
        .break-word { word-wrap: break-word; overflow-wrap: break-word; word-break: break-all; }
        
        .table-danger { background-color: #f8d7da; }
        .table-warning { background-color: #fff3cd; }
        .table-info { background-color: #d1ecf1; }
        
        .section-title { 
            font-size: 11px; 
            font-weight: 700; 
            text-transform: uppercase; 
            background: #f4f4f4; 
            border: 1px solid #000;
            padding: 6px;
            margin: 15px 0 10px 0;
        }
        
        .text-center { text-align: center; }
        .text-muted { color: #666; }
        
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
@php
    $metrics = $report['metrics'] ?? [];
    $genderDistribution = $report['gender_distribution'] ?? [];
    $ageDistribution = $report['age_distribution'] ?? [];
    $regionDistribution = $report['region_distribution'] ?? [];
    $occupationDistribution = $report['occupation_distribution'] ?? [];
    $categoryDistribution = $report['category_distribution'] ?? [];
    $idTypeDistribution = $report['id_type_distribution'] ?? [];
    $registrationTrends = $report['registration_trends'] ?? [];

    $shopName = $shop->name ?? 'Institution';
    $shopEmail = $shop->email ?? null;
    $shopPhone = $shop->phone ?? null;
    $shopWebsite = $shop->website ?? null;
    $shopAddress = $shop->address ?? null;
    
    $hasData = ($metrics['total_customers'] ?? 0) > 0;
@endphp

<div class="header">
    <div class="header-left">
        <div class="header-logo">
            @if(!empty($shopLogoPath) && file_exists($shopLogoPath))
                <img class="logo" src="{{ $shopLogoPath }}" alt="Logo">
            @endif
        </div>
        <div class="header-info">
            <div class="inst-name">{{ $shopName }}</div>
            <div class="inst-meta">
                @if($shopAddress)
                    <div><strong>Address:</strong> {{ $shopAddress }}</div>
                @endif
                @if($shopPhone)
                    <div><strong>Phone:</strong> {{ $shopPhone }}</div>
                @endif
                @if($shopEmail)
                    <div><strong>Email:</strong> {{ $shopEmail }}</div>
                @endif
                @if($shopWebsite)
                    <div><strong>Website:</strong> {{ $shopWebsite }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="header-right">
        <div class="report-title">Customer Demographics Report</div>
        <div class="report-sub">
            <div><strong>Branch:</strong> {{ $subshopName ?? 'All Branches' }}</div>
            <div><strong>Generated:</strong> {{ $generatedAt }}</div>
            @if(!empty($filters['from_date']) || !empty($filters['to_date']))
                <div><strong>Date Range:</strong> {{ $filters['from_date'] ?? 'All' }} to {{ $filters['to_date'] ?? 'All' }}</div>
            @endif
        </div>
    </div>
</div>

@if($hasData)
<!-- Summary Box -->
<div class="summary-box">
    <div class="summary-item">
        <span class="summary-label">Total Customers</span>
        <span class="summary-value">{{ number_format($metrics['total_customers'] ?? 0) }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Active Customers</span>
        <span class="summary-value text-success">{{ number_format($metrics['active_customers'] ?? 0) }}</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Inactive Customers</span>
        <span class="summary-value text-muted">{{ number_format($metrics['inactive_customers'] ?? 0) }}</span>
    </div>
</div>

<!-- Gender Distribution -->
<div class="section-title">Gender Distribution</div>
<table>
    <thead>
        <tr>
            <th>Gender</th>
            <th class="num">Count</th>
            <th class="num">Percentage</th>
        </tr>
    </thead>
    <tbody>
        @forelse($genderDistribution as $item)
        <tr>
            <td>{{ $item['gender'] }}</td>
            <td class="num">{{ number_format($item['count']) }}</td>
            <td class="num">{{ $item['percentage'] }}%</td>
        </tr>
        @empty
        <tr>
            <td colspan="3" class="text-center text-muted">No data available</td>
        </tr>
        @endforelse
    </tbody>
</table>

<!-- Age Distribution -->
<div class="section-title">Age Group Distribution</div>
<table>
    <thead>
        <tr>
            <th>Age Group</th>
            <th class="num">Count</th>
            <th class="num">Percentage</th>
        </tr>
    </thead>
    <tbody>
        @forelse($ageDistribution as $item)
        <tr>
            <td>{{ $item['age_group'] }}</td>
            <td class="num">{{ number_format($item['count']) }}</td>
            <td class="num">{{ $item['percentage'] }}%</td>
        </tr>
        @empty
        <tr>
            <td colspan="3" class="text-center text-muted">No data available</td>
        </tr>
        @endforelse
    </tbody>
</table>

<!-- Region Distribution -->
<div class="section-title">Geographic Distribution (by Region)</div>
<table>
    <thead>
        <tr>
            <th>Region</th>
            <th class="num">Count</th>
            <th class="num">Percentage</th>
        </tr>
    </thead>
    <tbody>
        @forelse($regionDistribution as $item)
        <tr>
            <td>{{ $item['region'] }}</td>
            <td class="num">{{ number_format($item['count']) }}</td>
            <td class="num">{{ $item['percentage'] }}%</td>
        </tr>
        @empty
        <tr>
            <td colspan="3" class="text-center text-muted">No data available</td>
        </tr>
        @endforelse
    </tbody>
</table>

<!-- Occupation Distribution -->
<div class="section-title">Occupation Distribution</div>
<table>
    <thead>
        <tr>
            <th>Occupation</th>
            <th class="num">Count</th>
            <th class="num">Percentage</th>
        </tr>
    </thead>
    <tbody>
        @forelse($occupationDistribution as $item)
        <tr>
            <td>{{ $item['occupation'] }}</td>
            <td class="num">{{ number_format($item['count']) }}</td>
            <td class="num">{{ $item['percentage'] }}%</td>
        </tr>
        @empty
        <tr>
            <td colspan="3" class="text-center text-muted">No data available</td>
        </tr>
        @endforelse
    </tbody>
</table>

<!-- Category Distribution -->
<div class="section-title">Customer Category Distribution</div>
<table>
    <thead>
        <tr>
            <th>Category</th>
            <th class="num">Count</th>
            <th class="num">Percentage</th>
        </tr>
    </thead>
    <tbody>
        @forelse($categoryDistribution as $item)
        <tr>
            <td>{{ $item['category'] }}</td>
            <td class="num">{{ number_format($item['count']) }}</td>
            <td class="num">{{ $item['percentage'] }}%</td>
        </tr>
        @empty
        <tr>
            <td colspan="3" class="text-center text-muted">No data available</td>
        </tr>
        @endforelse
    </tbody>
</table>

<!-- ID Type Distribution -->
<div class="section-title">ID Type Distribution</div>
<table>
    <thead>
        <tr>
            <th>ID Type</th>
            <th class="num">Count</th>
            <th class="num">Percentage</th>
        </tr>
    </thead>
    <tbody>
        @forelse($idTypeDistribution as $item)
        <tr>
            <td>{{ $item['id_type'] }}</td>
            <td class="num">{{ number_format($item['count']) }}</td>
            <td class="num">{{ $item['percentage'] }}%</td>
        </tr>
        @empty
        <tr>
            <td colspan="3" class="text-center text-muted">No data available</td>
        </tr>
        @endforelse
    </tbody>
</table>

<!-- Registration Trends -->
<div class="section-title">Monthly Registration Trends</div>
<table>
    <thead>
        <tr>
            <th>Month</th>
            <th class="num">New Customers</th>
        </tr>
    </thead>
    <tbody>
        @forelse($registrationTrends as $trend)
        <tr>
            <td>{{ $trend['month'] }}</td>
            <td class="num">{{ number_format($trend['count']) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="2" class="text-center text-muted">No data available</td>
        </tr>
        @endforelse
    </tbody>
</table>

@else
<p class="text-center text-muted">No demographic data available for the selected criteria.</p>
@endif

</body>
</html>
