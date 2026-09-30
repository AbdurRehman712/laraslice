<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('status')->default('active'); // active, suspended, pending
                $table->string('avatar_url')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        } else {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'status')) {
                    $table->string('status')->default('active')->after('password');
                }
                if (!Schema::hasColumn('users', 'avatar_url')) {
                    $table->string('avatar_url')->nullable()->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
