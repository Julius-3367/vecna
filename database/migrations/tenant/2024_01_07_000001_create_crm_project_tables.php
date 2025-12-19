<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CRM Leads
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_number')->unique();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('website')->nullable();

            // Address
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('county')->nullable();
            $table->string('country', 2)->default('KE');

            // Lead details
            $table->enum('source', ['website', 'referral', 'social_media', 'phone', 'email', 'walk_in', 'advertisement', 'other'])->default('other');
            $table->enum('status', ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'])->default('new');
            $table->enum('rating', ['hot', 'warm', 'cold'])->default('warm');

            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->integer('probability')->default(50); // Percentage
            $table->date('expected_close_date')->nullable();

            // Assignment
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();

            // Meta
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('lead_number');
            $table->index(['status', 'assigned_to']);
        });

        // Lead activities (call logs, emails, meetings)
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->enum('type', ['call', 'email', 'meeting', 'note', 'task', 'whatsapp'])->default('note');
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('activity_date');
            $table->integer('duration_minutes')->nullable();
            $table->enum('outcome', ['successful', 'unsuccessful', 'follow_up_required'])->nullable();
            $table->timestamp('follow_up_date')->nullable();
            $table->timestamps();
        });

        // Sales pipeline stages
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->string('color', 7)->default('#3B82F6'); // Hex color
            $table->integer('probability')->default(50); // Likelihood of closing
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Opportunities (qualified leads in pipeline)
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('opportunity_number')->unique();
            $table->string('name');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 12, 2);
            $table->foreignId('pipeline_stage_id')->constrained();
            $table->integer('probability')->default(50);
            $table->date('expected_close_date')->nullable();
            $table->date('actual_close_date')->nullable();

            $table->enum('status', ['open', 'won', 'lost', 'abandoned'])->default('open');
            $table->text('lost_reason')->nullable();

            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Customer interactions/notes
        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('note');
            $table->boolean('is_important')->default(false);
            $table->timestamps();
        });

        // Customer communication log
        Schema::create('customer_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->enum('channel', ['email', 'phone', 'sms', 'whatsapp', 'in_person'])->default('phone');
            $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('communication_date');
            $table->integer('duration_minutes')->nullable();
            $table->timestamps();
        });

        // Tasks
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();

            // Assignment
            $table->foreignId('assigned_to')->constrained('users');
            $table->foreignId('created_by')->constrained('users');

            // Related entities
            $table->string('related_type')->nullable(); // Customer, Lead, Project, etc
            $table->unsignedBigInteger('related_id')->nullable();

            // Dates
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Priority & status
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');

            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['related_type', 'related_id']);
        });

        // Projects
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_number')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Dates
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('actual_end_date')->nullable();

            // Budget
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->decimal('budget', 15, 2)->nullable();

            // Status
            $table->enum('status', ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'])->default('planning');
            $table->integer('progress_percentage')->default(0);

            // Assignment
            $table->foreignId('project_manager_id')->nullable()->constrained('users');

            $table->timestamps();
        });

        // Project tasks/milestones
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('project_tasks')->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('estimated_hours')->nullable();
            $table->integer('actual_hours')->default(0);

            $table->enum('status', ['pending', 'in_progress', 'completed', 'blocked'])->default('pending');
            $table->integer('progress_percentage')->default(0);

            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });

        // Time tracking
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('hours'); // Total hours worked
            $table->text('description')->nullable();

            $table->boolean('is_billable')->default(true);
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->boolean('is_invoiced')->default(false);

            $table->timestamps();
        });

        // Job cards (for repair/service businesses)
        Schema::create('job_cards', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('location_id')->constrained();

            // Item details
            $table->string('item_description'); // What's being repaired
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();

            // Problem & diagnosis
            $table->text('reported_problem');
            $table->text('diagnosis')->nullable();
            $table->text('work_performed')->nullable();

            // Status
            $table->enum('status', ['received', 'diagnosed', 'awaiting_parts', 'in_progress', 'completed', 'ready_for_pickup', 'delivered', 'cancelled'])->default('received');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // Dates
            $table->timestamp('received_at');
            $table->timestamp('promised_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Assignment
            $table->foreignId('technician_id')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');

            // Costs
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->decimal('labor_cost', 12, 2)->default(0);
            $table->decimal('parts_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);

            // Invoice
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('payment_status', ['pending', 'partial', 'paid'])->default('pending');

            // Meta
            $table->text('customer_notes')->nullable();
            $table->text('technician_notes')->nullable();
            $table->json('accessories')->nullable(); // Items received with the job

            $table->timestamps();

            $table->index('job_number');
            $table->index(['status', 'technician_id']);
        });

        // Job card items (parts used)
        Schema::create('job_card_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->timestamps();
        });

        // Email campaigns
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'cancelled'])->default('draft');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->integer('recipients_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);

            $table->json('recipient_filters')->nullable(); // Criteria for selecting recipients
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
        });

        // Campaign recipients tracking
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('email');

            $table->enum('status', ['pending', 'sent', 'failed', 'bounced'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->integer('open_count')->default(0);
            $table->integer('click_count')->default(0);

            $table->timestamps();
        });

        // Customer loyalty program
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['earn', 'redeem', 'expire', 'adjustment'])->default('earn');
            $table->integer('points');
            $table->integer('balance_after');

            $table->string('reference_type')->nullable(); // Sale, etc
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('description')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Customer groups/segments
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('criteria')->nullable(); // Auto-assignment rules
            $table->integer('discount_percentage')->default(0);
            $table->timestamps();
        });

        Schema::create('customer_group_customer', function (Blueprint $table) {
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_group_id', 'customer_id'], 'customer_group_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_group_customer');
        Schema::dropIfExists('customer_groups');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('email_campaigns');
        Schema::dropIfExists('job_card_items');
        Schema::dropIfExists('job_cards');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('customer_communications');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
    }
};
