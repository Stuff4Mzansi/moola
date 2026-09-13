<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return $this->response('ok');
    }

    public function ready(): JsonResponse
    {
        try {
            DB::select('SELECT 1');

            if (! Schema::hasTable('migrations')) {
                return $this->response('not_ready', 503);
            }

            $ran = DB::table('migrations')->pluck('migration')->all();
            $expected = collect(glob(database_path('migrations/*.php')) ?: [])
                ->map(static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
                ->all();

            if (array_diff($expected, $ran) !== []) {
                return $this->response('not_ready', 503);
            }
        } catch (QueryException) {
            return $this->response('not_ready', 503);
        }

        return $this->response('ready');
    }

    private function response(string $status, int $code = 200): JsonResponse
    {
        return response()
            ->json(['status' => $status], $code)
            ->header('Cache-Control', 'no-store');
    }
}
