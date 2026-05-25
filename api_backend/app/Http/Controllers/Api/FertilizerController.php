<?php

/**
 * Fertilizer configuration endpoints.
 *
 * Admins can create/update/deactivate fertilizer references with N/P/K
 * percentage composition. Managers and technicians have read access.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\PaginatesResources;
use App\Http\Controllers\Concerns\RespondsWithResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fertilizer\IndexFertilizerRequest;
use App\Http\Requests\Fertilizer\StoreFertilizerRequest;
use App\Http\Requests\Fertilizer\UpdateFertilizerRequest;
use App\Http\Resources\FertilizerResource;
use App\Models\Fertilizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FertilizerController extends Controller
{
    use PaginatesResources;
    use RespondsWithResource;

    public function index(IndexFertilizerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $query = Fertilizer::query()->orderBy('name');

        if (array_key_exists('is_active', $data)) {
            $query->where('is_active', (bool) $data['is_active']);
        }

        if (! empty($data['unit'])) {
            $query->where('unit', $data['unit']);
        }

        if (! empty($data['search'])) {
            $search = strtolower($data['search']);
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
        }

        return $this->paginatedResponse(
            $query->paginate($data['per_page'] ?? 25),
            FertilizerResource::class,
        );
    }

    public function store(StoreFertilizerRequest $request): JsonResponse
    {
        $fertilizer = Fertilizer::create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return $this->resourceResponse(FertilizerResource::class, $fertilizer, 201);
    }

    public function show(Fertilizer $fertilizer): JsonResponse
    {
        return $this->resourceResponse(FertilizerResource::class, $fertilizer);
    }

    public function update(UpdateFertilizerRequest $request, Fertilizer $fertilizer): JsonResponse
    {
        $fertilizer->fill([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ])->save();

        return $this->resourceResponse(FertilizerResource::class, $fertilizer->refresh());
    }

    public function destroy(Request $request, Fertilizer $fertilizer): JsonResponse
    {
        // Customer feedback: "Supprimer" must actually delete, not deactivate.
        try {
            $fertilizer->delete();
            return response()->json(['data' => ['id' => $fertilizer->id, 'deleted' => true]]);
        } catch (\Illuminate\Database\QueryException $e) {
            $fertilizer->forceFill([
                'is_active' => false,
                'updated_by' => $request->user()?->id,
            ])->save();
            return response()->json([
                'error' => [
                    'code' => 'in_use',
                    'message' => "L'engrais est utilisé par des opérations existantes — il a été désactivé.",
                ],
            ], 409);
        }
    }
}
