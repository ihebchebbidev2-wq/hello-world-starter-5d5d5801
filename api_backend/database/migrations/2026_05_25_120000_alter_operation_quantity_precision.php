<?php

/**
 * Bump quantity precision on phytosanitary & fertilization operations to
 * support 3 decimal places (Tunisia convention). Previously decimal(10,2)
 * silently truncated values like 0.805 to 0.80, which the field crew
 * reported as a "save bug" on the phyto entry form.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Use raw ALTER COLUMN because Laravel's Schema::table()->decimal()
        // change() requires doctrine/dbal and we want zero extra deps.
        if (Schema::hasTable('phytosanitary_operations')) {
            DB::statement('ALTER TABLE phytosanitary_operations ALTER COLUMN quantity_applied TYPE numeric(12,3)');
        }
        if (Schema::hasTable('fertilization_operations')) {
            DB::statement('ALTER TABLE fertilization_operations ALTER COLUMN quantity_applied TYPE numeric(12,3)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('phytosanitary_operations')) {
            DB::statement('ALTER TABLE phytosanitary_operations ALTER COLUMN quantity_applied TYPE numeric(10,2)');
        }
        if (Schema::hasTable('fertilization_operations')) {
            DB::statement('ALTER TABLE fertilization_operations ALTER COLUMN quantity_applied TYPE numeric(10,2)');
        }
    }
};
