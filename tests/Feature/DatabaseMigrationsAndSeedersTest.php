<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\MunicipioEs;
use App\Models\Perfil;
use App\Models\User;
use App\Models\Egresso;
use App\Models\Prontuario;
use App\Models\VagaEmprego;
use App\Models\CursoCapacitacao;
use App\Models\RedeApoio;
use Database\Seeders\DatabaseSeeder;

class DatabaseMigrationsAndSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_all_expected_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        // 1. Verify 78 Municipalities of ES
        $this->assertEquals(78, MunicipioEs::count());

        // 2. Verify 4 Physical social office municipalities (Vitória, Vila Velha, Serra, Cariacica)
        $this->assertEquals(4, MunicipioEs::where('tem_escritorio_fisico', true)->count());
        $this->assertEquals(74, MunicipioEs::where('tem_escritorio_fisico', false)->count());

        // 3. Verify Profiles (Gestor, Tecnico, Egresso, Familiar, Suporte)
        $this->assertEquals(5, Perfil::count());
        $this->assertNotNull(Perfil::where('slug', 'gestor')->first());
        $this->assertNotNull(Perfil::where('slug', 'tecnico')->first());
        $this->assertNotNull(Perfil::where('slug', 'egresso')->first());
        $this->assertNotNull(Perfil::where('slug', 'suporte')->first());

        // 4. Verify Demo Users
        $this->assertGreaterThanOrEqual(4, User::count());
        $gestor = User::where('email', 'gestor@sejus.es.gov.br')->first();
        $this->assertNotNull($gestor);
        $this->assertTrue($gestor->isGestor());

        // 5. Verify Egressos and Prontuarios
        $this->assertGreaterThanOrEqual(2, Egresso::count());
        $this->assertGreaterThanOrEqual(2, Prontuario::count());

        // 6. Verify Jobs, Courses, Support Network
        $this->assertGreaterThanOrEqual(6, VagaEmprego::count());
        $this->assertGreaterThanOrEqual(5, CursoCapacitacao::count());
        $this->assertGreaterThanOrEqual(10, RedeApoio::count());
    }
}
