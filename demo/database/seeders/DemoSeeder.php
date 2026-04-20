<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Task;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer 2 utilisateurs de démo
        $user1 = User::create([
            'name' => 'Alice Demo',
            'email' => 'alice@demo.com',
            'password' => Hash::make('password'),
        ]);

        $user2 = User::create([
            'name' => 'Bob Demo',
            'email' => 'bob@demo.com',
            'password' => Hash::make('password'),
        ]);

        // Créer des tâches pour Alice
        $aliceTasks = [
            [
                'title' => 'Installer le plugin OfflineSync',
                'description' => 'Suivre le guide d\'installation',
                'completed' => true,
                'priority' => 'high',
                'due_date' => now()->subDays(2),
            ],
            [
                'title' => 'Tester la synchronisation offline',
                'description' => 'Désactiver le réseau et créer des tâches',
                'completed' => false,
                'priority' => 'high',
                'due_date' => now(),
            ],
            [
                'title' => 'Configurer les stratégies de conflits',
                'description' => 'Tester les 4 stratégies disponibles',
                'completed' => false,
                'priority' => 'medium',
                'due_date' => now()->addDays(1),
            ],
            [
                'title' => 'Lire la documentation complète',
                'description' => 'Parcourir tous les guides dans /docs',
                'completed' => false,
                'priority' => 'low',
                'due_date' => now()->addDays(3),
            ],
            [
                'title' => 'Déployer en production',
                'description' => 'Suivre le checklist SECURITY.md',
                'completed' => false,
                'priority' => 'high',
                'due_date' => now()->addWeek(),
            ],
        ];

        foreach ($aliceTasks as $taskData) {
            Task::create(array_merge($taskData, ['user_id' => $user1->id]));
        }

        // Créer des tâches pour Bob
        $bobTasks = [
            [
                'title' => 'Créer une app de démo',
                'description' => 'Todo list avec synchronisation',
                'completed' => true,
                'priority' => 'high',
                'due_date' => now()->subDay(),
            ],
            [
                'title' => 'Tester les conflits',
                'description' => 'Modifier la même tâche offline et online',
                'completed' => false,
                'priority' => 'medium',
                'due_date' => now(),
            ],
            [
                'title' => 'Optimiser les performances',
                'description' => 'Vérifier le batch size et les index',
                'completed' => false,
                'priority' => 'medium',
                'due_date' => now()->addDays(2),
            ],
        ];

        foreach ($bobTasks as $taskData) {
            Task::create(array_merge($taskData, ['user_id' => $user2->id]));
        }

        $this->command->info('✅ Créé 2 utilisateurs (alice@demo.com / bob@demo.com)');
        $this->command->info('✅ Créé ' . count($aliceTasks) . ' tâches pour Alice');
        $this->command->info('✅ Créé ' . count($bobTasks) . ' tâches pour Bob');
        $this->command->info('🔑 Mot de passe pour tous : password');
    }
}
