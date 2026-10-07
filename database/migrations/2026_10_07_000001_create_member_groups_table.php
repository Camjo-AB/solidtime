<?php

declare(strict_types=1);

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
        Schema::create('member_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('member_group_member', function (Blueprint $table): void {
            $table->uuid('member_group_id');
            $table->foreign('member_group_id')
                ->references('id')
                ->on('member_groups')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->uuid('member_id');
            $table->foreign('member_id')
                ->references('id')
                ->on('members')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->primary(['member_group_id', 'member_id']);
            $table->index('member_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_group_member');
        Schema::dropIfExists('member_groups');
    }
};
