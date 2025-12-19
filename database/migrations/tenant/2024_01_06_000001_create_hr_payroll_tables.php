<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Leave types
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Annual, Sick, Maternity, etc
            $table->string('code')->unique();
            $table->integer('days_per_year');
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_document')->default(false); // Sick leave needs doctor's note
            $table->integer('max_consecutive_days')->nullable();
            $table->boolean('carry_forward')->default(false); // Can unused days carry to next year
            $table->integer('max_carry_forward_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Employee leave balances
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->integer('year');
            $table->integer('total_days'); // Allocated
            $table->integer('used_days')->default(0);
            $table->integer('pending_days')->default(0);
            $table->integer('remaining_days')->storedAs('total_days - used_days - pending_days');
            $table->timestamps();

            $table->unique(['user_id', 'leave_type_id', 'year']);
        });

        // Leave applications
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('leave_type_id')->constrained();

            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days');
            $table->boolean('is_half_day')->default(false);

            $table->text('reason')->nullable();
            $table->string('supporting_document')->nullable(); // File upload
            $table->text('handover_notes')->nullable();
            $table->foreignId('relief_officer_id')->nullable()->constrained('users'); // Who covers

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });

        // Attendance tracking
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('location_id')->nullable()->constrained();
            $table->date('date');

            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->integer('work_hours')->nullable(); // In minutes
            $table->integer('overtime_hours')->default(0); // In minutes
            $table->integer('late_minutes')->default(0);
            $table->integer('early_leave_minutes')->default(0);

            $table->enum('status', ['present', 'absent', 'late', 'half_day', 'on_leave', 'holiday', 'weekend'])->default('present');
            $table->text('notes')->nullable();

            // Geolocation check-in
            $table->decimal('check_in_latitude', 10, 8)->nullable();
            $table->decimal('check_in_longitude', 11, 8)->nullable();
            $table->string('check_in_ip')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        // Shifts
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Morning, Evening, Night
            $table->string('code')->unique();
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_hours');
            $table->integer('grace_period_minutes')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Shift assignments
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained();
            $table->foreignId('location_id')->nullable()->constrained();
            $table->date('date');
            $table->boolean('is_recurring')->default(false);
            $table->json('recurring_days')->nullable(); // [1,2,3,4,5] for Mon-Fri
            $table->date('recurring_until')->nullable();
            $table->timestamps();
        });

        // Payroll periods
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // December 2025
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('frequency', ['weekly', 'bi_weekly', 'monthly'])->default('monthly');
            $table->enum('status', ['draft', 'processing', 'approved', 'paid', 'closed'])->default('draft');
            $table->decimal('total_gross_pay', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_net_pay', 15, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // Payslips
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->string('payslip_number')->unique();
            $table->foreignId('payroll_period_id')->constrained();
            $table->foreignId('user_id')->constrained();

            // Basic pay
            $table->decimal('basic_salary', 12, 2);
            $table->integer('days_worked');
            $table->integer('total_days');

            // Earnings
            $table->decimal('housing_allowance', 12, 2)->default(0);
            $table->decimal('transport_allowance', 12, 2)->default(0);
            $table->decimal('meal_allowance', 12, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('commission', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('other_earnings', 12, 2)->default(0);
            $table->decimal('gross_pay', 12, 2);

            // Statutory deductions (Kenya)
            $table->decimal('paye_tax', 12, 2)->default(0); // Income tax
            $table->decimal('nhif', 12, 2)->default(0); // Health insurance
            $table->decimal('nssf', 12, 2)->default(0); // Social security
            $table->decimal('housing_levy', 12, 2)->default(0); // 1.5% housing levy

            // Other deductions
            $table->decimal('loan_deduction', 12, 2)->default(0);
            $table->decimal('advance_deduction', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2);

            // Net pay
            $table->decimal('net_pay', 12, 2);

            // Payment
            $table->enum('payment_method', ['bank_transfer', 'mpesa', 'cash', 'cheque'])->default('bank_transfer');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();

            $table->timestamps();

            $table->unique(['payroll_period_id', 'user_id']);
        });

        // Salary advances
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->string('advance_number')->unique();
            $table->foreignId('user_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->text('reason')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'paid', 'fully_recovered'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            $table->decimal('recovered_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->storedAs('amount - recovered_amount');
            $table->integer('recovery_installments')->default(1); // How many months to recover
            $table->decimal('installment_amount', 12, 2)->nullable();

            $table->date('advance_date');
            $table->date('recovery_start_date')->nullable();
            $table->timestamps();
        });

        // Loans (longer-term than advances)
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number')->unique();
            $table->foreignId('user_id')->constrained();
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2)->default(0); // Annual percentage
            $table->decimal('total_amount', 15, 2); // Principal + interest

            $table->integer('installments'); // Number of monthly installments
            $table->decimal('installment_amount', 12, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_amount', 15, 2)->storedAs('total_amount - paid_amount');

            $table->enum('status', ['pending', 'approved', 'rejected', 'active', 'fully_paid', 'defaulted'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');

            $table->date('loan_date');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('purpose')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Allowances (one-time or recurring)
        Schema::create('allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // housing, transport, meal, etc
            $table->decimal('amount', 12, 2);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_recurring')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Deductions (one-time or recurring)
        Schema::create('deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // loan, advance, fine, etc
            $table->decimal('amount', 12, 2);
            $table->boolean('is_recurring')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Performance reviews
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('reviewer_id')->constrained('users');
            $table->string('review_period'); // Q1 2025, 2025
            $table->date('review_date');

            $table->integer('overall_rating')->default(3); // 1-5
            $table->integer('attendance_rating')->default(3);
            $table->integer('performance_rating')->default(3);
            $table->integer('teamwork_rating')->default(3);
            $table->integer('communication_rating')->default(3);

            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('goals')->nullable();
            $table->text('reviewer_comments')->nullable();
            $table->text('employee_comments')->nullable();

            $table->enum('status', ['draft', 'completed', 'acknowledged'])->default('draft');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });

        // Disciplinary actions
        Schema::create('disciplinary_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('issued_by')->constrained('users');
            $table->enum('type', ['verbal_warning', 'written_warning', 'suspension', 'termination']);
            $table->date('incident_date');
            $table->text('incident_description');
            $table->text('action_taken');
            $table->date('effective_date');
            $table->date('end_date')->nullable(); // For suspensions
            $table->text('employee_response')->nullable();
            $table->timestamps();
        });

        // Training records
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('provider')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('duration_hours')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('location')->nullable();
            $table->enum('type', ['internal', 'external', 'online'])->default('internal');
            $table->timestamps();
        });

        Schema::create('training_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['enrolled', 'completed', 'cancelled'])->default('enrolled');
            $table->integer('attendance_percentage')->nullable();
            $table->integer('score')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Employee documents
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', ['contract', 'id_card', 'certificate', 'letter', 'other'])->default('other');
            $table->string('file_path');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('training_attendees');
        Schema::dropIfExists('trainings');
        Schema::dropIfExists('disciplinary_actions');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('deductions');
        Schema::dropIfExists('allowances');
        Schema::dropIfExists('employee_loans');
        Schema::dropIfExists('salary_advances');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('leave_applications');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
    }
};
