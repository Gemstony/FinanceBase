<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Sales Report - {{ $subshopName ?? 'All Locations' }}</title>
    <style>
        @page { 
            margin: 20px 15px;
            font-family: 'DejaVu Sans', Arial, sans-serif;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            font-size: 10px;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .container {
            width: 100%;
            max-width: 100%;
            padding: 0;
        }
        
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 25px 20px;
            margin: 10px;
            text-align: center;
            margin: 0 -15px 20px -15px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 24px;
            margin: 0 0 10px 0;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .header .subtitle {
            font-size: 12px;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .header .period {
            font-size: 11px;
            margin: 0 0 10px 0;
            opacity: 0.9;
            font-weight: 400;
        }
        
        .header .meta {
            margin-top: 15px;
            font-size: 8px;
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 8px;
            opacity: 0.8;
            font-weight: 300;
            letter-spacing: 0.3px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
            margin: 0 -5px 25px -5px;
        }
        
        .stat-box {
            background: white;
            padding: 15px 10px;
            text-align: center;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            margin: 5px;
        }
        
        .stat-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            opacity: 0.9;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin: 3px 0;
            line-height: 1.2;
        }
        
        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 20px 0 30px 0;
            font-size: 9px;
            background: white;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .data-table th {
            background-color: #2c3e50;
            color: white;
            padding: 10px 12px;
            text-align: left;
            text-transform: uppercase;
            font-size: 8.5px;
            font-weight: 600;
            letter-spacing: 0.5px;
            border: none;
        }
        
        .data-table td {
            padding: 9px 12px;
            border-bottom: 1px solid #f0f0f0;
            border-right: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table td:last-child {
            border-right: none;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #fafbfc;
        }
        
        .data-table tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Section Headers */
        .section-header {
            color: #2c3e50;
            font-size: 12px;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 6px;
            margin: 35px 0 18px 0;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            position: relative;
            padding-left: 10px;
        }
        
        .section-header:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -2px;
            width: 50px;
            height: 2px;
            background-color: #2c3e50;
        }
        
        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 8.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }
        
        .badge-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .badge-warning {
            background-color: #fff3e0;
            color: #e65100;
            border: 1px solid #ffe0b2;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .badge-danger {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .text-right {
            text-align: right;
            font-family: 'DejaVu Sans Mono', monospace;
            font-feature-settings: "tnum";
            font-variant-numeric: tabular-nums;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #777;
            opacity: 0.8;
        }
        
        .page-break {
            page-break-before: always;
            break-before: page;
        }
        
        .page-break-avoid {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .footer {
            text-align: center;
            margin: 40px 0 10px 0;
            padding: 15px 0 0 0;
            font-size: 8px;
            color: #999;
            border-top: 1px solid #f0f0f0;
            position: relative;
        }
        
        .footer:before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 1px;
            background-color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>SALES REPORT</h1>
            <div class="subtitle">{{ $subshopName ?? 'All Locations' }}</div>
            <div class="period">{{ \Carbon\Carbon::parse($dateFrom)->format('F j, Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('F j, Y') }}</div>
            <div class="meta">
                Generated on {{ now()->format('F j, Y \a\t g:i A') }} | DukaBase Sales Analytics
            </div>
        </div>
    <div class="">
    
        <!-- KPIs -->
        <div class="stats-grid">
            <div class="stat-box stat-blue">
                <div class="stat-label">Net Sales</div>
                <div class="stat-value">{{ number_format($kpi['net_sales'], 2) }}</div>
            </div>
            <div class="stat-box stat-green">
                <div class="stat-label">Total Orders</div>
                <div class="stat-value">{{ $kpi['orders'] }}</div>
            </div>
            <div class="stat-box stat-blue">
                <div class="stat-label">Avg. Order Value</div>
                <div class="stat-value">{{ number_format($kpi['aov'], 2) }}</div>
            </div>
            <div class="stat-box stat-green">
                <div class="stat-label">Units Sold</div>
                <div class="stat-value">{{ $kpi['units'] }}</div>
            </div>
            <div class="stat-box stat-blue">
                <div class="stat-label">Gross Profit</div>
                <div class="stat-value">{{ number_format($kpi['gross_profit'], 2) }}</div>
            </div>
            <div class="stat-box stat-green">
                <div class="stat-label">Margin %</div>
                <div class="stat-value">{{ number_format($kpi['margin_pct'], 2) }}%</div>
            </div>
        </div>

        <!-- Orders List -->
        <h3 class="section-header">Recent Orders</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date & Time</th>
                    <th>Customer</th>
                    <th>Subshop</th>
                    <th class="text-right">Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ordersList as $order)
                <tr>
                    <td>{{ $order->invoice_number }}</td>
                    <td>{{ \Carbon\Carbon::parse($order->created_at)->format('M j, Y H:i') }}</td>
                    <td>{{ $order->customer_name ?? 'Walk-in Customer' }}</td>
                    <td>{{ $order->subshop_name ?? 'N/A' }}</td>
                    <td class="text-right">{{ number_format($order->grand_total, 2) }}</td>
                    <td>
                        @php
                            $statusClass = 'badge-warning';
                            if ($order->status === 'completed') $statusClass = 'badge-success';
                            if ($order->status === 'cancelled') $statusClass = 'badge-danger';
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ ucfirst($order->status) }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center">No orders found for the selected period</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Product Performance -->
        <div class="page-break"></div>
        <h3 class="section-header">Product Performance</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Product</th>
                        <th style="width: 15%;">Category</th>
                        <th class="text-center" style="width: 10%;">Qty Sold</th>
                        <th class="text-right" style="width: 15%;">Revenue (Tsh)</th>
                        <th class="text-right" style="width: 15%;">Profit (Tsh)</th>
                        <th class="text-right" style="width: 15%;">Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productPerformance as $product)
                    @php
                        $profit = $product->profit ?? ($product->revenue - ($product->cogs ?? 0));
                        $margin = $product->revenue > 0 ? ($profit / $product->revenue) * 100 : 0;
                        $marginClass = 'text-success';
                        if ($margin < 10) $marginClass = 'text-danger';
                        elseif ($margin < 20) $marginClass = 'text-warning';
                    @endphp
                    <tr class="product-row">
                        <td>
                            <div class="product-name">{{ $product->product_name }}</div>
                            @if($product->sku)
                            <div class="text-muted small">SKU: {{ $product->sku }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="category-badge">{{ $product->category_name ?: 'Uncategorized' }}</span>
                        </td>
                        <td class="text-center">
                            <span class="quantity-badge">{{ number_format($product->quantity_sold) }}</span>
                        </td>
                        <td class="text-right">{{ number_format($product->revenue, 2) }}</td>
                        <td class="text-right {{ $profit < 0 ? 'text-danger' : '' }}">
                            {{ number_format($profit, 2) }}
                        </td>
                        <td class="text-right {{ $marginClass }}">
                            {{ number_format($margin, 1) }}%
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-3">
                            <div class="empty-state">
                                <i class="fas fa-box-open fa-2x mb-2"></i>
                                <p class="mb-0">No product performance data available</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if(count($productPerformance) > 0)
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-right"><strong>Total:</strong></td>
                        <td class="text-center"><strong>{{ number_format($productPerformance->sum('quantity_sold')) }}</strong></td>
                        <td class="text-right"><strong>{{ number_format($productPerformance->sum('revenue'), 2) }}</strong></td>
                        @php
                            $totalProfit = $productPerformance->sum(function($item) {
                                return $item->profit ?? ($item->revenue - ($item->cogs ?? 0));
                            });
                            $totalRevenue = $productPerformance->sum('revenue');
                            $avgMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;
                        @endphp
                        <td class="text-right"><strong>{{ number_format($totalProfit, 2) }}</strong></td>
                        <td class="text-right"><strong>{{ number_format($avgMargin, 1) }}%</strong></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
        
        <style>
            .product-row:hover {
                background-color: #f8f9fa !important;
            }
            
            .product-name {
                font-weight: 500;
                margin-bottom: 2px;
            }
            
            .category-badge {
                display: inline-block;
                background-color: #f0f4f8;
                color: #4a5568;
                padding: 2px 8px;
                border-radius: 10px;
                font-size: 8.5px;
                font-weight: 500;
                white-space: nowrap;
            }
            
            .quantity-badge {
                display: inline-block;
                background-color: #e6f7ff;
                color: #1890ff;
                padding: 2px 8px;
                border-radius: 10px;
                font-weight: 600;
                min-width: 40px;
                text-align: center;
            }
            
            .empty-state {
                padding: 15px 0;
                color: #718096;
            }
            
            .empty-state i {
                opacity: 0.6;
                margin-bottom: 8px;
            }
            
            .text-success {
                color: #2e7d32 !important;
            }
            
            .text-warning {
                color: #ed6c02 !important;
            }
            
            .text-danger {
                color: #d32f2f !important;
            }
            
            table.data-table tfoot {
                background-color: #f8fafc;
                font-weight: 600;
            }
            
            table.data-table tfoot td {
                padding-top: 10px;
                padding-bottom: 10px;
                border-top: 2px solid #e2e8f0;
            }
        </style>

        <!-- Returns -->
        <div class="page-break"></div>
        <div class="section">
            <h3 class="section-header">Sales Returns</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Return #</th>
                        <th>Date</th>
                        <th>Order #</th>
                        <th>Product</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Amount</th>
                        <th>Reason</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returnsList as $return)
                    <tr>
                        <td>RTN-{{ str_pad($return->id, 6, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ \Carbon\Carbon::parse($return->created_at)->format('M j, Y') }}</td>
                        <td>{{ $return->invoice_number }}</td>
                        <td>{{ $return->product_name }}</td>
                        <td class="text-right">{{ $return->quantity_returned }}</td>
                        <td class="text-right">{{ number_format($return->return_amount, 2) }}</td>
                        <td>{{ $return->reason ? ucfirst($return->reason) : 'Not specified' }}</td>
                        <td>
                            @if($return->processed_by)
                                <span class="badge badge-success">Processed</span>
                            @else
                                <span class="badge badge-warning">Pending</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center">No return records found for the selected period</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Report Generated by DukaBase Sales Analytics</strong></p>
            <p>{{ now()->format('l, F j, Y \a\t g:i A') }} | Page <span class="page-number"></span></p>
        </div>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
            $size = 9;
            $font = $fontMetrics->getFont("DejaVu Sans");
            $font = $fontMetrics->getFont("Arial, sans-serif");
            $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 15;
            $pdf->page_text($x, $y, $text, $font, $size);
        }
    </script>
</body>
</html>
