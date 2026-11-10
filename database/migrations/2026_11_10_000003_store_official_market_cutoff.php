<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markets', fn (Blueprint $table) => $table->timestamp('source_closes_at')->nullable());
        // Preserve the original deadlines of drafts from the former editorial catalogue.
        foreach ([
            'can-2027-md3-cod-sle' => '2026-11-12 15:55:00',
            'can-2027-md3-civ-gmb' => '2026-11-12 18:55:00',
            'sarb-mpc-2026-11-rate-cut' => '2026-11-19 12:55:00',
            'au-27th-executive-council-financing' => '2026-11-12 20:55:00',
            'au-payf-2026-opening' => '2026-11-15 20:55:00',
            'fiba-wcq-2027-feb-cmr-ngr' => '2027-02-24 20:55:00',
        ] as $id => $cutoff) {
            DB::table('markets')->where('pipeline_source', 'africa:'.$id)->update(['source_closes_at' => $cutoff]);
        }
    }

    public function down(): void
    {
        Schema::table('markets', fn (Blueprint $table) => $table->dropColumn('source_closes_at'));
    }
};
