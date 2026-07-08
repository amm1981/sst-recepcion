<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'user')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('user', 191)->nullable()->after('id')->unique();
        });

        DB::table('users')
            ->select(['id', 'email'])
            ->orderBy('id')
            ->get()
            ->each(function ($user): void {
                $base = Str::of((string) $user->email)
                    ->before('@')
                    ->lower()
                    ->replaceMatches('/[^a-z0-9._-]/', '')
                    ->trim('.-_')
                    ->value() ?: 'usuario';

                $candidate = $base;
                $suffix = 1;
                while (DB::table('users')->where('user', $candidate)->exists()) {
                    $suffix++;
                    $candidate = "{$base}{$suffix}";
                }

                DB::table('users')->where('id', $user->id)->update(['user' => $candidate]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'user')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['user']);
            $table->dropColumn('user');
        });
    }
};
