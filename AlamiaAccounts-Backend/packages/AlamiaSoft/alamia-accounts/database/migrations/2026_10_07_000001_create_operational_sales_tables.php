<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('operational_sales', function (Blueprint $table) {
            $table->id();
            $table->string('company_code', 50)->index();
            $table->string('client_reference_id', 100)->nullable()->index();
            $table->string('idempotency_key', 100)->nullable()->index();
            $table->string('created_by_user_id', 50)->nullable()->index();
            $table->string('created_by_user_name', 150)->nullable();
            $table->string('customer_name', 150)->index();
            $table->string('customer_phone', 50)->nullable();
            $table->string('customer_email', 100)->nullable();
            $table->string('customer_cnic_or_ntn', 50)->nullable();
            $table->string('customer_subledger_code', 50)->nullable();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('status', 50)->default('posted')->index(); // 'draft', 'pending_approval', 'posted', 'reversed'
            $table->string('workflow_mode', 50)->default('instant_post'); // 'instant_post', 'pending_approval', 'draft'
            $table->decimal('gross_amount', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('net_amount', 15, 2)->default(0.00);
            $table->decimal('paid_amount', 15, 2)->default(0.00);
            $table->decimal('balance_due', 15, 2)->default(0.00);
            $table->string('sales_voucher_ref', 50)->nullable()->index();
            $table->string('receipt_voucher_ref', 50)->nullable()->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_code', 'created_at']);
        });

        Schema::create('operational_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_sale_id')->constrained('operational_sales')->onDelete('cascade');
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->decimal('total_price', 15, 2)->default(0.00);
            $table->string('revenue_account_code', 50)->default('4100');
            $table->decimal('tax_rate_percent', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->json('metadata')->nullable(); // PNR, ticket number, route, passenger, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_sale_items');
        Schema::dropIfExists('operational_sales');
    }
};
