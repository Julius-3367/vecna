<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of accounts
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // 1000, 1010, etc
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();

            $table->enum('type', [
                'asset', 'liability', 'equity', 'revenue', 'expense',
            ]);
            $table->enum('subtype', [
                // Assets
                'current_asset', 'fixed_asset', 'inventory', 'bank', 'cash',
                // Liabilities
                'current_liability', 'long_term_liability',
                // Equity
                'capital', 'retained_earnings',
                // Revenue
                'sales_revenue', 'other_income',
                // Expenses
                'cost_of_sales', 'operating_expense', 'tax',
            ])->nullable();

            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->enum('balance_type', ['debit', 'credit']);

            $table->boolean('is_system')->default(false); // Can't be deleted
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('code');
            $table->index('type');
        });

        // Journal entries (double-entry bookkeeping)
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number')->unique();
            $table->date('entry_date');
            $table->string('reference_type')->nullable(); // Sale, Purchase, Payment, etc
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->boolean('is_balanced')->storedAs('total_debit = total_credit');
            $table->enum('status', ['draft', 'posted', 'void'])->default('posted');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('entry_number');
            $table->index(['reference_type', 'reference_id']);
        });

        // Journal entry lines
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->enum('type', ['debit', 'credit']);
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Expenses
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('account_id')->constrained('accounts'); // Expense account

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('KES');
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);

            $table->date('expense_date');
            $table->enum('payment_method', ['cash', 'mpesa', 'bank_transfer', 'card', 'cheque'])->nullable();
            $table->string('payment_reference')->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'partial'])->default('pending');

            $table->string('receipt_number')->nullable();
            $table->string('receipt_file')->nullable(); // Uploaded receipt image
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_frequency')->nullable(); // daily, weekly, monthly

            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users');
            $table->foreignId('location_id')->nullable()->constrained();

            $table->timestamps();
        });

        // Expense categories
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->foreignId('account_id')->nullable()->constrained('accounts');
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // M-Pesa transactions (for reconciliation)
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type'); // C2B, B2C, B2B
            $table->string('trans_id')->unique(); // M-Pesa receipt number
            $table->string('trans_time');
            $table->decimal('trans_amount', 12, 2);
            $table->string('business_short_code');
            $table->string('bill_ref_number')->nullable(); // Account reference
            $table->string('invoice_number')->nullable();
            $table->string('org_account_balance')->nullable();
            $table->string('third_party_trans_id')->nullable();
            $table->string('msisdn')->nullable(); // Phone number
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();

            // Reconciliation
            $table->boolean('is_reconciled')->default(false);
            $table->string('reconciled_type')->nullable(); // Sale, Payment, etc
            $table->unsignedBigInteger('reconciled_id')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users');

            $table->json('raw_data')->nullable(); // Full callback data
            $table->timestamps();

            $table->index('trans_id');
            $table->index('is_reconciled');
        });

        // Bank accounts
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number');
            $table->string('branch')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('currency', 3)->default('KES');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->foreignId('account_id')->constrained('accounts'); // Link to chart of accounts
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Bank transactions
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained();
            $table->date('transaction_date');
            $table->enum('type', ['deposit', 'withdrawal', 'transfer']);
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->decimal('balance_after', 15, 2);

            // Reconciliation
            $table->boolean('is_reconciled')->default(false);
            $table->string('reconciled_type')->nullable();
            $table->unsignedBigInteger('reconciled_id')->nullable();

            $table->timestamps();
        });

        // Tax records (KRA VAT)
        Schema::create('tax_records', function (Blueprint $table) {
            $table->id();
            $table->string('period'); // 2025-12, YYYY-MM
            $table->date('period_start');
            $table->date('period_end');

            // Sales VAT
            $table->decimal('sales_excluding_vat', 15, 2)->default(0);
            $table->decimal('sales_vat', 15, 2)->default(0);
            $table->decimal('sales_including_vat', 15, 2)->default(0);

            // Purchase VAT
            $table->decimal('purchases_excluding_vat', 15, 2)->default(0);
            $table->decimal('purchases_vat', 15, 2)->default(0);
            $table->decimal('purchases_including_vat', 15, 2)->default(0);

            // Net VAT (to pay or reclaim)
            $table->decimal('net_vat', 15, 2)->storedAs('sales_vat - purchases_vat');

            // KRA submission
            $table->enum('status', ['draft', 'submitted', 'paid', 'overdue'])->default('draft');
            $table->string('kra_reference')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();

            $table->timestamps();

            $table->unique('period');
        });

        // Budgets
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('period'); // 2025, 2025-Q1, 2025-12
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_amount', 15, 2);
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->decimal('allocated_amount', 15, 2);
            $table->decimal('spent_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->storedAs('allocated_amount - spent_amount');
            $table->timestamps();
        });

        // Financial reports cache
        Schema::create('financial_reports', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['profit_loss', 'balance_sheet', 'cash_flow', 'trial_balance']);
            $table->date('period_start');
            $table->date('period_end');
            $table->json('data'); // Cached report data
            $table->foreignId('generated_by')->constrained('users');
            $table->timestamps();

            $table->index(['type', 'period_start', 'period_end']);
        });

        // Payment terms templates
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Net 30, Net 60, COD, etc
            $table->integer('days');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Fiscal years
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // FY 2025
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('payment_terms');
        Schema::dropIfExists('financial_reports');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('tax_records');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('mpesa_transactions');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
