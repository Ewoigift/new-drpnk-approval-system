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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // The user who submitted the request
            $table->string('title');
            $table->text('description');
            $table->decimal('estimated_cost', 10, 2);
            $table->string('attachment_path')->nullable(); // Path to the uploaded file

            // Workflow status fields
            $table->string('status')->default('pending_procurement'); // e.g., 'pending_procurement', 'pending_accountant', 'approved', 'rejected', 'sent_back_to_requester'
            $table->string('current_approver_role')->nullable(); // Role of the person currently responsible for action

            // Comments and timestamps for each approval stage
            $table->text('procurement_comments')->nullable();
            $table->timestamp('procurement_approved_at')->nullable();

            $table->text('accountant_comments')->nullable();
            $table->timestamp('accountant_approved_at')->nullable();

            $table->text('coordinator_comments')->nullable();
            $table->timestamp('coordinator_approved_at')->nullable();

            $table->text('chief_officer_comments')->nullable();
            $table->timestamp('chief_officer_approved_at')->nullable();

            $table->string('final_outcome')->nullable(); // 'approved', 'rejected', 'sent_back'

            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
