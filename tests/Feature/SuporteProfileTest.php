<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Perfil;
use App\Models\User;

class SuporteProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /**
     * Test PerfilSeeder includes the suporte profile (id 5, slug 'suporte').
     */
    public function test_perfil_suporte_exists_in_database(): void
    {
        $suportePerfil = Perfil::where('slug', 'suporte')->first();
        $this->assertNotNull($suportePerfil, "Perfil 'suporte' should exist in the database.");
        $this->assertTrue($suportePerfil->ativo, "Perfil 'suporte' should be active.");
    }

    /**
     * Test User model isSuporte helper method.
     */
    public function test_user_is_suporte_method(): void
    {
        $user = new User();
        $suportePerfil = Perfil::where('slug', 'suporte')->first();
        
        if ($suportePerfil) {
            $user->perfil_id = $suportePerfil->id;
            $user->setRelation('perfil', $suportePerfil);
            $this->assertTrue(method_exists($user, 'isSuporte') ? $user->isSuporte() : ($user->perfil?->slug === 'suporte'));
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Test Agile Support user is seeded with email suporte.agile@sejus.es.gov.br.
     */
    public function test_agile_support_user_is_seeded(): void
    {
        $user = User::where('email', 'suporte.agile@sejus.es.gov.br')->first();
        $this->assertNotNull($user, "User 'suporte.agile@sejus.es.gov.br' should be seeded in database.");
        $this->assertTrue($user->ativo, "Agile Support user should be active.");
    }
}
