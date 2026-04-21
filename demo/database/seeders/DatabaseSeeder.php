<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the demo database.
     *
     * Credentials:
     *   test@example.com  / password   ← main account used by the mobile client
     *   alice@demo.com    / password
     *   bob@demo.com      / password
     */
    public function run(): void
    {
        $this->createUser(
            name:  'Test User',
            email: 'test@example.com',
            tasks: [
                ['title' => 'Buy groceries',         'priority' => 'high',   'due_date' => now()->addHours(3),  'completed' => false],
                ['title' => 'Call the doctor',        'priority' => 'medium', 'due_date' => now()->addDay(),     'completed' => false],
                ['title' => 'Finish the Q1 report',   'priority' => 'high',   'due_date' => now()->addDays(3),   'completed' => false],
                ['title' => 'Reply to client email',  'priority' => 'high',   'due_date' => now()->subHours(2),  'completed' => false], // overdue
                ['title' => 'Go for a run',           'priority' => 'medium', 'due_date' => today(),             'completed' => true],
                ['title' => 'Read the docs',          'priority' => 'low',    'due_date' => now()->addWeek(),    'completed' => false],
            ]
        );

        $this->createUser(
            name:  'Alice Demo',
            email: 'alice@demo.com',
            tasks: [
                ['title' => 'Install OfflineSync plugin', 'priority' => 'high',   'due_date' => now()->subDays(2), 'completed' => true],
                ['title' => 'Test offline sync',          'priority' => 'high',   'due_date' => today(),           'completed' => false],
                ['title' => 'Configure conflict strategy','priority' => 'medium', 'due_date' => now()->addDay(),   'completed' => false],
                ['title' => 'Deploy to production',       'priority' => 'high',   'due_date' => now()->addWeek(),  'completed' => false],
            ]
        );

        $this->createUser(
            name:  'Bob Demo',
            email: 'bob@demo.com',
            tasks: [
                ['title' => 'Build demo app',        'priority' => 'high',   'due_date' => now()->subDay(), 'completed' => true],
                ['title' => 'Test conflict flow',    'priority' => 'medium', 'due_date' => today(),         'completed' => false],
                ['title' => 'Benchmark batch size',  'priority' => 'medium', 'due_date' => now()->addDays(2), 'completed' => false],
            ]
        );

        $this->command->newLine();
        $this->command->info('✅ Database seeded.');
        $this->command->table(
            ['Email', 'Password', 'Tasks'],
            [
                ['test@example.com', 'password', 6],
                ['alice@demo.com',   'password', 4],
                ['bob@demo.com',     'password', 3],
            ]
        );
    }

    /**
     * Create a user (idempotent) with a set of tasks.
     */
    private function createUser(string $name, string $email, array $tasks): void
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('password')]
        );

        // Only seed tasks if the user was just created
        if ($user->wasRecentlyCreated) {
            foreach ($tasks as $data) {
                Task::create(array_merge($data, [
                    'user_id'     => $user->id,
                    'description' => null,
                ]));
            }
        }
    }
}
