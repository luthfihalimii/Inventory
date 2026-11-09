<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $division = Division::where('code', 'RED')->firstOrFail();
        $roles = Role::pluck('id', 'name');

        $accounts = [
            [
                'username' => 'anggota',
                'name' => 'Anggota Demo',
                'nrp' => '2410000001',
                'member_code' => 'MBR-0001',
                'role' => 'user',
            ],
            [
                'username' => 'korlab',
                'name' => 'Admin Korlab',
                'nrp' => '2410000002',
                'member_code' => 'MBR-0002',
                'role' => 'admin_korlab',
            ],
            [
                'username' => 'wm',
                'name' => 'Wakil Manajer',
                'nrp' => '2410000003',
                'member_code' => 'MBR-0003',
                'role' => 'wm',
            ],
        ];

        foreach ($accounts as $account) {
            $member = Member::updateOrCreate(
                ['nrp' => $account['nrp']],
                [
                    'member_code' => $account['member_code'],
                    'name' => $account['name'],
                    'division_id' => $division->id,
                    'membership_status' => 'active',
                    'borrowing_status' => 'eligible',
                ],
            );

            $user = User::updateOrCreate(
                ['username' => $account['username']],
                [
                    'member_id' => $member->id,
                    'name' => $account['name'],
                    'email' => $account['username'].'@ent.local',
                    'password' => 'password',
                    'status' => 'active',
                    'must_change_password' => false,
                ],
            );

            $user->roles()->sync([$roles[$account['role']]]);
        }
    }
}
