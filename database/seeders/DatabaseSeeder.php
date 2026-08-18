<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PerfilSeeder::class,
            MunicipioEsSeeder::class,
            UserSeeder::class,
            EgressoSeeder::class,
            ProntuarioSeeder::class,
            VagaEmpregoSeeder::class,
            CursoCapacitacaoSeeder::class,
            RedeApoioSeeder::class,
        ]);
    }
}
