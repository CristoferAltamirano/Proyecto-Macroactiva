<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('password_reset_token', function (Blueprint $t) {
            $t->id('id')->startingValue(1);
            $t->string('email', 120)->index();
            $t->string('token', 100);          // hash del token
            $t->dateTime('expires_at');
            $t->dateTime('created_at')->useCurrent();
            $t->index(['email','token']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('password_reset_token');
    }
};
