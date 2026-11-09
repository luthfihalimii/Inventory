<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimestampTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_timestamp_columns_are_timezone_aware(): void
    {
        $columns = [
            ['users', 'created_at'],
            ['users', 'updated_at'],
            ['users', 'last_login_at'],
            ['members', 'created_at'],
            ['items', 'created_at'],
            ['assets', 'created_at'],
        ];

        foreach ($columns as [$table, $column]) {
            $this->assertSame(
                'timestamp with time zone',
                DB::table('information_schema.columns')
                    ->where('table_name', $table)
                    ->where('column_name', $column)
                    ->value('data_type'),
                "{$table}.{$column} must be timestamptz",
            );
        }
    }

    public function test_timestamps_are_stored_in_utc(): void
    {
        $user = User::factory()->create();

        $stored = DB::table('users')->where('id', $user->id)->value('created_at');

        $this->assertLessThan(
            5,
            abs(now()->utc()->diffInSeconds($stored, true)),
            'users.created_at is not stored in UTC',
        );
    }
}
