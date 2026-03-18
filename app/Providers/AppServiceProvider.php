<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register gates for AdminLTE menu permissions
        Gate::define('Super Admin', function ($user) {
            return $user && $user->hasRole('Super Admin');
        });

        Gate::define('owner', function ($user) {
            return $user && $user->hasRole('owner');
        });

        // Gate for Super Admin OR Owner access
        Gate::define('admin-or-owner', function ($user) {
            return $user && ($user->hasRole('Super Admin') || $user->hasRole('owner'));
        });

        Gate::define('view_shop', function ($user) {
           return $user->hasPermissionTo('view_shop');
        });

        Gate::define('edit_subshop', function ($user) {
           return $user->hasPermissionTo('edit_subshop');
        });

        Gate::define('delete_subshop', function ($user) {
           return $user->hasPermissionTo('delete_subshop');
        });

        Gate::define('view_inventory', function ($user) {
           return $user->hasPermissionTo('view_inventory');
        });

        Gate::define('view_items', function ($user) {
           return $user->hasPermissionTo('view_items');
        });

        Gate::define('view_categories', function ($user) {
           return $user->hasPermissionTo('view_categories');
        }); 
        
        Gate::define('view_sales', function ($user) {
            return $user->hasPermissionTo('view_sales');
        });

        Gate::define('view_pos', function ($user) {
            return $user->hasPermissionTo('view_pos');
        });

        Gate::define('view_invoice_history', function ($user) {
            return $user->hasPermissionTo('view_invoice_history');
        }); 

        Gate::define('view_sales_returns', function ($user) {
            return $user->hasPermissionTo('view_sales_returns');
        });

        Gate::define('view_purchases', function ($user) {
            return $user->hasPermissionTo('view_purchases');
        });

        Gate::define('view_new_purchases', function ($user) {
            return $user->hasPermissionTo('view_new_purchases');
        });

        Gate::define('view_purchase_history', function ($user) {
            return $user->hasPermissionTo('view_purchase_history');
        }); 

        Gate::define('view_purchase_returns', function ($user) {
            return $user->hasPermissionTo('view_purchase_returns');
        }); 

        Gate::define('view_expenses', function ($user) {
            return $user->hasPermissionTo('view_expenses');
        }); 

        Gate::define('view_writeoffs', function ($user) {
            return $user->hasPermissionTo('view_writeoffs');
        }); 

         Gate::define('view_customers', function ($user) {
            return $user->hasPermissionTo('view_customers');
        }); 

        Gate::define('view_suppliers', function ($user) {
            return $user->hasPermissionTo('view_suppliers');
        });  
        Gate::define('view_banks', function ($user) {
            return $user->hasPermissionTo('view_banks');
        }); 

         Gate::define('view_sales_transactions', function ($user) {
            return $user->hasPermissionTo('view_sales_transactions');
        }); 
        
        Gate::define('view_purchase_transactions', function ($user) {
            return $user->hasPermissionTo('view_purchase_transactions');
        }); 
        Gate::define('view_dashboard', function ($user) {
            return $user->hasPermissionTo('view_dashboard');
        }); 

        Gate::define('view_reports', function ($user) {
            return $user->hasPermissionTo('view_reports');
        });  

        Gate::define('view_inventory_report', function ($user) {
            return $user->hasPermissionTo('view_inventory_report');
        }); 

        Gate::define('view_inventory_ledger', function ($user) {
            return $user->hasPermissionTo('view_inventory_ledger');
        }); 

        Gate::define('view_sales_report', function ($user) {
            return $user->hasPermissionTo('view_sales_report');
        }); 

        Gate::define('view_purchases_report', function ($user) {
            return $user->hasPermissionTo('view_purchases_report');
        }); 

         Gate::define('view_profit_and_loss_report', function ($user) {
            return $user->hasPermissionTo('view_profit_and_loss_report');
        }); 

        Gate::define('view_loan_portfolio_report', function ($user) {
            return $user->hasPermissionTo('view_loan_portfolio_report');
        }); 

        Gate::define('view_loan_aging_report', function ($user) {
            return $user->hasPermissionTo('view_loan_aging_report');
        }); 

        Gate::define('view_loan_aging_installment_report', function ($user) {
            return $user->hasPermissionTo('view_loan_aging_installment_report');
        }); 

        Gate::define('view_loan_performance_report', function ($user) {
            return $user->hasPermissionTo('view_loan_performance_report');
        }); 

        Gate::define('view_delinquency_report', function ($user) {
            return $user->hasPermissionTo('view_delinquency_report');
        }); 

        Gate::define('view_loan_disbursement_report', function ($user) {
            return $user->hasPermissionTo('view_loan_disbursement_report');
        }); 

        Gate::define('view_loan_repayment_report', function ($user) {
            return $user->hasPermissionTo('view_loan_repayment_report');
        }); 

        Gate::define('view_loan_outstanding_report', function ($user) {
            return $user->hasPermissionTo('view_loan_outstanding_report');
        });

        Gate::define('view_loan_arrears_report', function ($user) {
            return $user->hasPermissionTo('view_loan_arrears_report');
        });

        Gate::define('view_items_transfers', function ($user) {
            return $user->hasPermissionTo('view_items_transfers');
        }); 

        Gate::define('view_printer_settings', function ($user) {
            return $user->hasPermissionTo('view_printer_settings');
        });  

        Gate::define('view_configure_printer', function ($user) {
            return $user->hasPermissionTo('view_configure_printer');
        }); 

        Gate::define('view_print_jobs', function ($user) {
            return $user->hasPermissionTo('view_print_jobs');
        });

        Gate::define('perform_bank_reconciliation', function ($user) {
            return $user->hasPermissionTo('perform_bank_reconciliation');
        });



    


        // Register authentication log event listeners
        Event::listen(
            \Illuminate\Auth\Events\Login::class,
            \Rappasoft\LaravelAuthenticationLog\Listeners\LoginListener::class
        );

        Event::listen(
            \Illuminate\Auth\Events\Failed::class,
            \Rappasoft\LaravelAuthenticationLog\Listeners\FailedLoginListener::class
        );

        Event::listen(
            \Illuminate\Auth\Events\Logout::class,
            \Rappasoft\LaravelAuthenticationLog\Listeners\LogoutListener::class
        );

        Event::listen(
            \Illuminate\Auth\Events\OtherDeviceLogout::class,
            \Rappasoft\LaravelAuthenticationLog\Listeners\OtherDeviceLogoutListener::class
        );

        // Add dynamic badge to Expenses menu for pending approvals in active subshop
        Event::listen(\JeroenNoten\LaravelAdminLte\Events\BuildingMenu::class, function ($event) {
            try {
                $activeSubshopId = session('subshop_id');
                if (!$activeSubshopId) { return; }
                $pending = \App\Models\Expenses::where('subshop_id', $activeSubshopId)
                    ->where('status', 'pending')
                    ->count();
                if ($pending > 0) {
                    // Share the variable with the view so the label can resolve it
                    view()->share('pending_expenses_count', (string)$pending);
                } else {
                    // Ensure badge is hidden when zero
                    view()->share('pending_expenses_count', '');
                }
            } catch (\Throwable $e) {
                // Fail silently to avoid breaking menu build
            }
        });
    }
}
