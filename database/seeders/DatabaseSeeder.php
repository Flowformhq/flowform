<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'FlowForm Admin',
            'email' => 'admin@flowform.dev',
        ]);

        $this->call(FieldTypeSeeder::class);

        $token = $user->createToken('dev-token')->plainTextToken;
        $this->command->info("API Token: {$token}");
    }
}
