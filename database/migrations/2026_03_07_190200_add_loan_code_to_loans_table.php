<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('loan_code', 32)->nullable()->after('id');
            $table->unique('loan_code');
        });

        DB::table('loans')
            ->select(['id'])
            ->whereNull('loan_code')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $code = null;

                    do {
                        $code = 'LN-' . (string) Str::ulid();
                        $exists = DB::table('loans')->where('loan_code', $code)->exists();
                    } while ($exists);

                    DB::table('loans')->where('id', $row->id)->update(['loan_code' => $code]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropUnique(['loan_code']);
            $table->dropColumn('loan_code');
        });
    }
};
