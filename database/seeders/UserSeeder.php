<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Perfil;
use Illuminate\Support\Facades\Hash;
use App\Services\LgpdSecurityService;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds for demonstration users.
     */
    public function run(): void
    {
        $lgpd = app(LgpdSecurityService::class);
        $gestorPerfil = Perfil::where('slug', 'gestor')->first();
        $tecnicoPerfil = Perfil::where('slug', 'tecnico')->first();
        $egressoPerfil = Perfil::where('slug', 'egresso')->first();
        $suportePerfil = Perfil::where('slug', 'suporte')->first();

        $users = [
            [
                'id' => 1,
                'perfil_id' => $gestorPerfil ? $gestorPerfil->id : 1,
                'name' => 'Carlos Eduardo Silva',
                'email' => 'gestor@sejus.es.gov.br',
                'password' => Hash::make('secret123'),
                'govbr_id' => 'govbr-gestor-001',
                'cpf' => '11122233344',
                'telefone' => '(27) 3636-5700',
                'foto_url' => 'https://ui-avatars.com/api/?name=Carlos+Silva&background=0284c7&color=fff',
                'ativo' => true,
            ],
            [
                'id' => 2,
                'perfil_id' => $tecnicoPerfil ? $tecnicoPerfil->id : 2,
                'name' => 'Dra. Márcia Oliveira',
                'email' => 'marcia.oliveira@sejus.es.gov.br',
                'password' => Hash::make('secret123'),
                'govbr_id' => 'govbr-tecnico-002',
                'cpf' => '55566677788',
                'telefone' => '(27) 99888-1122',
                'foto_url' => 'https://ui-avatars.com/api/?name=Marcia+Oliveira&background=059669&color=fff',
                'ativo' => true,
            ],
            [
                'id' => 3,
                'perfil_id' => $egressoPerfil ? $egressoPerfil->id : 3,
                'name' => 'Lucas Santos',
                'email' => 'lucas.santos@cidadao.es.gov.br',
                'password' => Hash::make('secret123'),
                'govbr_id' => 'govbr-egresso-003',
                'cpf' => '19283045678',
                'telefone' => '(27) 99777-3344',
                'foto_url' => 'https://ui-avatars.com/api/?name=Lucas+Santos&background=475569&color=fff',
                'ativo' => true,
            ],
            [
                'id' => 4,
                'perfil_id' => $egressoPerfil ? $egressoPerfil->id : 3,
                'name' => 'Roberto Fonseca da Silva',
                'email' => 'roberto.fonseca@cidadao.es.gov.br',
                'password' => Hash::make('secret123'),
                'govbr_id' => 'govbr-egresso-004',
                'cpf' => '48291037492',
                'telefone' => '(27) 99666-5566',
                'foto_url' => 'https://ui-avatars.com/api/?name=Roberto+Fonseca&background=475569&color=fff',
                'ativo' => true,
            ],
            [
                'id' => 5,
                'perfil_id' => $suportePerfil ? $suportePerfil->id : 5,
                'name' => 'Suporte Agile SEJUS',
                'email' => 'suporte.agile@sejus.es.gov.br',
                'password' => Hash::make('secret123'),
                'govbr_id' => 'govbr-suporte-005',
                'cpf' => '99988877700',
                'telefone' => '(27) 3636-5700',
                'foto_url' => 'https://ui-avatars.com/api/?name=Suporte+Agile&background=4f46e5&color=fff',
                'ativo' => true,
            ],
        ];

        foreach ($users as $u) {
            $user = User::firstOrNew(['email' => $u['email']]);
            $user->id = $u['id'];
            $user->perfil_id = $u['perfil_id'];
            $user->name = $u['name'];
            $user->password = $u['password'];
            $user->govbr_id = $u['govbr_id'];
            $user->cpf = $u['cpf']; // mutator automatically sets cpf_encrypted & hash_cpf
            $user->telefone = $u['telefone'];
            $user->foto_url = $u['foto_url'];
            $user->ativo = $u['ativo'];
            $user->save();
        }
    }
}
