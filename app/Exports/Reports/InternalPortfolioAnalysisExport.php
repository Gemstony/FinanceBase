<?php

declare(strict_types=1);

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class InternalPortfolioAnalysisExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $report,
    ) {
    }

    public function sheets(): array
    {
        $r = $this->report;

        return [
            new InternalPortfolioAnalysisArraySheet('Summary', $this->summarySheet($r)),
            new InternalPortfolioAnalysisArraySheet('Health Score', $this->healthSheet($r)),
            new InternalPortfolioAnalysisArraySheet('Profitability', $this->profitabilitySheet($r)),
            new InternalPortfolioAnalysisArraySheet('Risk vs Return', $this->riskReturnSheet($r)),
            new InternalPortfolioAnalysisArraySheet('Officer Performance', $this->officerSheet($r)),
            new InternalPortfolioAnalysisArraySheet('Customer Segments', $this->segmentsSheet($r)),
            new InternalPortfolioAnalysisArraySheet('Loan Cycle', $this->loanCycleSheet($r)),
            new InternalPortfolioAnalysisArraySheet('Cohorts', $this->cohortSheet($r)),
            new InternalPortfolioAnalysisArraySheet('Growth vs Risk', $this->growthRiskSheet($r)),
            new InternalPortfolioAnalysisArraySheet('Income vs Portfolio', $this->incomePortfolioSheet($r)),
            new InternalPortfolioAnalysisArraySheet('Concentration', $this->concentrationSheet($r)),
            new InternalPortfolioAnalysisArraySheet('Early Warning', $this->earlyWarningSheet($r)),
            new InternalPortfolioAnalysisArraySheet('Behavioral Risk', $this->behaviorSheet($r)),
            new InternalPortfolioAnalysisArraySheet('Cross Analysis', $this->crossSheet($r)),
            new InternalPortfolioAnalysisArraySheet('Insights', $this->insightsSheet($r)),
        ];
    }

    private function summarySheet(array $r): array
    {
        $s = $r['summary'] ?? [];

        return [
            ['Metric', 'Value'],
            ['Portfolio Outstanding', (float) ($s['portfolio_outstanding'] ?? 0)],
            ['PAR30 %', (float) ($s['par30_pct'] ?? 0)],
            ['Collection Efficiency %', (float) ($s['collection_efficiency_pct'] ?? 0)],
            ['Default Rate %', (float) ($s['default_rate_pct'] ?? 0)],
            ['Health Score %', (float) ($s['health_score_pct'] ?? 0)],
            ['Health Category', (string) ($s['health_category'] ?? '')],
        ];
    }

    private function healthSheet(array $r): array
    {
        $h = $r['portfolio_health'] ?? [];
        $inputs = $h['inputs'] ?? [];
        $components = $h['components'] ?? [];

        return [
            ['Field', 'Value'],
            ['Score %', (float) ($h['score_pct'] ?? 0)],
            ['Category', (string) ($h['category'] ?? '')],
            ['PAR30 %', (float) ($inputs['par30_pct'] ?? 0)],
            ['Collection Efficiency %', (float) ($inputs['collection_efficiency_pct'] ?? 0)],
            ['Default Rate %', (float) ($inputs['default_rate_pct'] ?? 0)],
            ['PAR Component', (float) ($components['par_component'] ?? 0)],
            ['Collection Component', (float) ($components['collection_component'] ?? 0)],
            ['Default Component', (float) ($components['default_component'] ?? 0)],
        ];
    }

    private function profitabilitySheet(array $r): array
    {
        $rows = $r['profitability_by_product'] ?? [];
        $out = [[
            'Product',
            'Interest Earned',
            'Fees Collected',
            'Penalties Collected',
            'Revenue',
            'Estimated Cost',
            'Profit',
            'PAR30 %',
        ]];

        foreach ($rows as $row) {
            $out[] = [
                (string) ($row['product'] ?? ''),
                (float) ($row['interest_earned'] ?? 0),
                (float) ($row['fees_collected'] ?? 0),
                (float) ($row['penalties_collected'] ?? 0),
                (float) ($row['revenue'] ?? 0),
                (float) ($row['estimated_cost'] ?? 0),
                (float) ($row['profit'] ?? 0),
                (float) ($row['par30_pct'] ?? 0),
            ];
        }

        return $out;
    }

    private function riskReturnSheet(array $r): array
    {
        $rows = $r['risk_vs_return'] ?? [];
        $out = [['Product', 'Profit', 'PAR30 %', 'Risk Level']];

        foreach ($rows as $row) {
            $out[] = [
                (string) ($row['product'] ?? ''),
                (float) ($row['profit'] ?? 0),
                (float) ($row['par30_pct'] ?? 0),
                (string) ($row['risk_level'] ?? ''),
            ];
        }

        return $out;
    }

    private function officerSheet(array $r): array
    {
        $rows = $r['officer_performance'] ?? [];
        $out = [['Officer', 'Total Portfolio', 'PAR30 %', 'Collection Efficiency %', 'Loans Disbursed', 'Score %']];

        foreach ($rows as $row) {
            $out[] = [
                (string) ($row['officer'] ?? ''),
                (float) ($row['total_portfolio'] ?? 0),
                (float) ($row['par30_pct'] ?? 0),
                (float) ($row['collection_efficiency_pct'] ?? 0),
                (int) ($row['loans_disbursed'] ?? 0),
                (float) ($row['score_pct'] ?? 0),
            ];
        }

        return $out;
    }

    private function segmentsSheet(array $r): array
    {
        $rows = $r['customer_segmentation'] ?? [];
        $out = [['Segment', 'Customers', 'Portfolio', 'PAR30 %', '% of Portfolio']];

        foreach ($rows as $row) {
            $out[] = [
                (string) ($row['segment'] ?? ''),
                (int) ($row['customers'] ?? 0),
                (float) ($row['portfolio'] ?? 0),
                (float) ($row['par30_pct'] ?? 0),
                (float) ($row['pct_of_portfolio'] ?? 0),
            ];
        }

        return $out;
    }

    private function loanCycleSheet(array $r): array
    {
        $rows = $r['loan_cycle_analysis'] ?? [];
        $out = [['Cycle', 'Loans', 'Avg Loan Size']];

        foreach ($rows as $row) {
            $out[] = [
                (string) ($row['cycle'] ?? ''),
                (int) ($row['loans'] ?? 0),
                (float) ($row['avg_loan_size'] ?? 0),
            ];
        }

        return $out;
    }

    private function cohortSheet(array $r): array
    {
        $rows = $r['cohort_analysis'] ?? [];
        $out = [['Cohort Month', 'Loans Disbursed', 'Portfolio Outstanding', 'PAR30 %']];

        foreach ($rows as $row) {
            $out[] = [
                (string) ($row['cohort_month'] ?? ''),
                (int) ($row['loans_disbursed'] ?? 0),
                (float) ($row['portfolio_outstanding'] ?? 0),
                (float) ($row['par30_pct'] ?? 0),
            ];
        }

        return $out;
    }

    private function growthRiskSheet(array $r): array
    {
        $rows = $r['growth_vs_risk'] ?? [];
        $out = [['Month', 'Portfolio Outstanding', 'PAR30 %']];

        foreach ($rows as $row) {
            $out[] = [
                (string) ($row['month'] ?? ''),
                (float) ($row['portfolio_outstanding'] ?? 0),
                (float) ($row['par30_pct'] ?? 0),
            ];
        }

        return $out;
    }

    private function incomePortfolioSheet(array $r): array
    {
        $row = $r['income_vs_portfolio'] ?? [];

        return [
            ['Metric', 'Value'],
            ['Interest Income', (float) ($row['interest_income'] ?? 0)],
            ['Average Portfolio', (float) ($row['avg_portfolio'] ?? 0)],
            ['Yield %', (float) ($row['yield_pct'] ?? 0)],
        ];
    }

    private function concentrationSheet(array $r): array
    {
        $c = $r['concentration_risk'] ?? [];

        $out = [['Type', 'Label', 'Exposure', 'Pct']];

        foreach (($c['top_customers'] ?? []) as $row) {
            $out[] = ['Customer', (string) ($row['label'] ?? ''), (float) ($row['exposure'] ?? 0), (float) ($row['pct'] ?? 0)];
        }

        foreach (($c['top_branches'] ?? []) as $row) {
            $out[] = ['Branch', (string) ($row['label'] ?? ''), (float) ($row['exposure'] ?? 0), (float) ($row['pct'] ?? 0)];
        }

        foreach (($c['top_products'] ?? []) as $row) {
            $out[] = ['Product', (string) ($row['label'] ?? ''), (float) ($row['exposure'] ?? 0), (float) ($row['pct'] ?? 0)];
        }

        return $out;
    }

    private function earlyWarningSheet(array $r): array
    {
        $e = $r['early_warning'] ?? [];
        $flags = $e['flags'] ?? [];

        return [
            ['Indicator', 'Flagged'],
            ['Increasing PAR Trend', !empty($flags['increasing_par_trend']) ? 'YES' : 'NO'],
            ['Declining Collection Efficiency', !empty($flags['declining_collection_efficiency']) ? 'YES' : 'NO'],
        ];
    }

    private function behaviorSheet(array $r): array
    {
        $rows = $r['behavioral_risk']['repeat_late_payers'] ?? [];
        $out = [['Customer', 'Late Payments', 'Avg Days Late']];

        foreach ($rows as $row) {
            $out[] = [
                (string) ($row['customer'] ?? ''),
                (int) ($row['late_payments'] ?? 0),
                (float) ($row['avg_days_late'] ?? 0),
            ];
        }

        return $out;
    }

    private function crossSheet(array $r): array
    {
        $rows = $r['cross_analysis'] ?? [];
        $out = [['Product', 'Branch', 'Officer', 'PAR30 %']];

        foreach ($rows as $row) {
            $out[] = [
                (string) ($row['product'] ?? ''),
                (string) ($row['branch'] ?? ''),
                (string) ($row['officer'] ?? ''),
                (float) ($row['par30_pct'] ?? 0),
            ];
        }

        return $out;
    }

    private function insightsSheet(array $r): array
    {
        $rows = $r['strategic_insights'] ?? [];
        $out = [['Insight']];

        foreach ($rows as $row) {
            $out[] = [(string) $row];
        }

        return $out;
    }
}

class InternalPortfolioAnalysisArraySheet implements FromArray, WithTitle
{
    public function __construct(
        private readonly string $title,
        private readonly array $rows,
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->title;
    }
}
