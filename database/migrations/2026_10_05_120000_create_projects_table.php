<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('planned');
            $table->string('priority')->default('medium');
            $table->timestamps();
            $table->softDeletes();

            $table->index('manager_id');
            $table->index('status');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
