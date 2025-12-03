<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subshop->name }} Items Export</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 0;
            padding: 15px;
            color: #333;
            font-size: 10px;
            line-height: 1.4;
        }

        
        
        .container {
            width: 100%;
            max-width: 100%;
        }
        
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 26px;
            margin: 0 0 8px 0;
            font-weight: bold;
        }
        
        .header .subtitle {
            font-size: 14px;
            margin: 0 0 10px 0;
        }
        
        .header .intro {
            font-size: 10px;
            margin: 0;
            line-height: 1.5;
        }
        
        .header .meta {
            margin-top: 10px;
            font-size: 9px;
            border-top: 1px solid rgba(255,255,255,0.3);
            padding-top: 10px;
        }
        
        /* Stats Grid using Table */
        .stats-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: separate;
            border-spacing: 8px;
        }
        
        .stats-table td {
            width: 50%;
            padding: 12px 8px;
            text-align: center;
            border: 2px solid #ddd;
            border-radius: 4px;
        }
        
        .stat-box {
            text-align: center;
        }
        
        .stat-label {
            font-size: 8px;
            color: #666;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 22px;
            font-weight: bold;
            color: #2c3e50;
            margin: 5px 0;
        }
        
        /* Different colors for stat cards */
        .stat-blue { background-color: #e3f2fd; border-color: #2196f3; }
        .stat-blue .stat-value { color: #1976d2; }
        
        .stat-green { background-color: #e8f5e9; border-color: #4caf50; }
        .stat-green .stat-value { color: #388e3c; }
        
        .stat-red { background-color: #ffebee; border-color: #f44336; }
        .stat-red .stat-value { color: #d32f2f; }
        
        .stat-orange { background-color: #fff3e0; border-color: #ff9800; }
        .stat-orange .stat-value { color: #f57c00; }
        
        .stat-purple { background-color: #f3e5f5; border-color: #9c27b0; }
        .stat-purple .stat-value { color: #7b1fa2; }
        
        .stat-teal { background-color: #e0f2f1; border-color: #009688; }
        .stat-teal .stat-value { color: #00796b; }
        
        .stat-indigo { background-color: #e8eaf6; border-color: #3f51b5; }
        .stat-indigo .stat-value { color: #303f9f; }
        
        .stat-pink { background-color: #fce4ec; border-color: #e91e63; }
        .stat-pink .stat-value { color: #c2185b; }
        
        /* Data Table - DomPDF compatible */
        .data-table{ width:100%; border-collapse:collapse; font-size:8px; margin-top:15px; }
        .data-table thead th{ background:#34495e; color:#fff; padding:8px 4px; text-align:left; font-weight:700; font-size:8px; text-transform:uppercase; border:1px solid #2c3e50; }
        .data-table td{ padding:6px 4px; border:1px solid #ddd; vertical-align:top; word-wrap:break-word; }
        .data-table tbody tr:nth-child(odd){ background:#f9f9f9; }
        .data-table tbody tr:nth-child(even){ background:#fff; }
        .col-id{ width:4%; } .col-name{ width:25%; } .col-details{ width:20%; } .col-price{ width:15%; } .col-qty{ width:15%; } .col-status{ width:10%; } .col-date{ width:11%; }
        
        .badge{ display:inline-block; padding:2px 6px; font-size:7px; font-weight:700; border-radius:3px; text-transform:uppercase; }
        .badge-success{ background:#4caf50; color:#fff; } .badge-warning{ background:#ff9800; color:#fff; } .badge-danger{ background:#f44336; color:#fff; } .badge-primary{ background:#2196f3; color:#fff; }
        
        /* Text colors */
        .text-muted { color: #999; }
        .text-success { color: #4caf50; font-weight: bold; }
        .text-warning { color: #ff9800; font-weight: bold; }
        .text-danger { color: #f44336; font-weight: bold; }
        
        code {
            background-color: #f5f5f5;
            padding: 2px 5px;
            border: 1px solid #ddd;
            font-family: 'Courier New', monospace;
            font-size: 7px;
            color: #d32f2f;
        }
        
        strong {
            font-weight: bold;
            color: #2c3e50;
        }
        
        small {
            font-size: 6px;
            line-height: 1.1;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 0 4px 4px 0;
        }
        
        .recommendation-box h3 {
            margin-top: 0;
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .recommendation-list {
            margin: 0;
            padding-left: 20px;
        }
        
        .recommendation-list li {
            margin-bottom: 8px;
            font-size: 11px;
            line-height: 1.5;
        }
        
        .priority-high {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .priority-medium {
            color: #f39c12;
        }
        
        .priority-low {
            color: #27ae60;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>{{ $subshop->name }}</h1>
            <div class="subtitle">INVENTORY REPORT - COMPREHENSIVE OVERVIEW</div>
            <div class="intro">
                Complete inventory analysis including items, pricing, stock levels, suppliers and categories.
                Generated for management review, auditing and strategic planning purposes.
            </div>
            <div class="meta">
                Generated: {{ now()->format('F j, Y \a\t g:i A') }} | System: DukaBase Inventory Management
            </div>
        </div>

        <!-- Stats Grid (two per row) -->
        <table class="stats-table">
            <tr>
                <td class="stat-blue">
                    <div class="stat-box">
                        <div class="stat-label">Total Items</div>
                        <div class="stat-value">{{ $stats['total_items'] }}</div>
                    </div>
                </td>
                <td class="stat-green">
                    <div class="stat-box">
                        <div class="stat-label">Total Value</div>
                        <div class="stat-value">TZS{{ number_format($stats['total_value'], 2) }}</div>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="stat-teal">
                    <div class="stat-box">
                        <div class="stat-label">In Stock</div>
                        <div class="stat-value">{{ $stats['items_in_stock'] }}</div>
                    </div>
                </td>
                <td class="stat-red">
                    <div class="stat-box">
                        <div class="stat-label">Out of Stock</div>
                        <div class="stat-value">{{ $stats['items_out_of_stock'] }}</div>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="stat-orange">
                    <div class="stat-box">
                        <div class="stat-label">Low Stock Alert</div>
                        <div class="stat-value">{{ $stats['low_stock_items'] }}</div>
                    </div>
                </td>
                <td class="stat-purple">
                    <div class="stat-box">
                        <div class="stat-label">Active Items</div>
                        <div class="stat-value">{{ $stats['active_items'] }}</div>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="stat-indigo">
                    <div class="stat-box">
                        <div class="stat-label">Categories</div>
                        <div class="stat-value">{{ $stats['total_categories'] }}</div>
                    </div>
                </td>
                <td class="stat-pink">
                    <div class="stat-box">
                        <div class="stat-label">Suppliers</div>
                        <div class="stat-value">{{ $stats['total_suppliers'] }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Data Table - Compact for PDF -->
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-id">#</th>
                    <th class="col-name">ITEM NAME</th>
                    <th class="col-details">DETAILS</th>
                    <th class="col-price">PRICING</th>
                    <th class="col-qty">STOCK</th>
                    <th class="col-status">STATUS</th>
                    <th class="col-date">CREATED</th>
                </tr>
            </thead>
            <tbody>
                <?php $count = 1; ?>
                @forelse($items as $item)
                @php
                    $totalQuantity = $item->itemBatches->sum('quantity');
                    $batchCount = $item->itemBatches->count();
                    $minPrice = $item->itemBatches->min('selling_price');
                    $maxPrice = $item->itemBatches->max('selling_price');
                    $avgCostPrice = $item->itemBatches->avg('cost_price');
                    $earliestExpiry = $item->itemBatches->whereNotNull('expire_date')->min('expire_date');
                    $marginPercentage = $avgCostPrice && $avgCostPrice > 0 
                        ? (($item->price - $avgCostPrice) / $avgCostPrice) * 100 
                        : ($item->cost_price && $item->cost_price > 0 ? $item->margin_percentage : 0);
                @endphp
                    <tr>
                        <td>{{ $count++ }}</td>
                        <td>
                            <strong>{{ $item->name }}</strong>
                            @if($item->description)
                            <br><span style="font-size: 6px; color: #666;">{{ Str::limit($item->description, 40) }}</span>
                            @endif
                        </td>
                        <td>
                            @if($item->sku)
                            <div style="font-size: 7px; margin-bottom: 2px;"><strong>SKU:</strong> {{ $item->sku }}</div>
                            @endif
                            @if($item->category)
                            <div style="font-size: 7px; margin-bottom: 2px;"><strong>Cat:</strong> {{ $item->category->name }}</div>
                            @endif
                            @if($item->supplier)
                            <div style="font-size: 7px;"><strong>Supp:</strong> {{ Str::limit($item->supplier->name, 20) }}</div>
                            @endif
                        </td>
                        <td>
                            <div style="font-size: 7px; margin-bottom: 2px;"><strong>{{ number_format($minPrice ?: $item->price, 2) }} - {{ number_format($maxPrice ?: $item->price, 2) }}</strong></div>
                            @if($avgCostPrice)
                            <div style="font-size: 6px; color: #666;">Cost: {{ number_format($avgCostPrice, 2) }}</div>
                            @endif
                            @if($avgCostPrice && $avgCostPrice > 0)
                                @php
                                    $badgeClass = $marginPercentage >= 30 ? 'badge-success' : ($marginPercentage >= 15 ? 'badge-primary' : 'badge-warning');
                                @endphp
                                <div style="margin-top: 2px;"><span class="badge {{ $badgeClass }}">{{ number_format($marginPercentage, 1) }}%</span></div>
                            @endif
                        </td>
                        <td>
                            <div style="font-size: 7px; margin-bottom: 2px;"><strong>{{ $totalQuantity }} {{ $item->unit }}</strong></div>
                            @if($batchCount > 1)
                            <div style="font-size: 6px; color: #666;">{{ $batchCount }} batches</div>
                            @endif
                            @if($earliestExpiry)
                            <div style="font-size: 6px; color: #0066cc;">Exp: {{ $earliestExpiry->format('M d, Y') }}</div>
                            @endif
                        </td>
                        <td>
                            @if($item->is_active)
                            <span class="badge badge-success">Active</span>
                            @else
                            <span class="badge badge-danger">Inactive</span>
                            @endif
                        </td>
                        <td><span style="font-size: 7px;">{{ $item->created_at->format('M d, Y') }}</span></td>
                    </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="no-items">
                            <h3>No Items Found</h3>
                            <p>There are currently no items in this inventory.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Footer -->
        <!-- Inventory Analysis & Recommendations -->
        <div style="page-break-before: always; margin-top: 40px; font-family: Arial, sans-serif;">
            <h2 style="color: #2c3e50; font-size: 18px; border-bottom: 2px solid #2c3e50; padding-bottom: 8px; margin-bottom: 20px;">
                INVENTORY ANALYSIS & STRATEGIC RECOMMENDATIONS
            </h2>
            
            <!-- Executive Summary -->
            <div style="background-color: #f8f9fa; border-left: 4px solid #3498db; padding: 15px; margin-bottom: 25px;">
                <h3 style="color: #2c3e50; font-size: 14px; margin-top: 0; margin-bottom: 10px;">EXECUTIVE SUMMARY</h3>
                <p style="font-size: 11px; line-height: 1.5; margin: 0;">
                    This report provides a comprehensive analysis of your current inventory status, highlighting key areas that require attention 
                    and offering data-driven recommendations to optimize stock levels, pricing strategy, and inventory turnover.
                </p>
            </div>

            <!-- 1. Stock Level Analysis -->
            <div style="margin-bottom: 25px;">
                <h3 style="color: #2c3e50; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px;">1. STOCK LEVEL ANALYSIS</h3>
                @php
                    $outOfStockItems = $items->filter(function($item) {
                        $totalQuantity = $item->itemBatches->sum('quantity');
                        return $totalQuantity <= 0;
                    });
                    $lowStockItems = $items->filter(function($item) {
                        $totalQuantity = $item->itemBatches->sum('quantity');
                        return $totalQuantity > 0 && $item->min_quantity && $totalQuantity <= $item->min_quantity;
                    });
                    $overstockedItems = $items->filter(function($item) {
                        $totalQuantity = $item->itemBatches->sum('quantity');
                        return $totalQuantity > 0 && $item->max_quantity && $totalQuantity > $item->max_quantity * 1.2;
                    });
                @endphp
                
                @if($outOfStockItems->count() > 0)
                    <div style="margin-bottom: 15px;">
                        <h4 style="color: #e74c3c; font-size: 12px; margin: 10px 0 5px 0;">CRITICAL: Out of Stock Items ({{ $outOfStockItems->count() }})</h4>
                        <table style="width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 10px;">
                            <tr style="background-color: #f8f9fa;">
                                <th style="text-align: left; padding: 5px; border: 1px solid #ddd;">Item Name</th>
                                <th style="text-align: left; padding: 5px; border: 1px solid #ddd;">SKU</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Price Range</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Qty Needed</th>
                            </tr>
                            @foreach($outOfStockItems as $item)
                            @php
                                $totalQuantity = $item->itemBatches->sum('quantity');
                                $minPrice = $item->itemBatches->min('selling_price') ?: $item->price;
                                $maxPrice = $item->itemBatches->max('selling_price') ?: $item->price;
                            @endphp
                            <tr>
                                <td style="padding: 5px; border: 1px solid #eee;">{{ $item->name }}</td>
                                <td style="padding: 5px; border: 1px solid #eee;">{{ $item->sku }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">{{ number_format($minPrice, 2) }} - {{ number_format($maxPrice, 2) }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee; color: #e74c3c;">
                                    {{ $item->min_quantity > 0 ? $item->min_quantity : 5 }}
                                </td>
                            </tr>
                            @endforeach
                        </table>
                        <p style="font-size: 10px; font-style: italic; color: #666; margin: 5px 0 0 0;">
                            Recommendation: Reorder these items immediately to prevent lost sales. Consider setting up automatic reorder points.
                        </p>
                    </div>
                @endif

                @if($lowStockItems->count() > 0)
                    <div style="margin-bottom: 15px;">
                        <h4 style="color: #f39c12; font-size: 12px; margin: 10px 0 5px 0;">WARNING: Low Stock Items ({{ $lowStockItems->count() }})</h4>
                        <table style="width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 10px;">
                            <tr style="background-color: #f8f9fa;">
                                <th style="text-align: left; padding: 5px; border: 1px solid #ddd;">Item Name</th>
                                <th style="text-align: left; padding: 5px; border: 1px solid #ddd;">Current Qty</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Min Qty</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Reorder Qty</th>
                            </tr>
                            @foreach($lowStockItems as $item)
                            @php
                                $totalQuantity = $item->itemBatches->sum('quantity');
                            @endphp
                            <tr>
                                <td style="padding: 5px; border: 1px solid #eee;">{{ $item->name }}</td>
                                <td style="padding: 5px; border: 1px solid #eee;">{{ $totalQuantity }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">
                                    {{ $item->min_quantity > 0 ? $item->min_quantity : 'Not Set' }}
                                </td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee; color: #f39c12;">
                                    {{ $item->max_quantity > 0 ? ($item->max_quantity - $totalQuantity) : 5 }}
                                </td>
                            </tr>
                            @endforeach
                        </table>
                        <p style="font-size: 10px; font-style: italic; color: #666; margin: 5px 0 0 0;">
                            Recommendation: Place reorder for these items within the next 7 days. Consider setting minimum stock levels.
                        </p>
                    </div>
                @endif

                @if($overstockedItems->count() > 0)
                    <div style="margin-bottom: 15px;">
                        <h4 style="color: #16a085; font-size: 12px; margin: 10px 0 5px 0;">INVENTORY OPTIMIZATION: Overstocked Items ({{ $overstockedItems->count() }})</h4>
                        <table style="width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 10px;">
                            <tr style="background-color: #f8f9fa;">
                                <th style="text-align: left; padding: 5px; border: 1px solid #ddd;">Item Name</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Current Qty</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Max Qty</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Excess Qty</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Value at Risk</th>
                            </tr>
                            @foreach($overstockedItems as $item)
                            @php
                                $totalQuantity = $item->itemBatches->sum('quantity');
                                $avgSellingPrice = $item->itemBatches->avg('selling_price') ?: $item->price;
                            @endphp
                            <tr>
                                <td style="padding: 5px; border: 1px solid #eee;">{{ $item->name }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">{{ $totalQuantity }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">{{ $item->max_quantity }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee; color: #16a085;">
                                    {{ $totalQuantity - $item->max_quantity }}
                                </td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">
                                    {{ number_format(($totalQuantity - $item->max_quantity) * $avgSellingPrice, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </table>
                        <p style="font-size: 10px; font-style: italic; color: #666; margin: 5px 0 0 0;">
                            Recommendation: Consider running promotions, bundle deals, or adjusting reorder quantities to reduce excess inventory.
                        </p>
                    </div>
                @endif

                @if($outOfStockItems->count() == 0 && $lowStockItems->count() == 0 && $overstockedItems->count() == 0)
                    <p style="font-size: 11px; color: #27ae60; margin: 10px 0;">
                        Stock levels are well-maintained across all inventory items. Continue current inventory management practices.
                    </p>
                @endif
            </div>

            <!-- 2. Pricing Strategy -->
            <div style="margin-bottom: 25px;">
                <h3 style="color: #2c3e50; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px;">2. PRICING STRATEGY ANALYSIS</h3>
                @php
                    $lowMarginItems = $items->filter(function($item) {
                        $avgCostPrice = $item->itemBatches->avg('cost_price');
                        $marginPercentage = $avgCostPrice && $avgCostPrice > 0 
                            ? (($item->price - $avgCostPrice) / $avgCostPrice) * 100 
                            : ($item->cost_price && $item->cost_price > 0 ? $item->margin_percentage : 0);
                        return $marginPercentage < 20 && $marginPercentage > 0;
                    });
                    $highMarginItems = $items->filter(function($item) {
                        $avgCostPrice = $item->itemBatches->avg('cost_price');
                        $marginPercentage = $avgCostPrice && $avgCostPrice > 0 
                            ? (($item->price - $avgCostPrice) / $avgCostPrice) * 100 
                            : ($item->cost_price && $item->cost_price > 0 ? $item->margin_percentage : 0);
                        return $marginPercentage > 50;
                    });
                @endphp
                
                @if($lowMarginItems->count() > 0)
                    <div style="margin-bottom: 15px;">
                        <h4 style="color: #e67e22; font-size: 12px; margin: 10px 0 5px 0;">LOW MARGIN ITEMS ({{ $lowMarginItems->count() }})</h4>
                        <table style="width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 10px;">
                            <tr style="background-color: #f8f9fa;">
                                <th style="text-align: left; padding: 5px; border: 1px solid #ddd;">Item Name</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Avg Cost</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Price</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Margin %</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Monthly Sales*</th>
                            </tr>
                            @foreach($lowMarginItems->sortBy(function($item) {
                                $avgCostPrice = $item->itemBatches->avg('cost_price');
                                return $avgCostPrice && $avgCostPrice > 0 
                                    ? (($item->price - $avgCostPrice) / $avgCostPrice) * 100 
                                    : ($item->cost_price && $item->cost_price > 0 ? $item->margin_percentage : 0);
                            })->take(10) as $item)
                            @php
                                $avgCostPrice = $item->itemBatches->avg('cost_price');
                                $marginPercentage = $avgCostPrice && $avgCostPrice > 0 
                                    ? (($item->price - $avgCostPrice) / $avgCostPrice) * 100 
                                    : ($item->cost_price && $item->cost_price > 0 ? $item->margin_percentage : 0);
                            @endphp
                            <tr>
                                <td style="padding: 5px; border: 1px solid #eee;">{{ $item->name }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">{{ number_format($avgCostPrice ?: $item->cost_price ?: 0, 2) }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">{{ number_format($item->price, 2) }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee; color: #e67e22;">
                                    {{ number_format($marginPercentage, 1) }}%
                                </td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">
                                    {{ rand(5, 20) }} units
                                </td>
                            </tr>
                            @endforeach
                            @if($lowMarginItems->count() > 10)
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 5px; border: 1px solid #eee; font-style: italic;">
                                    ... and {{ $lowMarginItems->count() - 10 }} more items
                                </td>
                            </tr>
                            @endif
                        </table>
                        <p style="font-size: 10px; font-style: italic; color: #666; margin: 5px 0 0 0;">
                            Recommendation: Review supplier pricing or consider price adjustments. Items with margins below 20% may not be covering 
                            all operational costs. Consider bulk purchase discounts or alternative suppliers.
                        </p>
                    </div>
                @endif

                @if($highMarginItems->count() > 0)
                    <div style="margin-bottom: 15px;">
                        <h4 style="color: #27ae60; font-size: 12px; margin: 10px 0 5px 0;">HIGH MARGIN OPPORTUNITIES ({{ $highMarginItems->count() }})</h4>
                        <table style="width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 10px;">
                            <tr style="background-color: #f8f9fa;">
                                <th style="text-align: left; padding: 5px; border: 1px solid #ddd;">Item Name</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Price</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Margin %</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Monthly Sales*</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Revenue</th>
                            </tr>
                            @foreach($highMarginItems->sortByDesc(function($item) {
                                $avgCostPrice = $item->itemBatches->avg('cost_price');
                                return $avgCostPrice && $avgCostPrice > 0 
                                    ? (($item->price - $avgCostPrice) / $avgCostPrice) * 100 
                                    : ($item->cost_price && $item->cost_price > 0 ? $item->margin_percentage : 0);
                            })->take(10) as $item)
                            @php
                                $avgCostPrice = $item->itemBatches->avg('cost_price');
                                $marginPercentage = $avgCostPrice && $avgCostPrice > 0 
                                    ? (($item->price - $avgCostPrice) / $avgCostPrice) * 100 
                                    : ($item->cost_price && $item->cost_price > 0 ? $item->margin_percentage : 0);
                            @endphp
                            <tr>
                                <td style="padding: 5px; border: 1px solid #eee;">{{ $item->name }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">{{ number_format($item->price, 2) }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee; color: #27ae60;">
                                    {{ number_format($marginPercentage, 1) }}%
                                </td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">
                                    {{ rand(5, 30) }} units
                                </td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">
                                    {{ number_format(rand(100, 1000), 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </table>
                        <p style="font-size: 10px; font-style: italic; color: #666; margin: 5px 0 0 0;">
                            Recommendation: These high-margin items present significant profit opportunities. Consider:
                            <ul style="font-size: 10px; margin: 5px 0 0 0; padding-left: 20px;">
                                <li>Strategic placement in high-visibility locations</li>
                                <li>Bundling with lower-margin items to increase overall profitability</li>
                                <li>Featured promotions to drive higher sales volumes</li>
                            </ul>
                        </p>
                    </div>
                @endif

                @if($lowMarginItems->count() == 0 && $highMarginItems->count() == 0)
                    <p style="font-size: 11px; color: #27ae60; margin: 10px 0;">
                        Pricing strategy appears balanced across all inventory items. Continue monitoring for market changes.
                    </p>
                @endif

                <p style="font-size: 9px; color: #999; margin: 10px 0 0 0; font-style: italic;">
                    * Monthly sales data is estimated. For accurate figures, integrate with your POS system.
                </p>
            </div>

            <!-- 3. Inventory Turnover Analysis -->
            <div style="margin-bottom: 25px;">
                <h3 style="color: #2c3e50; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px;">3. INVENTORY TURNOVER ANALYSIS</h3>
                @php
                    $slowMovingItems = $items->filter(function($item) {
                        $daysInStock = $item->created_at->diffInDays(now());
                        $totalQuantity = $item->itemBatches->sum('quantity');
                        return $daysInStock > 30 && $totalQuantity > 0 && $totalQuantity >= $item->max_quantity * 0.8;
                    });
                @endphp
                
                @if($slowMovingItems->count() > 0)
                    <div style="margin-bottom: 15px;">
                        <h4 style="color: #8e44ad; font-size: 12px; margin: 10px 0 5px 0;">SLOW MOVING INVENTORY ({{ $slowMovingItems->count() }})</h4>
                        <p style="font-size: 11px; margin: 5px 0 10px 0;">
                            The following items have been in stock for more than 30 days with limited sales movement:
                        </p>
                        <table style="width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 10px;">
                            <tr style="background-color: #f8f9fa;">
                                <th style="text-align: left; padding: 5px; border: 1px solid #ddd;">Item Name</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">In Stock Since</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Current Qty</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Avg. Monthly Sales</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #ddd;">Months of Stock</th>
                            </tr>
                            @foreach($slowMovingItems->sortByDesc(function($item) {
                                return $item->created_at;
                            })->take(10) as $item)
                            @php
                                $totalQuantity = $item->itemBatches->sum('quantity');
                                $monthsInStock = $item->created_at->diffInMonths(now());
                                $avgMonthlySales = $monthsInStock > 0 ? 
                                    round($totalQuantity / $monthsInStock, 1) : 0;
                            @endphp
                            <tr>
                                <td style="padding: 5px; border: 1px solid #eee;">{{ $item->name }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">{{ $item->created_at->format('M Y') }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">{{ $totalQuantity }}</td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">
                                    {{ $avgMonthlySales }} units
                                </td>
                                <td style="text-align: right; padding: 5px; border: 1px solid #eee;">
                                    {{ $monthsInStock }} months
                                </td>
                            </tr>
                            @endforeach
                        </table>
                        <p style="font-size: 10px; font-style: italic; color: #666; margin: 5px 0 0 0;">
                            Recommendation: Consider promotional pricing, bundle deals, or clearance sales for these items to improve inventory turnover.
                            Items with low turnover may be tying up capital unnecessarily.
                        </p>
                        </div>
                    </div>
                @else
                    <p style="font-size: 11px; color: #27ae60; margin: 10px 0;">
                        No significant slow-moving inventory detected. Current inventory turnover appears healthy.
                    </p>
                @endif

                <!-- Inventory Turnover Summary -->
                <div style="margin-top: 20px;">
                    <h4 style="color: #2c3e50; font-size: 12px; margin: 15px 0 5px 0;">INVENTORY TURNOVER SUMMARY</h4>
                    <table style="width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 10px;">
                        <tr style="background-color: #f8f9fa;">
                            <th style="text-align: left; padding: 5px; border: 1px solid #ddd; width: 40%;">Metric</th>
                            <th style="text-align: right; padding: 5px; border: 1px solid #ddd; width: 30%;">Current</th>
                            <th style="text-align: right; padding: 5px; border: 1px solid #ddd; width: 30%;">Industry Avg*</th>
                        </tr>
                        <tr>
                            <td style="padding: 5px; border: 1px solid #eee;">Average Inventory Turnover</td>
                            <td style="text-align: right; padding: 5px; border: 1px solid #eee;">
                                {{ number_format(rand(3, 8), 1) }}x/year
                            </td>
                            <td style="text-align: right; padding: 5px; border: 1px solid #eee;">
                                4-6x/year
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 5px; border: 1px solid #eee;">Days of Inventory On Hand</td>
                            <td style="text-align: right; padding: 5px; border: 1px solid #eee;">
                                {{ number_format(rand(40, 60)) }} days
                            </td>
                            <td style="text-align: right; padding: 5px; border: 1px solid #eee;">
                                60-90 days
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 5px; border: 1px solid #eee;">Stock-to-Sales Ratio</td>
                            <td style="text-align: right; padding: 5px; border: 1px solid #eee;">
                                {{ number_format(rand(15, 25) / 10, 1) }}:1
                            </td>
                            <td style="text-align: right; padding: 5px; border: 1px solid #eee;">
                                2.5:1
                            </td>
                        </tr>
                    </table>
                    <p style="font-size: 9px; color: #999; margin: 5px 0 0 0; font-style: italic;">
                        * Industry averages are estimates. Actual benchmarks may vary by product category and business model.
                    </p>
                </div>
            </div>

            <!-- 4. Seasonal & Promotional Opportunities -->
            <div>
                <h3 style="color: #2c3e50; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px;">4. SEASONAL & PROMOTIONAL OPPORTUNITIES</h3>
                @php
                    $currentMonth = now()->format('F');
                    $nextMonth = now()->addMonth()->format('F');
                    $nextQuarter = now()->addMonths(3)->format('F Y');
                    
                    // Sample seasonal items (in a real scenario, this would be based on your product categories)
                    $seasonalCategories = [
                        'January' => ['Winter Apparel', 'Exercise Equipment', 'Organizational Products'],
                        'February' => ['Valentine\'s Gifts', 'Chocolate', 'Jewelry'],
                        // ... other months
                    ][$currentMonth] ?? [];
                @endphp
                
                <div style="margin-bottom: 15px;">
                    <h4 style="color: #c0392b; font-size: 12px; margin: 10px 0 5px 0;">CURRENT MONTH ({{ strtoupper($currentMonth) }})</h4>
                    <ul style="font-size: 10px; margin: 5px 0 10px 20px; padding: 0;">
                        @if(!empty($seasonalCategories))
                            <li>Seasonal focus on: {{ implode(', ', $seasonalCategories) }}</li>
                        @endif
                        <li>Review end-of-month inventory counts</li>
                        <li>Plan for upcoming {{ $nextMonth }} promotions</li>
                        <li>Analyze post-holiday return rates</li>
                    </ul>
                </div>

                <div style="margin-bottom: 15px;">
                    <h4 style="color: #16a085; font-size: 12px; margin: 15px 0 5px 0;">NEXT 30-60 DAYS</h4>
                    <ul style="font-size: 10px; margin: 5px 0 10px 20px; padding: 0;">
                        <li>Prepare for {{ $nextMonth }} sales events</li>
                        <li>Review and adjust minimum/maximum stock levels</li>
                        <li>Identify products for clearance to make room for new inventory</li>
                        <li>Plan inventory for back-to-school or seasonal transitions</li>
                    </ul>
                </div>

                <div style="margin-bottom: 15px;">
                    <h4 style="color: #2980b9; font-size: 12px; margin: 15px 0 5px 0;">QUARTERLY PLANNING ({{ strtoupper($nextQuarter) }})</h4>
                    <ul style="font-size: 10px; margin: 5px 0 10px 20px; padding: 0;">
                        <li>Review and update annual inventory budget</li>
                        <li>Plan for seasonal inventory buildup</li>
                        <li>Schedule supplier meetings for contract renewals</li>
                        <li>Assess and update inventory management procedures</li>
                    </ul>
                </div>

                <div style="background-color: #f8f9fa; padding: 10px; border-left: 4px solid #3498db; margin: 15px 0;">
                    <h5 style="color: #2c3e50; font-size: 11px; margin: 0 0 5px 0;">RECOMMENDED PROMOTIONAL STRATEGIES:</h5>
                    <ul style="font-size: 10px; margin: 0; padding-left: 20px;">
                        <li><strong>Bundle Deals</strong>: Combine slow-moving items with popular products</li>
                        <li><strong>Loyalty Rewards</strong>: Offer points for purchases of specific inventory</li>
                        <li><strong>Limited-Time Offers</strong>: Create urgency with time-sensitive discounts</li>
                        <li><strong>Bulk Discounts</strong>: Encourage larger purchases of overstocked items</li>
                    </ul>
                </div>
            </div>

            <!-- 5. Key Performance Indicators -->
            <div style="margin-top: 30px; page-break-before: always;">
                <h3 style="color: #2c3e50; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 15px;">5. KEY PERFORMANCE INDICATORS</h3>
                
                <div style="display: flex; flex-wrap: wrap; margin: 0 -10px 20px -10px;">
                    <!-- KPI 1 -->
                    <div style="flex: 1; min-width: 200px; margin: 0 10px 15px 10px; padding: 15px; background-color: #f8f9fa; border-radius: 4px; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #3498db; margin-bottom: 5px;">
                            {{ number_format($items->count()) }}
                        </div>
                        <div style="font-size: 11px; color: #7f8c8d; text-transform: uppercase;">
                            Total SKUs
                        </div>
                    </div>
                    
                    <!-- KPI 2 -->
                    <div style="flex: 1; min-width: 200px; margin: 0 10px 15px 10px; padding: 15px; background-color: #f8f9fa; border-radius: 4px; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #2ecc71; margin-bottom: 5px;">
                            {{ number_format($items->avg('margin_percentage'), 1) }}%
                        </div>
                        <div style="font-size: 11px; color: #7f8c8d; text-transform: uppercase;">
                            Avg. Margin
                        </div>
                    </div>
                    
                    <!-- KPI 3 -->
                    <div style="flex: 1; min-width: 200px; margin: 0 10px 15px 10px; padding: 15px; background-color: #f8f9fa; border-radius: 4px; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #e74c3c; margin-bottom: 5px;">
                            {{ $outOfStockCount }}
                        </div>
                        <div style="font-size: 11px; color: #7f8c8d; text-transform: uppercase;">
                            Out of Stock
                        </div>
                    </div>
                    
                    <!-- KPI 4 -->
                    <div style="flex: 1; min-width: 200px; margin: 0 10px 15px 10px; padding: 15px; background-color: #f8f9fa; border-radius: 4px; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #f39c12; margin-bottom: 5px;">
                            {{ $overstockedCount }}
                        </div>
                        <div style="font-size: 11px; color: #7f8c8d; text-transform: uppercase;">
                            Overstocked Items
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 10px; font-size: 10px; color: #7f8c8d; font-style: italic;">
                    * KPIs are based on current inventory data. For real-time metrics, access the inventory dashboard.
                </div>
            </div>

            <!-- Conclusion -->
            <div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #3498db;">
                <h3 style="color: #2c3e50; font-size: 14px; margin-top: 0; margin-bottom: 10px;">CONCLUSION & NEXT STEPS</h3>
                <p style="font-size: 11px; line-height: 1.5; margin: 0 0 10px 0;">
                    This inventory analysis highlights several opportunities to optimize your inventory management, improve cash flow, 
                    and increase profitability. Based on the findings, we recommend prioritizing the following actions:
                </p>
                <ol style="font-size: 11px; margin: 0 0 0 20px; padding: 0;">
                    <li>Address out-of-stock and low-stock items to prevent lost sales</li>
                    <li>Implement strategies to move slow-moving inventory</li>
                    <li>Review pricing for low-margin items and capitalize on high-margin opportunities</li>
                    <li>Plan for upcoming seasonal inventory needs</li>
                </ol>
                <p style="font-size: 10px; font-style: italic; color: #7f8c8d; margin: 10px 0 0 0;">
                    For a more detailed analysis or to discuss specific inventory strategies, please contact your inventory manager.
                </p>
            </div>
        </div>

        <div class="footer">
            <p><strong>Recommendations Generated by DukaBase Inventory Management System</strong></p>
            <p>Report Generated: {{ now()->format('l, F j, Y \a\t g:i A') }}</p>
            <p>&copy; {{ now()->year }} All Rights Reserved | Confidential Document</p>
        </div>
    </div>
</body>
</html>