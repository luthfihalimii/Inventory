<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\Generation;
use App\Models\Member;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Member> */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        $division = Division::firstOrCreate(['nama' => 'Webmaster'], ['status_aktif' => true]);
        $position = Position::firstOrCreate(['nama' => 'Tidak Ada']);
        $generation = Generation::firstOrCreate(['nama' => 'Gen 21']);
        $nrp = fake()->unique()->numerify('33########');

        return [
            'kode' => 'ENT-' . fake()->unique()->bothify('#####'),
            'nrp' => $nrp,
            'nama' => fake()->name(),
            'divisi_id' => $division->id,
            'jabatan_id' => $position->id,
            'generasi_id' => $generation->id,
            'status' => 'ACTIVE',
        ];
    }
}
