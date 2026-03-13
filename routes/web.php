<?php

use App\Http\Controllers\AccountClassController;
use App\Http\Controllers\AccountGroupsController;
use App\Http\Controllers\AccountingSettingsController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\ChartsOfAccountController;
use App\Http\Controllers\CollateralTypesController;
use App\Http\Controllers\CustomerCollateralsController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\Deposits\DepositAccountsController;
use App\Http\Controllers\Deposits\DepositProductsController;
use App\Http\Controllers\DisbursementMethodController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InterestCycleController;
use App\Http\Controllers\InterestMethodsController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\LoanFeesController;
use App\Http\Controllers\LoanGroupController;
use App\Http\Controllers\LoanPenaltiesController;
use App\Http\Controllers\LoanProductTypesController;
use App\Http\Controllers\LoansController;
use App\Http\Controllers\LoansSettingsController;
use App\Http\Controllers\Accounting\ManualJournalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrdersController;
use App\Http\Controllers\PurchaseReturnsController;
use App\Http\Controllers\RepaymentFrequenciesController;
use App\Http\Controllers\RolesPermissionsController;
use App\Http\Controllers\SalesReturnsController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ShopsManagementController;
use App\Http\Controllers\SubShopController;
use App\Http\Controllers\SuppliersController;
use App\Http\Controllers\ItemsController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\UISettingsController;
use App\Http\Controllers\WriteOffsController;
use App\Http\Controllers\ExpensesController;
use App\Http\Controllers\BankAccountsController;
use App\Http\Controllers\BanksController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PurchasesController;
use App\Http\Controllers\UsersManagementController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\SubshopSelectionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\InventoryReportController;
use App\Http\Controllers\PurchasesReportController;
use App\Http\Controllers\ProfitAndLossReportController;
use App\Http\Controllers\PrinterSettingsController;
use App\Http\Controllers\PrintJobsController;
use App\Http\Controllers\SmsManagementController;
use App\Http\Controllers\LoanRepaymentController;
use App\Http\Controllers\Loans\Risk\PortfolioRiskController;
use App\Http\Controllers\Loans\Risk\CollectionsController;
use App\Http\Controllers\Loans\Credits\CustomerCreditsController;
use App\Http\Controllers\Loans\SecurityDeposits\SecurityDepositsController;
use App\Http\Controllers\BankReconciliationController;

use Illuminate\Http\Request;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::prefix('risk')->group(function () {
    Route::get('/portfolio', [PortfolioRiskController::class, 'dashboard'])->name('risk.portfolio');
    Route::get('/delinquent', [PortfolioRiskController::class, 'delinquentLoans'])->name('risk.delinquent.index');
    Route::get('/delinquent/{days}', [PortfolioRiskController::class, 'delinquentByDays'])->name('risk.delinquent.by_days');
    Route::get('/collections', [CollectionsController::class, 'index'])->name('risk.collections');
});
// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

//    // Shop setup routes
//     Route::get('/shop/setup', [ShopController::class, 'create'])->name('shop.create');
//     Route::post('/shop/setup', [ShopController::class, 'store'])->name('shop.store');
    
//     // Protected routes - require shop setup
//     Route::middleware(['has.shop'])->group(function () {
        
//         // Shop management routes
//         Route::get('/shop', [ShopController::class, 'show'])->name('shop.show');
//         Route::get('/shop/edit', [ShopController::class, 'edit'])->name('shop.edit');
//         Route::put('/shop', [ShopController::class, 'update'])->name('shop.update');
        

//     });
    
// });


Route::middleware(['auth'])->group(function () {


    // API Routes
    Route::get('/api/items/summary', [ItemsController::class, 'getSummaryData'])->name('api.items.summary');
    
    // Shop setup routes
    Route::get('/shop/setup', [ShopController::class, 'create'])->name('shop.create');
    Route::post('/shop/setup', [ShopController::class, 'store'])->name('shop.store');
    
    // Profile routes
    Route::get('/settings/profile', function () {
        $user = auth()->user();
        $permissionNames = [];
        if ($user) {
            try {
                if (method_exists($user, 'getAllPermissions') && method_exists($user, 'roles')) {
                    $roles = $user->roles;
                    if ($roles && $roles->contains('name', 'Super Admin')) {
                        // Show only Super Admin permissions
                        $superAdminRole = $roles->where('name', 'Super Admin')->first();
                        if ($superAdminRole && method_exists($superAdminRole, 'permissions')) {
                            $permissionNames = $superAdminRole->permissions->pluck('name')->toArray();
                        }
                    } else {
                        // Show all permissions from all roles
                        $permissions = $user->getAllPermissions();
                        if ($permissions) {
                            $permissionNames = $permissions->pluck('name')->toArray();
                        }
                    }
                }
            } catch (\Exception $e) {
                $permissionNames = [];
            }
        }
        return view('settings.profile', compact('permissionNames'));
    })->name('settings.profile.show');
    Route::post('/settings/profile', [ProfileController::class, 'updateBasic'])->name('settings.profile.update');
    Route::post('/settings/profile/photo', [ProfileController::class, 'updatePhoto'])->name('settings.profile.photo');
    Route::post('/settings/profile/password', [ProfileController::class, 'updatePassword'])->name('settings.profile.password');
    // Password settings page
    Route::get('/settings/password', function () {
        return view('settings.password');
    })->name('settings.password.show');
    
    // Roles and Permissions routes
    Route::get('/settings/roles-permissions', [RolesPermissionsController::class, 'index'])->name('settings.roles-permissions.show');
    Route::post('/settings/roles-permissions/role', [RolesPermissionsController::class, 'createRole'])->name('settings.roles-permissions.create-role');
    Route::post('/settings/roles-permissions/permission', [RolesPermissionsController::class, 'createPermission'])->name('settings.roles-permissions.create-permission');
    Route::post('/settings/roles-permissions/assign', [RolesPermissionsController::class, 'assignPermissionsToRole'])->name('settings.roles-permissions.assign');
    Route::put('/settings/roles-permissions/role/{role}', [RolesPermissionsController::class, 'editRole'])->name('settings.roles-permissions.edit-role');
    Route::delete('/settings/roles-permissions/role/{role}', [RolesPermissionsController::class, 'deleteRole'])->name('settings.roles-permissions.delete-role');
    Route::put('/settings/roles-permissions/permission/{permission}', [RolesPermissionsController::class, 'editPermission'])->name('settings.roles-permissions.edit-permission');
    Route::delete('/settings/roles-permissions/permission/{permission}', [RolesPermissionsController::class, 'deletePermission'])->name('settings.roles-permissions.delete-permission');
    
    // Routes that require authentication but not necessarily shop ownership
    Route::middleware(['auth'])->group(function () {
        // Shop status page (for inactive/suspended shops - accessible even without active shop)
        Route::get('/shop/status', function () {
            return view('shop.status');
        })->name('shop.status');

        // Send message to super admin (accessible even without active shop)
        Route::post('/messages/send-to-super-admin', [MessageController::class, 'sendToSuperAdmin'])->name('messages.send-to-super-admin');
    });

    // Routes that require shop but NOT subshop context
    Route::middleware(['has.shop'])->group(function () {
        // Subshop chooser
        Route::get('/subshops/choose', [SubshopSelectionController::class, 'index'])->name('subshops.choose');
        Route::post('/subshops/choose', [SubshopSelectionController::class, 'store'])->name('subshops.choose.store');

        // Messages routes (shop-wide, not subshop-specific)
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/create', [MessageController::class, 'create'])->name('messages.create');
        Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
        Route::get('/messages/{message}', [MessageController::class, 'show'])->name('messages.show');
        Route::put('/messages/{message}', [MessageController::class, 'update'])->name('messages.update');
        Route::post('/messages/{message}/read', [MessageController::class, 'markAsRead'])->name('messages.read');
        Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

        // Messages API routes
        Route::get('/api/messages/unread-count', [MessageController::class, 'getUnreadCount'])->name('api.messages.unread-count');
        Route::get('/api/messages/recent', [MessageController::class, 'getRecentMessages'])->name('api.messages.recent');

        // Users management (owners only)
        Route::get('/admin/users', [UsersManagementController::class, 'index'])->name('users.index');
        Route::post('/admin/users', [UsersManagementController::class, 'store'])->name('users.store');
        Route::post('/admin/users/{user}/assign-subshops', [UsersManagementController::class, 'assignSubshops'])->name('users.assign-subshops');
        Route::get('/admin/users/{user}/edit', [UsersManagementController::class, 'edit'])->name('users.edit');
        Route::put('/admin/users/{user}', [UsersManagementController::class, 'update'])->name('users.update');
        Route::delete('/admin/users/{user}', [UsersManagementController::class, 'destroy'])->name('users.destroy');
    });

  
    // Protected routes - require shop setup or assignment, and enforce subshop access context
    Route::middleware(['has.shop','subshop.access'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('can:view_dashboard')
            ->name('dashboard');

        Route::prefix('bank-reconciliation')
            ->middleware('can:perform_bank_reconciliation')
            ->name('bank-reconciliation.')
            ->group(function () {
                Route::get('/', [BankReconciliationController::class, 'index'])->name('index');
                Route::get('/create', [BankReconciliationController::class, 'create'])->name('create');
                Route::post('/', [BankReconciliationController::class, 'store'])->name('store');
                Route::get('/{id}', [BankReconciliationController::class, 'show'])->whereNumber('id')->name('show');
                Route::post('/{id}/import', [BankReconciliationController::class, 'import'])->whereNumber('id')->name('import');
                Route::get('/{id}/reconcile', [BankReconciliationController::class, 'reconcile'])->whereNumber('id')->name('reconcile');
                Route::post('/{id}/auto-match', [BankReconciliationController::class, 'autoMatch'])->whereNumber('id')->name('auto-match');
                Route::post('/{id}/reset-matches', [BankReconciliationController::class, 'resetMatches'])->whereNumber('id')->name('reset-matches');
                Route::post('/match-line', [BankReconciliationController::class, 'matchLine'])->name('match-line');
                Route::get('/{id}/lines/{lineId}/create-journal', [BankReconciliationController::class, 'createJournal'])
                    ->whereNumber('id')
                    ->whereNumber('lineId')
                    ->name('lines.create-journal');
                Route::post('/{id}/lines/{lineId}/create-journal', [BankReconciliationController::class, 'storeJournal'])
                    ->whereNumber('id')
                    ->whereNumber('lineId')
                    ->name('lines.store-journal');
                Route::post('/{id}/finalize', [BankReconciliationController::class, 'finalize'])->whereNumber('id')->name('finalize');
            });

        Route::prefix('credits')->group(function () {
            Route::get('/', [CustomerCreditsController::class, 'index'])->name('credits.index');
            Route::get('/{customer}', [CustomerCreditsController::class, 'show'])->name('credits.show');
            Route::post('/apply', [CustomerCreditsController::class, 'apply'])->name('credits.apply');
            Route::post('/refund', [CustomerCreditsController::class, 'refund'])->name('credits.refund');
        });

        Route::prefix('deposits')->group(function () {
            Route::get('/', [DepositAccountsController::class, 'index'])->name('deposits.index');
            Route::get('/create', [DepositAccountsController::class, 'create'])->name('deposits.create');
            Route::post('/', [DepositAccountsController::class, 'store'])->name('deposits.store');
            Route::delete('/{deposit_account}', [DepositAccountsController::class, 'destroy'])
                ->whereNumber('deposit_account')
                ->name('deposits.destroy');
            Route::get('/{customer}', [DepositAccountsController::class, 'show'])
                ->whereNumber('customer')
                ->name('deposits.show');
            Route::get('/{deposit_account}/transactions', [DepositAccountsController::class, 'transactions'])
                ->whereNumber('deposit_account')
                ->name('deposits.transactions');
            Route::post('/deposit', [DepositAccountsController::class, 'deposit'])->name('deposits.deposit');
            Route::post('/withdraw', [DepositAccountsController::class, 'withdraw'])->name('deposits.withdraw');
            Route::post('/transfer', [DepositAccountsController::class, 'transfer'])->name('deposits.transfer');
            Route::post('/pay-loan', [DepositAccountsController::class, 'payLoan'])->name('deposits.pay-loan');

            Route::prefix('products')->name('deposits.products.')->group(function () {
                Route::get('/', [DepositProductsController::class, 'index'])->name('index');
                Route::get('/create', [DepositProductsController::class, 'create'])->name('create');
                Route::post('/', [DepositProductsController::class, 'store'])->name('store');
                Route::get('/{product}/edit', [DepositProductsController::class, 'edit'])->name('edit');
                Route::put('/{product}', [DepositProductsController::class, 'update'])->name('update');
                Route::delete('/{product}', [DepositProductsController::class, 'destroy'])->name('destroy');
            });
        });

        Route::prefix('security-deposits')->group(function () {
            Route::get('/', [SecurityDepositsController::class, 'index'])->name('security-deposits.index');
            Route::get('/borrower/{customer}', [SecurityDepositsController::class, 'borrower'])->name('security-deposits.borrower');
            Route::get('/loan/{loan:loan_code}', [SecurityDepositsController::class, 'loan'])->name('security-deposits.loan');
            Route::get('/collect/{loan:loan_code}', [SecurityDepositsController::class, 'collectForm'])->name('security-deposits.collect.form');
            Route::post('/refund', [SecurityDepositsController::class, 'refund'])->name('security-deposits.refund');
            Route::post('/apply', [SecurityDepositsController::class, 'apply'])->name('security-deposits.apply');
            Route::post('/forfeit', [SecurityDepositsController::class, 'forfeit'])->name('security-deposits.forfeit');
        });

        Route::post('/loans/{loan:loan_code}/security-deposit', [SecurityDepositsController::class, 'collect'])
            ->name('security-deposits.collect');

        //Accounting routes
        //charts of account
        Route::get('/accounting/charts_of_account/', [ChartsOfAccountController::class, 'index'])->name('accounting.charts_of_account.index');
        Route::get('/accounting/charts_of_account/export/{format}', [ChartsOfAccountController::class, 'export'])->name('accounting.charts_of_account.export');
        Route::post('/accounting/charts_of_account', [ChartsOfAccountController::class, 'store'])->name('accounting.charts_of_account.store');
        Route::put('/accounting/charts_of_account/{account}', [ChartsOfAccountController::class, 'update'])->name('accounting.charts_of_account.update');
        Route::delete('/accounting/charts_of_account/{account}', [ChartsOfAccountController::class, 'destroy'])->name('accounting.charts_of_account.destroy');

        //accounting settings
        Route::get('/accounting/accounting_settings', [AccountingSettingsController::class, 'index'])->name('accounting.accounting_settings.index');

        // Account Class Routes
        Route::prefix('accounting/account_class')->name('accounting.account_class.')->group(function () {
            Route::get('/', [AccountClassController::class, 'index'])->name('index');
            Route::post('/', [AccountClassController::class, 'store'])->name('store');
            Route::put('/{accountClass}', [AccountClassController::class, 'update'])->name('update');
            Route::delete('/{accountClass}', [AccountClassController::class, 'destroy'])->name('destroy');
        });


        // Account group Routes
        Route::prefix('accounting/account_groups')->name('accounting.account_groups.')->group(function () {
            Route::get('/', [AccountGroupsController::class, 'index'])->name('index');
            Route::post('/', [AccountGroupsController::class, 'store'])->name('store');
            Route::put('/{accountGroup}', [AccountGroupsController::class, 'update'])->name('update');
            Route::delete('/{accountGroup}', [AccountGroupsController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('accounting/manual-journals')->name('accounting.manual-journals.')->group(function () {
            Route::get('/', [ManualJournalController::class, 'index'])->name('index');
            Route::get('/create', [ManualJournalController::class, 'create'])->name('create');
            Route::post('/', [ManualJournalController::class, 'store'])->name('store');
            Route::get('/{id}', [ManualJournalController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/{id}/post', [ManualJournalController::class, 'post'])->whereNumber('id')->name('post');
            Route::post('/{id}/reverse', [ManualJournalController::class, 'reverse'])->whereNumber('id')->name('reverse');
        });



        //loans settings
        Route::get('/loans/loans_settings', [LoansSettingsController::class, 'index'])->name('loans.loans_settings.index');

        // Loan product types Routes
        Route::prefix('loans/loan_product_types')->name('loans.loan_product_types.')->group(function () {
            Route::get('/', [LoanProductTypesController::class, 'index'])->name('index');
            Route::post('/', [LoanProductTypesController::class, 'store'])->name('store');
            Route::put('/{productType}', [LoanProductTypesController::class, 'update'])->name('update');
            Route::delete('/{productType}', [LoanProductTypesController::class, 'destroy'])->name('destroy');
        });



        // Repayment frequencies Routes
        Route::prefix('loans/loans_settings/repayment_frequencies')->name('loans.repayment_frequencies.')->group(function () {
            Route::get('/', [RepaymentFrequenciesController::class, 'index'])->name('index');
            Route::post('/', [RepaymentFrequenciesController::class, 'store'])->name('store');
            Route::put('/{frequency}', [RepaymentFrequenciesController::class, 'update'])->name('update');
            Route::delete('/{frequency}', [RepaymentFrequenciesController::class, 'destroy'])->name('destroy');
        });

        // Interest cycles Routes
        Route::prefix('loans/loans_settings/interest_cycles')->name('loans.interest_cycles.')->group(function () {
            Route::get('/', [InterestCycleController::class, 'index'])->name('index');
            Route::post('/', [InterestCycleController::class, 'store'])->name('store');
            Route::put('/{interestCycle}', [InterestCycleController::class, 'update'])->name('update');
            Route::delete('/{interestCycle}', [InterestCycleController::class, 'destroy'])->name('destroy');
        });

        // Interest methods Routes
        Route::prefix('loans/loans_settings/interest_methods')->name('loans.interest_methods.')->group(function () {
            Route::get('/', [InterestMethodsController::class, 'index'])->name('index');
            Route::post('/', [InterestMethodsController::class, 'store'])->name('store');
            Route::put('/{interestMethod}', [InterestMethodsController::class, 'update'])->name('update');
            Route::delete('/{interestMethod}', [InterestMethodsController::class, 'destroy'])->name('destroy');
        });

        // Loan products Routes
        Route::prefix('loans/loan_products')->name('loans.loan_products.')->group(function () {
            Route::get('/', [\App\Http\Controllers\LoanProductsController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\LoanProductsController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\LoanProductsController::class, 'store'])->name('store');
            Route::get('/{loanProduct}', [\App\Http\Controllers\LoanProductsController::class, 'show'])->name('show');
            Route::get('/{loanProduct}/edit', [\App\Http\Controllers\LoanProductsController::class, 'edit'])->name('edit');
            Route::put('/{loanProduct}', [\App\Http\Controllers\LoanProductsController::class, 'update'])->name('update');
            Route::delete('/{loanProduct}', [\App\Http\Controllers\LoanProductsController::class, 'destroy'])->name('destroy');
        });

        // Bank Accounts Routes
        Route::prefix('admin/accounting/bank_accounts')->name('accounting.bank_accounts.')->group(function () {
            Route::get('/', [BankAccountsController::class, 'index'])->name('index');
            Route::post('/', [BankAccountsController::class, 'store'])->name('store');
            Route::put('/{bankAccount}', [BankAccountsController::class, 'update'])->name('update');
            Route::delete('/{bankAccount}', [BankAccountsController::class, 'destroy'])->name('destroy');
        });

        Route::get('/accounting/bank-accounts/{bank_account}', [BankAccountsController::class, 'show'])
            ->whereNumber('bank_account')
            ->name('accounting.bank-accounts.show');

        // Loan fees Routes
        Route::prefix('loans/loans_settings/loan_fees')->name('loans.loan_fees.')->group(function () {
            Route::get('/', [LoanFeesController::class, 'index'])->name('index');
            Route::post('/', [LoanFeesController::class, 'store'])->name('store');
            Route::put('/{loanFee}', [LoanFeesController::class, 'update'])->name('update');
            Route::delete('/{loanFee}', [LoanFeesController::class, 'destroy'])->name('destroy');
        });

        // Loan write-off & recovery
        Route::prefix('loans/writeoffs')->group(function () {
            Route::get('/', [WriteOffManagementController::class, 'index'])->name('writeoffs.index');
            // Route::get('/{loan}/create', [LoanWriteOffController::class, 'create'])->name('writeoffs.create');
            // Route::post('/{loan}/store', [LoanWriteOffController::class, 'store'])->name('writeoffs.store');
        });

        // Completed Loans
        Route::get('loans/completed', [\App\Http\Controllers\Loans\CompletedLoansController::class, 'index'])->name('loans.completed.index');

        // Rejected Loans
        Route::get('loans/rejected', [\App\Http\Controllers\Loans\RejectedLoansController::class, 'index'])->name('loans.rejected.index');

        // Disbursement methods Routes
        Route::prefix('loans/loans_settings/disbursement_methods')->name('loans.disbursement_methods.')->group(function () {
            Route::get('/', [DisbursementMethodController::class, 'index'])->name('index');
            Route::post('/', [DisbursementMethodController::class, 'store'])->name('store');
            Route::put('/{method}', [DisbursementMethodController::class, 'update'])->name('update');
            Route::delete('/{method}', [DisbursementMethodController::class, 'destroy'])->name('destroy');
        });


        // Loan Penalties Routes
        Route::prefix('loans/loans_settings/loan_penalties')->name('loans.loan_penalties.')->group(function () {
            Route::get('/', [LoanPenaltiesController::class, 'index'])->name('index');
            Route::post('/', [LoanPenaltiesController::class, 'store'])->name('store');
            Route::put('/{loanPenalty}', [LoanPenaltiesController::class, 'update'])->name('update');
            Route::delete('/{loanPenalty}', [LoanPenaltiesController::class, 'destroy'])->name('destroy');
        });






        // Collateral Types (the system adminstrator specify which collateral the system accept)
        Route::prefix('loans/loans_settings/collateral_types')->name('loans.collateral_types.')->group(function () {
            Route::get('/', [CollateralTypesController::class, 'index'])->name('index');
            Route::post('/', [CollateralTypesController::class, 'store'])->name('store');
            Route::put('/{collateralType}', [CollateralTypesController::class, 'update'])->name('update');
            Route::delete('/{collateralType}', [CollateralTypesController::class, 'destroy'])->name('destroy');
        });

        // Loan Groups
        Route::prefix('loans/loan_groups')->name('loans.loan_groups.')->group(function () {
            Route::get('/', [LoanGroupController::class, 'index'])->name('index');
            Route::post('/', [LoanGroupController::class, 'store'])->name('store');
            Route::put('/{group}', [LoanGroupController::class, 'update'])->name('update');
            Route::delete('/{group}', [LoanGroupController::class, 'destroy'])->name('destroy');

            Route::post('/{group}/members', [LoanGroupController::class, 'addMember'])->name('members.store');
            Route::post('/members/{member}/leave', [LoanGroupController::class, 'removeMember'])->name('members.leave');
        });

        // Loan management center
        Route::get('loans', [\App\Http\Controllers\LoanManagementController::class, 'index'])->name('loans.management');

        // Loan approvals (multi-level sequential approval)
        Route::prefix('loans/approvals')->name('loans.approvals.')->group(function () {
            Route::get('/', [\App\Http\Controllers\LoanApprovalsController::class, 'index'])->name('index');
            Route::get('/{loan:loan_code}', [\App\Http\Controllers\LoanApprovalsController::class, 'show'])->name('show');
            Route::post('/{loan:loan_code}/approve', [\App\Http\Controllers\LoanApprovalsController::class, 'approve'])->name('approve');
            Route::post('/{loan:loan_code}/reject', [\App\Http\Controllers\LoanApprovalsController::class, 'reject'])->name('reject');
        });

        // Loan disbursement management
        Route::prefix('loans/disbursement')->name('loans.disbursement.')->group(function () {
            Route::get('/', [\App\Http\Controllers\LoansDisbursementController::class, 'index'])->name('index');
            Route::get('/{loan:loan_code}', [\App\Http\Controllers\LoansDisbursementController::class, 'show'])->name('show');
            Route::post('/{loan:loan_code}/disburse', [\App\Http\Controllers\LoansDisbursementController::class, 'disburse'])->name('disburse');
        });

        // Loan restructuring
        Route::prefix('loan-restructures')->group(function () {
            Route::get('/', [\App\Http\Controllers\LoanRestructureController::class, 'index'])
                ->name('loan.restructures.index');

            Route::get('/managed', [\App\Http\Controllers\LoanRestructureController::class, 'managed'])
                ->name('loan.restructures.managed');

            Route::get('/{loan:loan_code}/create', [\App\Http\Controllers\LoanRestructureController::class, 'create'])
                ->name('loan.restructures.create');

            Route::post('/{loan:loan_code}/store', [\App\Http\Controllers\LoanRestructureController::class, 'store'])
                ->name('loan.restructures.store');

            Route::post('/{restructure}/approve', [\App\Http\Controllers\LoanRestructureController::class, 'approve'])
                ->name('loan.restructures.approve');

            Route::post('/{restructure}/reject', [\App\Http\Controllers\LoanRestructureController::class, 'reject'])
                ->name('loan.restructures.reject');

            Route::post('/{restructure}/execute', [\App\Http\Controllers\LoanRestructureController::class, 'execute'])
                ->name('loan.restructures.execute');

            Route::get('/{loan:loan_code}/history', [\App\Http\Controllers\LoanRestructureController::class, 'history'])
                ->name('loan.restructures.history');
        });

        Route::prefix('loan-repayments')->group(function () {
            Route::get('/loans/repayments', [LoanRepaymentController::class, 'index'])->name('loan.repayments.index');
            Route::get('/loans/repayments/create/{loan:loan_code}', [LoanRepaymentController::class, 'create'])->name('loan.repayments.create');
            Route::post('/loans/repayments/store', [LoanRepaymentController::class, 'store'])->name('loan.repayments.store');
            Route::get('/loans/repayments/history/{loan:loan_code}', [LoanRepaymentController::class, 'show'])->name('loan.repayments.show');
            Route::get('/loans/repayments/receipt/{payment}', [LoanRepaymentController::class, 'receipt'])->name('loan.repayments.receipt');
            Route::post('/loans/repayments/reverse/{payment}', [LoanRepaymentController::class, 'reverse'])->name('loan.repayments.reverse');
        });

        // Loan write-off & recovery
        Route::prefix('loans/{loan:loan_code}')->group(function () {
            Route::get('writeoff/create', [\App\Http\Controllers\Loans\LoanWriteOffController::class, 'create'])
                ->name('loans.writeoff.create');
            Route::post('writeoff', [\App\Http\Controllers\Loans\LoanWriteOffController::class, 'store'])
                ->name('loans.writeoff.store');

            Route::get('recovery/create', [\App\Http\Controllers\Loans\LoanRecoveryController::class, 'create'])
                ->name('loans.recovery.create');
            Route::post('recovery', [\App\Http\Controllers\Loans\LoanRecoveryController::class, 'store'])
                ->name('loans.recovery.store');
        });

        Route::prefix('loans/writeoffs')->group(function () {
            Route::get('/', [\App\Http\Controllers\Loans\WriteOffManagementController::class, 'index'])
                ->name('writeoffs.index');
            Route::get('/{writeoff}', [\App\Http\Controllers\Loans\WriteOffManagementController::class, 'show'])
                ->name('writeoffs.show');
            Route::get('/{writeoff}/recoveries', [\App\Http\Controllers\Loans\WriteOffManagementController::class, 'recoveries'])
                ->name('writeoffs.recoveries');
        });

        // Loans for creating loan, viewing and all about loan management
        Route::prefix('loans/loans')->name('loans.loans.')->group(function () {
            Route::get('/', [LoansController::class, 'index'])->name('index');
            Route::get('/create', [LoansController::class, 'create'])->name('create');
            Route::post('/', [LoansController::class, 'store'])->name('store');
            Route::get('/{loan:loan_code}', [LoansController::class, 'show'])->name('show');
            Route::get('/{loan:loan_code}/edit', [LoansController::class, 'edit'])->name('edit');
            Route::put('/{loan:loan_code}', [LoansController::class, 'update'])->name('update');
            Route::delete('/{loan:loan_code}', [LoansController::class, 'destroy'])->name('destroy');

            Route::post('/{loan:loan_code}/recoveries', [\App\Http\Controllers\LoanRecoveryPaymentsController::class, 'store'])->name('recoveries.store');
        });


        // Customer Collaterals registry 
        Route::prefix('loans/loans_settings/customer_collaterals')->name('loans.customer_collaterals.')->group(function () {
            Route::get('/', [CustomerCollateralsController::class, 'index'])->name('index');
            Route::post('/', [CustomerCollateralsController::class, 'store'])->name('store');
            Route::put('/{customerCollateral}', [CustomerCollateralsController::class, 'update'])->name('update');
            Route::delete('/{customerCollateral}', [CustomerCollateralsController::class, 'destroy'])->name('destroy');
            
            // Collateral Documents routes
            Route::get('/{customerCollateral}/documents', [CustomerCollateralsController::class, 'getDocuments'])->name('documents');
            Route::post('/{customerCollateral}/documents', [CustomerCollateralsController::class, 'storeDocument'])->name('documents.store');
            Route::get('/documents/{document}/download', [CustomerCollateralsController::class, 'downloadDocument'])->name('documents.download');
            Route::post('/documents/{document}/verify', [CustomerCollateralsController::class, 'verifyDocument'])->name('documents.verify');
            Route::delete('/documents/{document}', [CustomerCollateralsController::class, 'deleteDocument'])->name('documents.delete');
        });



        
        Route::get('/dashboard/analytics/payments-daily', [DashboardController::class, 'paymentsDaily'])->name('dashboard.analytics.payments');
        Route::get('/dashboard/analytics/orders-daily', [DashboardController::class, 'ordersDaily'])->name('dashboard.analytics.orders');
        Route::get('/dashboard/analytics/net-payments-refunds', [DashboardController::class, 'netPaymentsRefunds'])->name('dashboard.analytics.net');
        Route::get('/dashboard/analytics/aov-daily', [DashboardController::class, 'aovDaily'])->name('dashboard.analytics.aov');
        Route::get('/dashboard/alerts', [DashboardController::class, 'alerts'])->name('dashboard.alerts');
        // Dashboard exports
        Route::get('/dashboard/export/alerts/{format}', [DashboardController::class, 'exportAlerts'])->name('dashboard.export.alerts');
        Route::get('/dashboard/export/analytics/{type}/{format}', [DashboardController::class, 'exportAnalytics'])->name('dashboard.export.analytics');
        Route::get('/dashboard/export/quick-report', [DashboardController::class, 'exportQuickReport'])->name('dashboard.export.quick');

        // Printer Settings (per subshop)
        Route::get('/settings/printers', [PrinterSettingsController::class, 'index'])
            ->middleware('can:view_configure_printer')
            ->name('printers.settings.index');
        Route::post('/settings/printers', [PrinterSettingsController::class, 'store'])->name('printers.settings.store');
        Route::put('/settings/printers/{printer}', [PrinterSettingsController::class, 'update'])->name('printers.settings.update');
        Route::delete('/settings/printers/{printer}', [PrinterSettingsController::class, 'destroy'])->name('printers.settings.destroy');
        Route::post('/api/printers/test', [PrinterSettingsController::class, 'test'])->name('api.printers.test');
        Route::get('/api/printers/auto-detect', [PrinterSettingsController::class, 'autodetect'])->name('api.printers.autodetect');
        Route::post('/api/printers/{printer}/test-print', [PrinterSettingsController::class, 'testPrint'])->name('api.printers.test-print');
        Route::post('/api/printers/test-print-default', [PrinterSettingsController::class, 'testPrintDefault'])->name('api.printers.test-print-default');

        // Invoice ESC/POS printing
        Route::post('/api/invoices/{order}/print', [InvoicesController::class, 'apiPrint'])->name('api.invoices.print');

        // Sales Return ESC/POS printing
        Route::post('/api/returns/{return}/print', [SalesReturnsController::class, 'apiPrint'])->name('api.returns.print');

        // Purchase Orders ESC/POS printing
        Route::post('/api/purchase-orders/{order}/print', [PurchaseOrdersController::class, 'apiPrint'])->name('api.purchase_orders.print');

        // Purchase Returns ESC/POS printing
        Route::post('/api/purchase-returns/{return}/print', [PurchaseReturnsController::class, 'apiPrint'])->name('api.purchase_returns.print');

        // Print Jobs status
        Route::post('/api/print-jobs/status', [PrintJobsController::class, 'status'])->name('api.print_jobs.status');
        Route::post('/api/print-jobs/retry', [PrintJobsController::class, 'retry'])->name('api.print_jobs.retry');
        Route::get('/settings/printers/jobs', [PrintJobsController::class, 'index'])
            ->middleware('can:view_print_jobs')
            ->name('printers.jobs.index');
        // ROUTE ONY FOR SUPER ADMINS
        Route::middleware(['auth', 'role:Super Admin'])->group(function () {
            // Super Admin Shops management routes
            // Data management routes
            Route::get('/data', [DataController::class, 'index'])->name('data.index');
            Route::post('/data/backup', [DataController::class, 'backup'])->name('data.backup');
            Route::get('/data/backup/download/{filename}', [DataController::class, 'downloadBackup'])->name('data.backup.download');
            Route::delete('/data/backup/{filename}', [DataController::class, 'deleteBackup'])->name('data.backup.delete');

            // (moved UI Settings routes to owner|Super Admin group below)

            
            Route::get('/shopsmanagement', [ShopsManagementController::class, 'show'])->name('shopsmanagement.show');
            Route::get('/shops/configure/{id}', [ShopsManagementController::class, 'configure'])->name('configure.shop');
            // Owners management routes
            Route::get('/owners', [ShopsManagementController::class, 'owners'])->name('owners.index');
            Route::post('/owners', [ShopsManagementController::class, 'storeOwner'])->name('owners.store');
            Route::put('/owners/{owner}', [ShopsManagementController::class, 'updateOwner'])->name('owners.update');
            Route::delete('/owners/{owner}', [ShopsManagementController::class, 'destroyOwner'])->name('owners.destroy');
            Route::post('/owners/{owner}/reset-password', [ShopsManagementController::class, 'resetPassword'])->name('owners.reset-password');

            // SMS Management (usage per shop/subshop)
            Route::get('/sms-management', [SmsManagementController::class, 'index'])->name('sms.management.index');

            //security controller routes
            Route::get('/security', [SecurityController::class, 'index'])->name('admin.security');
            Route::post('/security/block-ip', [SecurityController::class, 'blockIP'])->name('admin.security.block-ip');
            Route::post('/security/unblock-ip', [SecurityController::class, 'unblockIP'])->name('admin.security.unblock-ip');
            Route::post('/security/clear-daily-logs', [SecurityController::class, 'clearOldDailyLogs'])->name('admin.security.clear-daily-logs');
            Route::post('/security/clear-auth-logs', [SecurityController::class, 'clearAuthLogs'])->name('admin.security.clear-auth-logs');
            Route::post('/security/clear-caches', [SecurityController::class, 'clearCaches'])->name('admin.security.clear-caches');

            // sessions management routes
            Route::get('/security/sessions', [SecurityController::class, 'sessions'])->name('admin.security.sessions');
            Route::post('/security/sessions/destroy', [SecurityController::class, 'destroySession'])->name('admin.security.sessions.destroy');
            Route::post('/security/sessions/destroy-others', [SecurityController::class, 'destroyOtherSessions'])->name('admin.security.sessions.destroy-others');
            Route::post('/security/sessions/timeout', [SecurityController::class, 'updateSessionTimeout'])->name('admin.security.sessions.timeout');

            // timezone management routes
            Route::get('/security/timezone', [SecurityController::class, 'timezoneInfo'])->name('admin.security.timezone.info');
            Route::post('/security/timezone', [SecurityController::class, 'updateTimezone'])->name('admin.security.timezone.update');

           
        });

        // Payments management route (for owners and super admins)
        Route::middleware(['auth', 'role:owner|Super Admin'])->group(function () {
            Route::get('/payments', [ShopsManagementController::class, 'payments'])->name('payments');
            
            // Payment resource routes
            Route::put('/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
            Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
        });

        //ROUTES FOR OWNERS OR SUPER ADMINS
        Route::middleware(['auth', 'role:owner|Super Admin'])->group(function () {
            // owners management routes
            // Shop management routes
            Route::get('/shop', [ShopController::class, 'show'])
                             ->middleware(['auth', 'can:view_shop'])

                ->name('shop.show');
            Route::get('/shop/edit', [ShopController::class, 'edit'])->name('shop.edit');
            Route::put('/shop', [ShopController::class, 'update'])->name('shop.update');
            
            // UI Settings routes (per-shop), accessible to owners and Super Admins
            Route::get('settings/ui-settings', [UISettingsController::class, 'index'])->name('settings.ui');
            Route::post('settings/ui-settings', [UISettingsController::class, 'save'])->name('settings.ui.save');
            
            // Users management (owners only)
            Route::get('/admin/users', [UsersManagementController::class, 'index'])->name('users.index');
            Route::get('/admin/users/{user}', [UsersManagementController::class, 'show'])->name('users.show');
            Route::post('/admin/users', [UsersManagementController::class, 'store'])->name('users.store');
            Route::post('/admin/users/{user}/assign-subshops', [UsersManagementController::class, 'assignSubshops'])->name('users.assign-subshops');
            Route::post('/admin/users/{user}/reset-password', [UsersManagementController::class, 'resetPassword'])->name('users.reset-password');
            

        });


        // Plan Management routes
        Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
        Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');
        // Payment Method routes
        Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::put('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
        Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
        Route::put('/shops/{shopId}/settings', [ShopsManagementController::class, 'updateSettings'])->name('shop.update.settings');
        // Plan Management routes
        Route::post('/shops/{shopId}/upgrade-plan', [ShopsManagementController::class, 'upgradePlan'])->name('shops.upgrade-plan');
        Route::post('/shops/{shopId}/record-payment', [ShopsManagementController::class, 'recordPayment'])->name('shops.record-payment');
        // Subscription management routes
        Route::post('/subscriptions/{subscription}/cancel', [\App\Http\Controllers\SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::post('/subscriptions/{subscription}/renew', [\App\Http\Controllers\SubscriptionController::class, 'renew'])->name('subscriptions.renew');
        // Route::get('/shop/edit', [ShopController::class, 'edit'])->name('shop.edit');
        // Route::put('/shop', [ShopController::class, 'update'])->name('shop.update');
        

        // SubShop management routes
        Route::post('/subshop', [SubShopController::class, 'store'])->name('subshop.store');
        Route::post('/subshops/create-modal', [SubShopController::class, 'createModal'])->name('subshops.create-modal');
        Route::put('/subshop/{subshop}', [SubShopController::class, 'update'])->name('subshop.update');
        Route::delete('/subshop/{subshop}', [SubShopController::class, 'destroy'])->name('subshop.destroy');
        
        // Add other protected routes here

        Route::get('/admin', function () {
            return view('admin.dashboard');
        });

        Route::get('/products', function () {
            return view('products');
        });
        // Reports
        Route::get('/admin/reports/inventory', [InventoryReportController::class, 'index'])
            ->middleware('can:view_inventory_report')
            ->name('reports.inventory');
        Route::get('/admin/reports/inventory/full-pdf', [InventoryReportController::class, 'fullPdf'])
            ->name('reports.inventory.fullpdf');
        Route::get('/admin/reports/inventory/ledger', [\App\Http\Controllers\InventoryLedgerController::class, 'index'])
            ->middleware('can:view_inventory_ledger')
            ->name('reports.inventory.ledger');
        Route::get('/admin/reports/sales', [\App\Http\Controllers\SalesReportController::class, 'index'])
            ->middleware('can:view_sales_report')
            ->name('reports.sales');
            
        // Sales Report Export Routes
        Route::get('/admin/reports/sales/export/{format}', [\App\Http\Controllers\SalesReportController::class, 'export'])
            ->name('reports.sales.export');
        // Purchases Report Route
        Route::get('/admin/reports/purchases', [PurchasesReportController::class, 'index'])
            ->middleware('can:view_purchases_report')
            ->name('reports.purchases.index');
        Route::get('/admin/reports/purchases/export/{format}', [PurchasesReportController::class, 'export'])->name('reports.purchases.export');
        // Purchases Analytics API
        Route::get('/admin/reports/purchases/analytics/spend', [PurchasesReportController::class, 'analyticsSpendOverTime'])
            ->name('reports.purchases.analytics.spend');
        Route::get('/admin/reports/purchases/analytics/orders-vs-apv', [PurchasesReportController::class, 'analyticsOrdersVsApv'])
            ->name('reports.purchases.analytics.orders');
        Route::get('/admin/reports/purchases/analytics/ap-aging', [PurchasesReportController::class, 'analyticsApAging'])
            ->name('reports.purchases.analytics.apaging');
        Route::get('/admin/reports/purchases/analytics/supplier-pareto', [PurchasesReportController::class, 'analyticsSupplierPareto'])
            ->name('reports.purchases.analytics.pareto');
        Route::get('/admin/reports/purchases/analytics/returns-rate', [PurchasesReportController::class, 'analyticsReturnsRate'])->name('reports.purchases.analytics.returns_rate');

    // Profit & Loss Report
    Route::get('/admin/reports/profit-and-loss', [ProfitAndLossReportController::class, 'index'])
        ->middleware('can:view_profit_and_loss_report')
        ->name('reports.pl.index');
    Route::get('/admin/reports/profit-and-loss/export/{format}', [ProfitAndLossReportController::class, 'export'])->name('reports.pl.export');
    // P&L Analytics API
    Route::get('/admin/reports/profit-and-loss/analytics/sales-vs-cogs', [ProfitAndLossReportController::class, 'analyticsSalesVsCogs'])->name('reports.pl.analytics.sales_cogs');
    Route::get('/admin/reports/profit-and-loss/analytics/margin', [ProfitAndLossReportController::class, 'analyticsMargin'])->name('reports.pl.analytics.margin');
    Route::get('/admin/reports/profit-and-loss/analytics/waterfall', [ProfitAndLossReportController::class, 'analyticsWaterfall'])->name('reports.pl.analytics.waterfall');
        ///////////////////////////
        ///    inventory        //
        /////////////////////////
        // Redirect legacy per-module choosers to the unified chooser
        Route::get('/admin/inventory/categories/subshops', function (Request $request, SubshopSelectionController $controller) {
            $request->merge(['intended' => route('categories.index')]);
            return $controller->index($request);
        })->name('categories.subshops');
        Route::get('/admin/inventory/categories', [CategoriesController::class, 'index'])
        ->middleware('can:view_categories')
         ->name('categories.index');
        Route::post('/admin/inventory/categories', [CategoriesController::class, 'store'])->name('categories.store');
        Route::put('/admin/inventory/categories/{category}', [CategoriesController::class, 'update'])->name('categories.update');
        Route::delete('/admin/inventory/categories/{category}', [CategoriesController::class, 'destroy'])->name('categories.destroy');

        Route::get('/admin/inventory/suppliers/subshops', function (Request $request, SubshopSelectionController $controller) {
            $request->merge(['intended' => route('suppliers.index')]);
            return $controller->index($request);
        })->name('suppliers.subshops');
        Route::get('/admin/inventory/suppliers', [SuppliersController::class, 'index'])
            ->middleware('can:view_suppliers')
            ->name('suppliers.index');
        Route::get('/admin/inventory/suppliers/export/{format}', [SuppliersController::class, 'export'])->name('suppliers.export');
        Route::get('/admin/inventory/suppliers/import/sample', [SuppliersController::class, 'downloadSample'])->name('suppliers.import.sample');
        Route::post('/admin/inventory/suppliers/import', [SuppliersController::class, 'import'])->name('suppliers.import');
        Route::post('/admin/inventory/suppliers', [SuppliersController::class, 'store'])->name('suppliers.store');
        Route::put('/admin/inventory/suppliers/{supplier}', [SuppliersController::class, 'update'])->name('suppliers.update');
        Route::delete('/admin/inventory/suppliers/{supplier}', [SuppliersController::class, 'destroy'])->name('suppliers.destroy');
        Route::get('/admin/inventory/suppliers/{supplier}', [SuppliersController::class, 'show'])->name('suppliers.show');

        // Suppliers APIs for modal data
        Route::get('/api/suppliers/{supplier}/purchases', [SuppliersController::class, 'apiPurchases'])->name('api.suppliers.purchases');
        Route::get('/api/suppliers/{supplier}/stats', [SuppliersController::class, 'apiStats'])->name('api.suppliers.stats');

        Route::get('/admin/inventory/items/subshops', function (Request $request, SubshopSelectionController $controller) {
            $request->merge(['intended' => route('items.index')]);
            return $controller->index($request);
        })->name('items.subshops');
        
        Route::get('/admin/inventory/items', [ItemsController::class, 'index'])
            ->middleware(['auth', 'can:view_items'])
            ->name('items.index');
        Route::get('/admin/inventory/items/export/{format}', [ItemsController::class, 'export'])->name('items.export');
        Route::post('/admin/inventory/items', [ItemsController::class, 'store'])->name('items.store');
        Route::put('/admin/inventory/items/{item}', [ItemsController::class, 'update'])->name('items.update');
        Route::delete('/admin/inventory/items/{item}', [ItemsController::class, 'destroy'])->name('items.destroy');
        Route::post('/admin/inventory/items/{item}/write-off', [ItemsController::class, 'writeOff'])->name('items.write-off');
        Route::get('/admin/inventory/items/generate-batch', [ItemsController::class, 'generateBatchNumber'])->name('items.generate-batch');
        
        // Import routes
        Route::get('/admin/inventory/items/import/sample', [ItemsController::class, 'downloadSample'])->name('items.import.sample');
        Route::post('/admin/inventory/items/import', [ItemsController::class, 'import'])->name('items.import');
        

        

        //Write offs       
        Route::get('/admin/inventory/subshops', [WriteOffsController::class, 'subshops'])->name('writeoffs.subshops');
        Route::get('/admin/inventory/writeoffs', [WriteOffsController::class, 'index'])
            ->middleware('can:view_writeoffs')
            ->name('writeoffs.index');
        Route::post('/admin/inventory/writeoffs', [WriteOffsController::class, 'store'])->name('writeoffs.store');
        Route::put('/admin/inventory/writeoffs/{writeoff}', [WriteOffsController::class, 'update'])->name('writeoffs.update');
        Route::post('/admin/inventory/writeoffs/{writeoff}/status', [WriteOffsController::class, 'updateStatus'])->name('writeoffs.updateStatus');
        Route::delete('/admin/inventory/writeoffs/{writeoff}', [WriteOffsController::class, 'destroy'])->name('writeoffs.destroy');
        Route::post('/admin/inventory/writeoffs/expired-batch', [WriteOffsController::class, 'writeOffExpiredBatch'])->name('writeoffs.expired-batch');
        Route::get('/admin/inventory/writeoffs/export/{format}', [WriteOffsController::class, 'export'])->name('writeoffs.export');
        
        // Test route for debugging
        Route::get('/test-writeoff-route/{writeoff}', function(\App\Models\WriteOff $writeoff) {
            return response()->json([
                'success' => true,
                'message' => 'Route is working!',
                'writeoff_id' => $writeoff->id,
                'status' => $writeoff->status
            ]);
        })->name('writeoffs.test');

        // Customer management routes
        Route::get('/admin/sales/customers/subshops', [CustomersController  ::class, 'subshops'])->name('customers.subshops');
        Route::get('/admin/sales/customers', [CustomersController::class, 'index'])
            ->middleware('can:view_customers')
            ->name('customers.index');
        Route::get('/admin/sales/customers/export/{format}', [CustomersController::class, 'export'])->name('customers.export');
        Route::get('/admin/sales/customers/import/sample', [CustomersController::class, 'downloadSample'])->name('customers.import.sample');
        Route::post('/admin/sales/customers/import', [CustomersController::class, 'import'])->name('customers.import');
        Route::get('/admin/sales/customers/create', [CustomersController::class, 'create'])
            ->middleware('can:add_customers')
            ->name('customers.create');
        Route::post('/admin/sales/customers', [CustomersController::class, 'store'])->name('customers.store');
        Route::get('/admin/sales/customers/{customer}/edit', [CustomersController::class, 'edit'])
            ->middleware('can:edit_customers')
            ->name('customers.edit');
        Route::put('/admin/sales/customers/{customer}', [CustomersController::class, 'update'])->name('customers.update');
        Route::delete('/admin/sales/customers/{customer}', [CustomersController::class, 'destroy'])->name('customers.destroy');
        Route::get('/admin/sales/customers/{customer}', [CustomersController::class, 'show'])->name('customers.show');
        // Customers APIs for modal data
        Route::get('/api/customers/{customer}/sales', [CustomersController::class, 'apiSales'])->name('api.customers.sales');
        Route::get('/api/customers/{customer}/stats', [CustomersController::class, 'apiStats'])->name('api.customers.stats');

        // Expenses management routes
        Route::get('/admin/finance/expenses/subshops', [ExpensesController::class, 'subshops'])->name('expenses.subshops');
        Route::get('/admin/finance/expenses', [ExpensesController::class, 'index'])
            ->middleware('can:view_expenses')
            ->name('expenses.index');
        Route::post('/admin/finance/expenses', [ExpensesController::class, 'store'])->name('expenses.store');
        Route::post('/admin/finance/expenses/{expense}/status', [ExpensesController::class, 'updateStatus'])->name('expenses.updateStatus');
        Route::delete('/admin/finance/expenses/{expense}', [ExpensesController::class, 'destroy'])->name('expenses.destroy');
        Route::get('/admin/finance/expenses/export/{format}', [ExpensesController::class, 'export'])->name('expenses.export');

        // Banks management routes
        Route::get('/admin/finance/banks/subshops', [BanksController::class, 'subshops'])->name('banks.subshops');
        Route::get('/admin/finance/banks', [BanksController::class, 'index'])
            ->middleware('can:view_banks')
            ->name('banks.index');
        Route::get('/admin/finance/banks/export/{format}', [BanksController::class, 'export'])->name('banks.export');
        Route::post('/admin/finance/banks', [BanksController::class, 'store'])->name('banks.store');
        Route::put('/admin/finance/banks/{bank}', [BanksController::class, 'update'])->name('banks.update');
        Route::delete('/admin/finance/banks/{bank}', [BanksController::class, 'destroy'])->name('banks.destroy');

        // POS routes
        Route::get('/admin/sales/pos/subshops', [PosController::class, 'subshops'])->name('pos.subshops');
        Route::get('/admin/sales/pos', [PosController::class, 'index'])
            ->middleware('can:view_pos')
            ->name('pos.index');
        Route::post('/admin/sales/pos', [PosController::class, 'store'])->name('pos.store');
        // POS APIs for searchable selects
        Route::get('/api/pos/customers', [PosController::class, 'apiCustomers'])->name('api.pos.customers');
        Route::get('/api/pos/items', [PosController::class, 'apiItems'])->name('api.pos.items');

        // Loans APIs for searchable selects
        Route::get('/api/loans/customers', [LoansController::class, 'apiCustomers'])->name('api.loans.customers');
        Route::get('/api/loans/loan-groups', [LoansController::class, 'apiLoanGroups'])->name('api.loans.loan-groups');
        Route::get('/api/loans/collaterals', [LoansController::class, 'apiCollaterals'])->name('api.loans.collaterals');

        // Purchases routes
        Route::get('/admin/purchases/purchase/subshops', [PurchasesController::class, 'subshops'])->name('purchases.subshops');
        Route::get('/admin/purchases/purchase', [PurchasesController::class, 'index'])
            ->middleware('can:view_new_purchases')
            ->name('purchases.index');
        Route::post('/admin/purchases/purchase', [PurchasesController::class, 'store'])->name('purchases.store');
        // Purchases APIs for searchable selects
        Route::get('/api/purchases/suppliers', [PurchasesController::class, 'apiSuppliers'])->name('api.purchases.suppliers');
        Route::get('/api/purchases/items', [PurchasesController::class, 'apiItems'])->name('api.purchases.items');
        Route::get('/api/purchases/next-batch-number', [PurchasesController::class, 'apiNextBatchNumber'])->name('api.purchases.next-batch-number');

        // Inventory Transfers
        Route::get('/admin/inventory/transfers', [\App\Http\Controllers\TransfersController::class, 'index'])
            ->middleware('can:view_items_transfers')
            ->name('transfers.index');
        Route::get('/admin/inventory/transfers/{transfer}', [\App\Http\Controllers\TransfersController::class, 'show'])
             ->middleware('can:view_items_transfers')
            ->name('transfers.show');
        Route::post('/admin/inventory/transfers', [\App\Http\Controllers\TransfersController::class, 'store'])
            ->name('transfers.store');
        Route::post('/admin/inventory/transfers/{transfer}/approve', [\App\Http\Controllers\TransfersController::class, 'approve'])
            ->name('transfers.approve');
        Route::post('/admin/inventory/transfers/{transfer}/dispatch', [\App\Http\Controllers\TransfersController::class, 'dispatch'])
            ->name('transfers.dispatch');
        Route::post('/admin/inventory/transfers/{transfer}/receive', [\App\Http\Controllers\TransfersController::class, 'receive'])
            ->name('transfers.receive');
        Route::get('/admin/inventory/transfers/{transfer}/receive', [\App\Http\Controllers\TransfersController::class, 'receiveForm'])
            ->name('transfers.receive.form');
        Route::get('/admin/inventory/transfers/{transfer}/print', [\App\Http\Controllers\TransfersController::class, 'printNote'])
            ->name('transfers.print');
        Route::post('/admin/inventory/transfers/{transfer}/cancel', [\App\Http\Controllers\TransfersController::class, 'cancel'])
            ->name('transfers.cancel');

        // Invoices (Sales History) routes
        Route::get('/admin/sales/invoices/subshops', [InvoicesController::class, 'subshops'])->name('invoices.subshops');
        Route::get('/admin/sales/invoices', [InvoicesController::class, 'index'])
            ->middleware('can:view_invoice_history')
            ->name('invoices.index');
        Route::get('/admin/sales/invoices/export/{format}', [InvoicesController::class, 'export'])->name('invoices.export');
        Route::get('/api/invoices/{order}', [InvoicesController::class, 'apiOrder'])->name('api.invoices.order');
        Route::post('/api/invoices/{order}/payments', [InvoicesController::class, 'storePayment'])->name('api.invoices.payments.store');
        Route::get('/api/invoices/{order}/payments', [InvoicesController::class, 'payments'])->name('api.invoices.payments.index');
        Route::get('/api/invoices/{order}/returns', [InvoicesController::class, 'returnItems'])->name('api.invoices.returns.items');
        Route::post('/api/invoices/{order}/returns', [InvoicesController::class, 'storeReturn'])->name('api.invoices.returns.store');
        Route::get('/admin/sales/invoices/{order}/print', [InvoicesController::class, 'print'])->name('invoices.print');
        Route::delete('/admin/sales/invoices/{order}', [InvoicesController::class, 'destroy'])->name('invoices.destroy');

        // Purchase Orders (Purchases History) routes
        Route::get('/admin/purchases/purchase-orders/subshops', [PurchaseOrdersController::class, 'subshops'])->name('purchase_orders.subshops');
        Route::get('/admin/purchases/purchase-orders', [PurchaseOrdersController::class, 'index'])
            ->middleware('can:view_purchase_history')
            ->name('purchase_orders.index');
        Route::get('/admin/purchases/purchase-orders/export/{format}', [PurchaseOrdersController::class, 'export'])->name('purchase_orders.export');
        Route::get('/api/purchase-orders/{order}', [PurchaseOrdersController::class, 'apiOrder'])->name('api.purchase_orders.order');
        Route::post('/api/purchase-orders/{order}/payments', [PurchaseOrdersController::class, 'storePayment'])->name('api.purchase_orders.payments.store');
        Route::get('/api/purchase-orders/{order}/payments', [PurchaseOrdersController::class, 'payments'])->name('api.purchase_orders.payments.index');
        Route::get('/api/purchase-orders/{order}/returns', [PurchaseReturnsController::class, 'items'])->name('api.purchase_orders.returns.items');
        Route::post('/api/purchase-orders/{order}/returns', [PurchaseReturnsController::class, 'store'])->name('api.purchase_orders.returns.store');
        Route::get('/admin/purchases/purchase-orders/{order}/print', [PurchaseOrdersController::class, 'print'])->name('purchase_orders.print');
        Route::delete('/admin/purchases/purchase-orders/{order}', [PurchaseOrdersController::class, 'destroy'])->name('purchase_orders.destroy');

        // Sales Returns routes
        Route::get('/admin/sales/returns/subshops', [SalesReturnsController::class, 'subshops'])->name('returns.subshops');
        Route::get('/admin/sales/returns', [SalesReturnsController::class, 'index'])->name('returns.index');
        Route::get('/admin/sales/returns/export/{format}', [SalesReturnsController::class, 'export'])->name('returns.export');
        Route::get('/admin/sales/returns/{return}/print', [SalesReturnsController::class, 'print'])->name('returns.print');
        Route::delete('/admin/sales/returns/{return}', [SalesReturnsController::class, 'destroy'])->name('returns.destroy');

        // Purchase Returns routes
        Route::get('/admin/purchases/returns/subshops', [PurchaseReturnsController::class, 'subshops'])->name('purchase_returns.subshops');
        Route::get('/admin/purchases/returns', [PurchaseReturnsController::class, 'index'])
            ->middleware('can:view_purchase_returns')
            ->name('purchase_returns.index');
        Route::get('/admin/purchases/returns/export/{format}', [PurchaseReturnsController::class, 'export'])->name('purchase_returns.export');
        Route::get('/admin/purchases/returns/{return}/print', [PurchaseReturnsController::class, 'print'])->name('purchase_returns.print');
        Route::delete('/admin/purchases/returns/{return}', [PurchaseReturnsController::class, 'destroy'])->name('purchase_returns.destroy');

        // Sales Transactions routes
        Route::get('/admin/sales/transactions/subshops', [TransactionsController::class, 'subshops'])->name('sales.transactions.subshops');
        Route::get('/admin/sales/transactions', [TransactionsController::class, 'index'])
            ->middleware('can:view_sales_transactions')
            ->name('sales.transactions.index');
        Route::get('/admin/sales/transactions/export/{format}', [TransactionsController::class, 'export'])->name('sales.transactions.export');

        // Purchase Transactions routes
        Route::get('/admin/purchases/transactions', [TransactionsController::class, 'purchaseIndex'])
            ->middleware('can:view_purchase_transactions')
            ->name('purchase.transactions.index');
        Route::get('/admin/purchases/transactions/export/{format}', [TransactionsController::class, 'purchaseExport'])->name('purchase.transactions.export');


        //Roles and permisions routes
        // Assign permissions
        Route::get('/assign', [RolesPermissionsController::class, 'update']);

     
    });
});




require __DIR__.'/auth.php';
