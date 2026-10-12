<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Department;
use App\Models\Society;
use App\Models\Street;
use App\Models\TariffType;
use App\Models\Unit;
use App\Models\UnitCategory;
use App\Support\Tenancy\Tenant;
use Illuminate\Database\Seeder;

class SocietySampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $society = Society::where('code', 'DEMO01')->first();
        if (! $society) {
            return;
        }

        Tenant::run($society->id, function () use ($society) {
            $categories = collect(['1 Marla', '2.5 Marla', '3 Marla', '5 Marla', '7 Marla', '10 Marla', '1 Kanal', '1-Bed Flat', '2-Bed Flat', 'Shop'])
                ->mapWithKeys(fn ($name) => [$name => UnitCategory::firstOrCreate(['society_id' => $society->id, 'name' => $name])]);

            $tariffs = collect(['Domestic', 'Commercial'])
                ->mapWithKeys(fn ($name) => [$name => TariffType::firstOrCreate(['society_id' => $society->id, 'name' => $name])]);

            foreach (['A', 'B', 'C', 'D', 'E'] as $blockName) {
                $block = Block::firstOrCreate(['society_id' => $society->id, 'name' => "{$blockName} Block"]);

                foreach (["Street 1", "Street 2", "Street 3"] as $streetName) {
                    $street = Street::firstOrCreate(['block_id' => $block->id, 'society_id' => $society->id, 'name' => $streetName]);

                    for ($i = 1; $i <= 5; $i++) {
                        $unitNumber = "{$blockName}-{$street->name}-{$i}";
                        $category = $categories->get(collect($categories)->keys()->random());
                        $tariff = $blockName === 'E' ? $tariffs['Commercial'] : $tariffs['Domestic'];

                        Unit::firstOrCreate(
                            ['society_id' => $society->id, 'block_id' => $block->id, 'street_id' => $street->id, 'unit_number' => $unitNumber],
                            [
                                'full_address' => "House {$unitNumber}, {$block->name}, {$society->name}",
                                'unit_category_id' => $category->id,
                                'tariff_type_id' => $tariff->id,
                                'residence_status' => 'owner',
                                'owner_name' => "Sample Owner {$unitNumber}",
                                'occupant_name' => "Sample Owner {$unitNumber}",
                                'reference_number' => "{$society->code}-".$this->uniqueSuffix($blockName.$street->id.$i),
                                'is_active' => true,
                            ]
                        );
                    }
                }
            }

            foreach (['Maintenance', 'Electrical', 'Sanitation', 'Security'] as $deptName) {
                Department::firstOrCreate(['society_id' => $society->id, 'name' => $deptName]);
            }
        });
    }

    private function uniqueSuffix(string $seed): string
    {
        return str_pad((string) (crc32($seed) % 999999), 6, '0', STR_PAD_LEFT);
    }
}
