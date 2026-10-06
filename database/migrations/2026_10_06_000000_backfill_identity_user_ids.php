<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Give every pre-existing Laravel user a valid Identity domain ID. */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('identity_user_id')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->whereNull('identity_user_id')
                        ->update(['identity_user_id' => (string) Str::uuid()]);
                }
            });

        // Once old rows are linked, future code can safely rely on this ID.
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('identity_user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // UUIDs are retained because removing identity links would break users.
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('identity_user_id')->nullable()->change();
        });
    }
};
