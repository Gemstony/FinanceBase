<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These indexes optimize the Financial Dashboard queries for better performance.
     * Run this migration to add the recommended indexes from the performance optimization.
     */
    public function up(): void
    {
        // Loans table indexes - for KPI calculations and loan charts
        if (Schema::hasTable('loans')) {
            Schema::table('loans', function (Blueprint $table) {
                // Composite index for subshop + status + is_active queries
                if (!$this->hasIndex('loans', 'idx_loans_subshop_status_active')) {
                    $table->index(['subshop_id', 'status', 'is_active'], 'idx_loans_subshop_status_active');
                }

                // Index for disbursement date filtering (loan charts)
                if (!$this->hasIndex('loans', 'idx_loans_disbursement_date')) {
                    $table->index(['disbursement_date', 'subshop_id', 'status'], 'idx_loans_disbursement_date');
                }

                // Index for customer loan lookups
                if (!$this->hasIndex('loans', 'idx_loans_customer_subshop')) {
                    $table->index(['customer_id', 'subshop_id'], 'idx_loans_customer_subshop');
                }
            });
        }

        // Journal Entries table indexes - for income/expense and cash flow calculations
        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                // Composite index for subshop + date range queries
                if (!$this->hasIndex('journal_entries', 'idx_je_subshop_date')) {
                    $table->index(['subshop_id', 'transaction_date'], 'idx_je_subshop_date');
                }

                // Index for recent transactions
                if (!$this->hasIndex('journal_entries', 'idx_je_date_id')) {
                    $table->index(['transaction_date', 'id'], 'idx_je_date_id');
                }
            });
        }

        // Journal Entry Lines table indexes
        if (Schema::hasTable('journal_entry_lines')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                // Index for account-based aggregations
                if (!$this->hasIndex('journal_entry_lines', 'idx_jel_account_id')) {
                    $table->index(['account_id', 'journal_entry_id'], 'idx_jel_account_id');
                }
            });
        }

        // Loan Payments table indexes - for collections calculations
        if (Schema::hasTable('loan_payments')) {
            Schema::table('loan_payments', function (Blueprint $table) {
                // Composite index for payment date + status queries
                if (!$this->hasIndex('loan_payments', 'idx_payments_date_status')) {
                    $table->index(['payment_date', 'status'], 'idx_payments_date_status');
                }

                // Index for loan-based payment lookups
                if (!$this->hasIndex('loan_payments', 'idx_payments_loan_status')) {
                    $table->index(['loan_id', 'status'], 'idx_payments_loan_status');
                }
            });
        }

        // Loan Restructures table indexes
        if (Schema::hasTable('loan_restructures')) {
            Schema::table('loan_restructures', function (Blueprint $table) {
                // Index for pending restructure counts
                if (!$this->hasIndex('loan_restructures', 'idx_restructures_status_active')) {
                    $table->index(['status', 'is_active'], 'idx_restructures_status_active');
                }
            });
        }

        // Charts of Accounts table indexes - for account classification
        if (Schema::hasTable('charts_of_accounts')) {
            Schema::table('charts_of_accounts', function (Blueprint $table) {
                // Index for subshop + active account lookups
                if (!$this->hasIndex('charts_of_accounts', 'idx_coa_subshop_active')) {
                    $table->index(['subshop_id', 'is_active'], 'idx_coa_subshop_active');
                }

                // Index for account class lookups
                if (!$this->hasIndex('charts_of_accounts', 'idx_coa_class_group')) {
                    $table->index(['account_class_id', 'account_group_id'], 'idx_coa_class_group');
                }
            });
        }

        // Account Classes table - add indexes for code lookups
        if (Schema::hasTable('account_classes')) {
            Schema::table('account_classes', function (Blueprint $table) {
                // Index for code-based lookups
                if (!$this->hasIndex('account_classes', 'idx_ac_code')) {
                    $table->index(['code'], 'idx_ac_code');
                }
            });
        }

        // Customers table indexes
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                // Index for subshop-based customer lookups
                if (!$this->hasIndex('customers', 'idx_customers_subshop')) {
                    $table->index(['subshop_id'], 'idx_customers_subshop');
                }
            });
        }

        // Loan Approvals table - optimize the complex approval query
        if (Schema::hasTable('loan_approvals')) {
            Schema::table('loan_approvals', function (Blueprint $table) {
                // Composite index for active pending approvals
                if (!$this->hasIndex('loan_approvals', 'idx_la_active_pending')) {
                    $table->index(['is_active', 'status', 'loan_id', 'level_order'], 'idx_la_active_pending');
                }
            });
        }

        // Promises to Pay table - optimize due date queries
        if (Schema::hasTable('promises_to_pay')) {
            Schema::table('promises_to_pay', function (Blueprint $table) {
                // Index for due date + status queries
                if (!$this->hasIndex('promises_to_pay', 'idx_promises_due_status')) {
                    $table->index(['promise_date', 'status', 'subshop_id'], 'idx_promises_due_status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all added indexes
        $tables = [
            'loans' => ['idx_loans_subshop_status_active', 'idx_loans_disbursement_date', 'idx_loans_customer_subshop'],
            'journal_entries' => ['idx_je_subshop_date', 'idx_je_date_id'],
            'journal_entry_lines' => ['idx_jel_account_id'],
            'loan_payments' => ['idx_payments_date_status', 'idx_payments_loan_status'],
            'loan_restructures' => ['idx_restructures_status_active'],
            'charts_of_accounts' => ['idx_coa_subshop_active', 'idx_coa_class_group'],
            'account_classes' => ['idx_ac_code'],
            'customers' => ['idx_customers_subshop'],
            'loan_approvals' => ['idx_la_active_pending'],
            'promises_to_pay' => ['idx_promises_due_status'],
        ];

        foreach ($tables as $table => $indexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) use ($indexes) {
                    foreach ($indexes as $index) {
                        try {
                            $table->dropIndex($index);
                        } catch (\Exception $e) {
                            // Index might not exist, continue
                        }
                    }
                });
            }
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function hasIndex(string $table, string $index): bool
    {
        $indexes = \DB::select("SHOW INDEX FROM {$table}");
        foreach ($indexes as $idx) {
            if ($idx->Key_name === $index) {
                return true;
            }
        }
        return false;
    }
};
