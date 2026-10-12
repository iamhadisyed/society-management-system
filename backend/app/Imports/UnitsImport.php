<?php

namespace App\Imports;

use App\Models\Block;
use App\Models\Society;
use App\Models\Street;
use App\Models\TariffType;
use App\Models\Unit;
use App\Models\UnitCategory;
use App\Services\ReferenceNumberGenerator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk unit import. Expected columns (case-insensitive header row):
 * block, street, unit_number, full_address, unit_category, tariff_type,
 * residence_status, owner_name, occupant_name.
 *
 * Block/street/category/tariff are looked up by name and created if
 * missing (so a society can bootstrap its whole unit list from one
 * spreadsheet without pre-creating lookups first).
 */
class UnitsImport implements ToCollection, WithHeadingRow
{
    public array $errors = [];

    public int $created = 0;

    public int $skipped = 0;

    public function __construct(private Society $society, private ReferenceNumberGenerator $refGenerator) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 for 0-index, +1 for header row

            try {
                $this->importRow($row, $rowNumber);
            } catch (\Throwable $e) {
                $this->errors[] = "Row {$rowNumber}: {$e->getMessage()}";
                $this->skipped++;
            }
        }
    }

    private function importRow(Collection $row, int $rowNumber): void
    {
        $unitNumber = trim((string) ($row['unit_number'] ?? ''));
        $blockName = trim((string) ($row['block'] ?? ''));
        $streetName = trim((string) ($row['street'] ?? ''));

        if ($unitNumber === '' || $blockName === '' || $streetName === '') {
            throw new \InvalidArgumentException('block, street and unit_number are required.');
        }

        $block = Block::withoutGlobalScopes()->firstOrCreate(['society_id' => $this->society->id, 'name' => $blockName]);
        $street = Street::withoutGlobalScopes()->firstOrCreate(['society_id' => $this->society->id, 'block_id' => $block->id, 'name' => $streetName]);
        $category = UnitCategory::withoutGlobalScopes()->firstOrCreate(['society_id' => $this->society->id, 'name' => trim((string) ($row['unit_category'] ?? 'Unspecified'))]);
        $tariff = TariffType::withoutGlobalScopes()->firstOrCreate(['society_id' => $this->society->id, 'name' => trim((string) ($row['tariff_type'] ?? 'Domestic'))]);

        $exists = Unit::withoutGlobalScopes()
            ->where('society_id', $this->society->id)
            ->where('block_id', $block->id)
            ->where('street_id', $street->id)
            ->where('unit_number', $unitNumber)
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException("Unit {$unitNumber} already exists in {$blockName}/{$streetName}.");
        }

        $unit = new Unit([
            'block_id' => $block->id,
            'street_id' => $street->id,
            'unit_number' => $unitNumber,
            'full_address' => $row['full_address'] ?? "{$unitNumber}, {$streetName}, {$blockName}",
            'unit_category_id' => $category->id,
            'tariff_type_id' => $tariff->id,
            'residence_status' => in_array($row['residence_status'] ?? 'owner', ['owner', 'tenant']) ? ($row['residence_status'] ?? 'owner') : 'owner',
            'owner_name' => $row['owner_name'] ?? null,
            'occupant_name' => $row['occupant_name'] ?? null,
            'is_active' => true,
        ]);
        $unit->society_id = $this->society->id;
        $unit->reference_number = $this->refGenerator->next($this->society);
        $unit->save();

        $this->created++;
    }
}
