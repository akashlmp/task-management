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
        // 1. Departments Table
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('department_head_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('description')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();
            });
        }

        // 2. Extend Users Table
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('id')->constrained('departments')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'designation')) {
                $table->string('designation')->nullable()->after('name');
            }
            if (! Schema::hasColumn('users', 'joining_date')) {
                $table->date('joining_date')->nullable()->after('status');
            }
            if (! Schema::hasColumn('users', 'profile_image')) {
                $table->string('profile_image')->nullable()->after('joining_date');
            }
        });

        // 3. Extend Projects Table
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'client_company')) {
                $table->string('client_company')->nullable()->after('description');
            }
            if (! Schema::hasColumn('projects', 'project_manager_id')) {
                $table->foreignId('project_manager_id')->nullable()->after('manager_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('projects', 'deadline')) {
                $table->date('deadline')->nullable()->after('start_date');
            }
            if (! Schema::hasColumn('projects', 'budget')) {
                $table->decimal('budget', 15, 2)->default(0)->after('priority');
            }
            if (! Schema::hasColumn('projects', 'progress')) {
                $table->unsignedTinyInteger('progress')->default(0)->after('budget');
            }
        });

        // 4. Extend Tasks Table
        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'name')) {
                $table->string('name')->nullable()->after('project_id');
            }
            if (! Schema::hasColumn('tasks', 'code')) {
                $table->string('code')->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('tasks', 'assigned_employee_id')) {
                $table->foreignId('assigned_employee_id')->nullable()->after('description')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('tasks', 'start_date')) {
                $table->date('start_date')->nullable()->after('status');
            }
            if (! Schema::hasColumn('tasks', 'due_date')) {
                $table->date('due_date')->nullable()->after('start_date');
            }
            if (! Schema::hasColumn('tasks', 'progress')) {
                $table->unsignedTinyInteger('progress')->default(0)->after('due_date');
            }
            if (! Schema::hasColumn('tasks', 'estimated_hours')) {
                $table->decimal('estimated_hours', 8, 2)->default(0)->after('progress');
            }
            if (! Schema::hasColumn('tasks', 'actual_hours')) {
                $table->decimal('actual_hours', 8, 2)->default(0)->after('estimated_hours');
            }
        });

        // 5. Employee Project Allocations Pivot/Table
        if (! Schema::hasTable('employee_project_allocations')) {
            Schema::create('employee_project_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->unsignedTinyInteger('allocation_percentage')->default(0);
                $table->string('role')->default('Member');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'project_id']);
                $table->index(['employee_id', 'allocation_percentage']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_project_allocations');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['assigned_employee_id']);
            $table->dropColumn([
                'name',
                'code',
                'assigned_employee_id',
                'start_date',
                'due_date',
                'progress',
                'estimated_hours',
                'actual_hours',
            ]);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['project_manager_id']);
            $table->dropColumn([
                'client_company',
                'project_manager_id',
                'deadline',
                'budget',
                'progress',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn([
                'department_id',
                'designation',
                'joining_date',
                'profile_image',
            ]);
        });

        Schema::dropIfExists('departments');
    }
};
