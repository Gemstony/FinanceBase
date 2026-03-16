# FinanceBase – Microfinance Core Banking System

[![Laravel](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

> A modern, multi-branch microfinance and core banking platform built on Laravel. FinanceBase supports loan lifecycle management, double-entry accounting, savings/deposits, risk analytics, and role-based multi-branch operations.

---

## 📋 Table of Contents

- [Features](#-features)
- [Architecture Overview](#-architecture-overview)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Multi-Branch (SubShop) Model](#-multi-branch-subshop-model)
- [Key Services & Engines](#-key-services--engines)
- [Loan Lifecycle](#-loan-lifecycle)
- [Accounting Method](#-accounting-method)
- [Security](#-security)
- [Reporting](#-reporting)
- [Contributing](#-contributing)
- [License](#-license)

---

## ✨ Features

### 🏦 Core Banking
- Multi-branch (SubShop) operations with role-based access
- Double-entry accounting with automatic journal posting
- Chart of accounts, bank/cash management
- Receipt/payment vouchers

### 💳 Loan Management
- Flexible loan product configuration (rules, fees, penalties, interest methods, repayment frequencies)
- Full loan lifecycle: application → approval → disbursement → repayment → write-off/restructure
- Automated daily interest accrual
- Automated penalty and fee application
- Payment allocation strategies (principal-first, penalty-first)
- Security deposits (collection/refund)
- Guarantors and collaterals
- Loan calculator/simulator

### 📊 Risk & Analytics
- Portfolio at Risk (PAR 30/60/90)
- Delinquency tracking
- Aging reports
- Officer performance metrics

### 🏧 Savings/Deposits
- Deposit products and accounts
- Interest on savings
- Withdrawals and transaction history

### 📄 Reporting
- Accrual and cash-basis reports
- Export to Excel/PDF
- Date-range and branch filters

### 🔧 Integrations
- SMS notifications
- Receipt printing
- Bank reconciliation imports

---

## 🏗️ Architecture Overview

```
app/
├─ Http/Controllers/          # UI request handling
├─ Services/                  # Business logic engines
│   ├─ Loans/               # Loan lifecycle, schedules, interest, penalties, risk
│   ├─ Accounting/           # Double-entry GL, vouchers, mappers
│   ├─ Deposits/             # Savings/deposits
│   └─ BankReconciliation/   # Statement import/matching
├─ Models/                   # Eloquent entities
├─ Jobs/                     # Scheduled tasks (e.g., daily accrual)
└─ Exports/Reports/          # Excel/PDF exports
resources/views/
├─ loans/                    # Loan UI (list, create, show, calculator)
├─ reports/                  # Report UI and PDF templates
└─ adminlte/                # Base layout
```

### Design Principles
- **Service-Oriented**: Core business logic lives in `app/Services/*`.
- **Read-Only Reporting**: Reports aggregate via services; no mutations.
- **Transactional**: All financial operations wrapped in DB transactions.
- **Multi-Branch by Default**: Every financial row is scoped to `subshop_id`.

---

## 🚀 Installation

1. Clone the repository
   ```bash
   git clone <repo-url>
   cd FinanceBase
   ```

2. Install dependencies
   ```bash
   composer install
   npm install
   ```

3. Environment setup
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Database
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. Link storage
   ```bash
   php artisan storage:link
   ```

6. Compile assets
   ```bash
   npm run build
   ```

7. Serve
   ```bash
   php artisan serve
   ```

---

## ⚙️ Configuration

- `.env`: Database, mail, SMS gateway, and branch settings.
- `config/adminlte.php`: UI theme and menu.
- `config/services.php`: External integrations (SMS, printers).

---

## 🏢 Multi-Branch (SubShop) Model

FinanceBase is built for multi-branch microfinance institutions:

- **Shop**: Top-level organization.
- **SubShop**: Physical branches.
- **User Access**: Users are assigned to one or more subshops; all data is filtered by `subshop_id` (session-enforced).
- **Isolation**: Reports, loans, deposits, and accounting are always scoped to user’s accessible branches.

---

## ⚙️ Key Services & Engines

### Loans
- `LoanScheduleEngine`: Generates repayment schedules (Flat/Reducing/Compound interest).
- `PaymentProcessor`: Repayments, allocation, vouchers.
- `InterestAccrualEngine`: Daily interest accrual and posting.
- `PenaltyEngine`: Auto-apply penalties to overdue installments.
- `FeeEngine`: Apply product-defined fees.
- `LoanWriteOffEngine`: Write-offs and recoveries.
- `LoanDelinquencyEngine` / `PortfolioRiskCalculator`: PAR and aging.
- `LoanRestructureEngine`: Restructure with new schedules.
- `SecurityDepositService`: Collect/refund deposits.

### Accounting
- `JournalPostingEngine`: Double-entry GL.
- `LoanAccountingMapper`: Map loan events to journal lines.
- `VoucherService`: Receipt/payment vouchers.

### Deposits
- `DepositAccountService`: Savings account lifecycle.

### Bank Reconciliation
- `StatementImportService`, `ReconciliationMatcher`, `AutoJournalService`.

---

## 📈 Loan Lifecycle

1. **Application** (`pending_approval`)
2. **Approval** (`approved`) – multi-level workflow supported
3. **Disbursement** (`disbursed`) – funds released; schedule active
4. **Repayment** (`active` / `partially_paid`) – daily interest accrues; penalties apply
5. **Closure** (`paid_off`) – fully settled
6. **Exceptions** (`written_off`, `restructured`)

---

## 📝 Accounting Method

**Accrual-based accounting**

- Revenue (interest, fees, penalties) is accrued daily and posted to the GL.
- Cash receipts are recorded separately; reports can toggle accrual/cash basis.
- All loan events generate double-entry journal lines via `LoanAccountingMapper`.

---

## 🔒 Security

- All requests validate branch (`subshop_id`) access.
- Role-based permissions (Super Admin, Branch Manager, Loan Officer, etc.).
- Input validation and transactional integrity.
- CSRF and authentication middleware by Laravel.

---

## 📊 Reporting

- Controllers in `app/Http/Controllers/*ReportController.php`.
- Services reuse engines (`LoanBalanceCalculator`, `PortfolioRiskCalculator`, etc.).
- Export via `app/Exports/Reports/` (Excel) and Blade PDFs.
- Filters: branch, date range, product, officer, status.

Common reports:
- Portfolio Aging / PAR 30/60/90
- Loan Disbursement Summary
- Repayment Collection Report
- Interest Accrual Report
- Officer Performance

---

## 🤝 Contributing

1. Fork the repo.
2. Create a feature branch.
3. Write tests (PHPUnit).
4. Keep services read-only for reporting.
5. Submit a PR.

---

## 📜 License

This project is open-sourced under the **MIT License**. See [LICENSE](LICENSE) for details.

---

## 🧭 Support

- Internal documentation for services and engines is provided in-code.
- For questions, refer to `app/Services/` class docs and model relationships.
- Use existing patterns when adding new reports or features.

---

> **FinanceBase** is designed for scale, auditability, and multi-branch microfinance operations. Built with Laravel, powered by a service-oriented architecture.
