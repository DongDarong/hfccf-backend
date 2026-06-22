<?php

namespace Database\Seeders;

use App\Support\CambodiaLocationImporter;
use Illuminate\Database\Seeder;

/**
 * Seeds the shared Cambodia administrative master data used across the system.
 */
class CambodiaLocationSeeder extends Seeder
{
    public function run(CambodiaLocationImporter $importer): void
    {
        $summary = $importer->import();

        if ($this->command) {
            $this->command->info(sprintf(
                'Imported Cambodia locations: %d provinces, %d districts, %d communes, %d villages.',
                $summary['imported']['provinces'],
                $summary['imported']['districts'],
                $summary['imported']['communes'],
                $summary['imported']['villages'],
            ));

            foreach ($summary['missing_parents'] as $level => $items) {
                if (! empty($items)) {
                    $this->command->warn(sprintf(
                        'Skipped %d %s rows because a parent record was missing.',
                        count($items),
                        $level,
                    ));
                }
            }
        }
    }
}
