<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run()
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo User',
                'password' => bcrypt('password'),
            ]
        );

        // Créer des tâches de test
        $tasks = [
            [
                'title' => 'Acheter du pain',
                'description' => 'Aller à la boulangerie avant 19h',
                'priority' => 'high',
                'due_date' => now()->addHours(2),
                'completed' => false,
            ],
            [
                'title' => 'Appeler le médecin',
                'description' => 'Prendre rendez-vous pour check-up annuel',
                'priority' => 'medium',
                'due_date' => now()->addDays(1),
                'completed' => false,
            ],
            [
                'title' => 'Finir le rapport',
                'description' => 'Rapport Q1 2025',
                'priority' => 'high',
                'due_date' => now()->addDays(3),
                'completed' => false,
            ],
            [
                'title' => 'Regarder le dernier épisode',
                'description' => 'Série préférée',
                'priority' => 'low',
                'due_date' => now()->addDays(7),
                'completed' => false,
            ],
            [
                'title' => 'Envoyer email client',
                'description' => 'Répondre aux questions du client A',
                'priority' => 'high',
                'due_date' => now()->subHours(2), // Overdue !
                'completed' => false,
            ],
            [
                'title' => 'Faire du sport',
                'description' => '30 min de course',
                'priority' => 'medium',
                'due_date' => today(),
                'completed' => true,
            ],
        ];

        foreach ($tasks as $taskData) {
            Task::create([
                'user_id' => $user->id,
                ...$taskData,
            ]);
        }

        $this->command->info("Created {$user->tasks()->count()} tasks for {$user->email}");
    }
}
