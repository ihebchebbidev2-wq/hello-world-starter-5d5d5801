<?php

/**
 * Aggregated reporting endpoints for the Manager & Admin dashboard.
 *
 * All reports accept optional filters: plot_ids[], campaign_id, date_from,
 * date_to. Queries run directly on the DB to avoid N+1 issues at report
 * scale. PostgreSQL-specific functions (EXTRACT) are intentional — the
 * project targets Postgres exclusively.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class ReportController extends Controller
{
    public function irrigation(ReportRequest $request): JsonResponse
    {
        [$plotIds, $campaignId, $from, $to] = $this->commonFilters($request);

        $monthly = DB::table('irrigation_operations as op')
            ->join('plots', 'plots.id', '=', 'op.plot_id')
            ->select([
                'op.plot_id',
                'plots.name as plot_name',
                'plots.surface_area_ha',
                DB::raw('EXTRACT(YEAR  FROM op.operation_date)::int AS year'),
                DB::raw('EXTRACT(MONTH FROM op.operation_date)::int AS month'),
                DB::raw('SUM(op.water_quantity) AS total_quantity'),
            ])
            ->when($plotIds,    fn ($q) => $q->whereIn('op.plot_id', $plotIds))
            ->when($campaignId, fn ($q) => $q->where('op.campaign_id', $campaignId))
            ->when($from,       fn ($q) => $q->where('op.operation_date', '>=', $from))
            ->when($to,         fn ($q) => $q->where('op.operation_date', '<=', $to))
            ->groupBy('op.plot_id', 'plots.name', 'plots.surface_area_ha', 'year', 'month')
            ->orderBy('op.plot_id')->orderBy('year')->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'plot_id'        => $r->plot_id,
                'plot_name'      => $r->plot_name,
                'year'           => $r->year,
                'month'          => $r->month,
                'total_quantity' => (float) $r->total_quantity,
                'per_hectare'    => $r->surface_area_ha > 0
                    ? round((float) $r->total_quantity / (float) $r->surface_area_ha, 4)
                    : null,
            ]);

        $cumulative = DB::table('irrigation_operations as op')
            ->join('plots', 'plots.id', '=', 'op.plot_id')
            ->select([
                'op.plot_id',
                'plots.name as plot_name',
                'plots.surface_area_ha',
                'plots.season_start_date',
                DB::raw('SUM(op.water_quantity) AS total_quantity'),
            ])
            ->whereRaw("(plots.season_start_date IS NULL OR op.operation_date >= plots.season_start_date)")
            ->when($plotIds,    fn ($q) => $q->whereIn('op.plot_id', $plotIds))
            ->when($campaignId, fn ($q) => $q->where('op.campaign_id', $campaignId))
            ->when($from,       fn ($q) => $q->where('op.operation_date', '>=', $from))
            ->when($to,         fn ($q) => $q->where('op.operation_date', '<=', $to))
            ->groupBy('op.plot_id', 'plots.name', 'plots.surface_area_ha', 'plots.season_start_date')
            ->orderBy('plots.name')
            ->get()
            ->map(fn ($r) => [
                'plot_id'        => $r->plot_id,
                'plot_name'      => $r->plot_name,
                'surface_area_ha'=> (float) $r->surface_area_ha,
                'total_quantity' => (float) $r->total_quantity,
                'per_hectare'    => $r->surface_area_ha > 0
                    ? round((float) $r->total_quantity / (float) $r->surface_area_ha, 4)
                    : null,
                'since'          => $r->season_start_date,
            ]);

        return ApiResponse::ok([
            'monthly'    => $monthly,
            'cumulative' => $cumulative,
            'filters'    => $this->filterSummary($plotIds, $campaignId, $from, $to),
        ]);
    }

    public function fertilization(ReportRequest $request): JsonResponse
    {
        [$plotIds, $campaignId, $from, $to] = $this->commonFilters($request);

        $monthly = DB::table('fertilization_operations as op')
            ->join('plots', 'plots.id', '=', 'op.plot_id')
            ->select([
                'op.plot_id',
                'plots.name as plot_name',
                'plots.surface_area_ha',
                DB::raw('EXTRACT(YEAR  FROM op.operation_date)::int AS year'),
                DB::raw('EXTRACT(MONTH FROM op.operation_date)::int AS month'),
                DB::raw('SUM(op.quantity_applied * op.n_at_entry / 100.0) AS total_n'),
                DB::raw('SUM(op.quantity_applied * op.p_at_entry / 100.0) AS total_p'),
                DB::raw('SUM(op.quantity_applied * op.k_at_entry / 100.0) AS total_k'),
            ])
            ->when($plotIds,    fn ($q) => $q->whereIn('op.plot_id', $plotIds))
            ->when($campaignId, fn ($q) => $q->where('op.campaign_id', $campaignId))
            ->when($from,       fn ($q) => $q->where('op.operation_date', '>=', $from))
            ->when($to,         fn ($q) => $q->where('op.operation_date', '<=', $to))
            ->groupBy('op.plot_id', 'plots.name', 'plots.surface_area_ha', 'year', 'month')
            ->orderBy('op.plot_id')->orderBy('year')->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'plot_id'   => $r->plot_id,
                'plot_name' => $r->plot_name,
                'year'      => $r->year,
                'month'     => $r->month,
                'n_total'   => round((float) $r->total_n, 4),
                'p_total'   => round((float) $r->total_p, 4),
                'k_total'   => round((float) $r->total_k, 4),
                'n_per_ha'  => $r->surface_area_ha > 0
                    ? round((float) $r->total_n / (float) $r->surface_area_ha, 4) : null,
                'p_per_ha'  => $r->surface_area_ha > 0
                    ? round((float) $r->total_p / (float) $r->surface_area_ha, 4) : null,
                'k_per_ha'  => $r->surface_area_ha > 0
                    ? round((float) $r->total_k / (float) $r->surface_area_ha, 4) : null,
            ]);

        $cumulative = DB::table('fertilization_operations as op')
            ->join('plots', 'plots.id', '=', 'op.plot_id')
            ->select([
                'op.plot_id',
                'plots.name as plot_name',
                'plots.surface_area_ha',
                'plots.season_start_date',
                DB::raw('SUM(op.quantity_applied * op.n_at_entry / 100.0) AS total_n'),
                DB::raw('SUM(op.quantity_applied * op.p_at_entry / 100.0) AS total_p'),
                DB::raw('SUM(op.quantity_applied * op.k_at_entry / 100.0) AS total_k'),
            ])
            ->whereRaw("(plots.season_start_date IS NULL OR op.operation_date >= plots.season_start_date)")
            ->when($plotIds,    fn ($q) => $q->whereIn('op.plot_id', $plotIds))
            ->when($campaignId, fn ($q) => $q->where('op.campaign_id', $campaignId))
            ->when($from,       fn ($q) => $q->where('op.operation_date', '>=', $from))
            ->when($to,         fn ($q) => $q->where('op.operation_date', '<=', $to))
            ->groupBy('op.plot_id', 'plots.name', 'plots.surface_area_ha', 'plots.season_start_date')
            ->orderBy('plots.name')
            ->get()
            ->map(fn ($r) => [
                'plot_id'        => $r->plot_id,
                'plot_name'      => $r->plot_name,
                'surface_area_ha'=> (float) $r->surface_area_ha,
                'since'          => $r->season_start_date,
                'n_per_ha'       => $r->surface_area_ha > 0
                    ? round((float) $r->total_n / (float) $r->surface_area_ha, 4) : null,
                'p_per_ha'       => $r->surface_area_ha > 0
                    ? round((float) $r->total_p / (float) $r->surface_area_ha, 4) : null,
                'k_per_ha'       => $r->surface_area_ha > 0
                    ? round((float) $r->total_k / (float) $r->surface_area_ha, 4) : null,
            ]);

        return ApiResponse::ok([
            'monthly'    => $monthly,
            'cumulative' => $cumulative,
            'filters'    => $this->filterSummary($plotIds, $campaignId, $from, $to),
        ]);
    }

    public function phytosanitary(ReportRequest $request): JsonResponse
    {
        [$plotIds, $campaignId, $from, $to] = $this->commonFilters($request);
        $data    = $request->validated();
        $keyword = isset($data['q']) ? trim((string) $data['q']) : '';

        $rows = DB::table('phytosanitary_operations as op')
            ->join('plots',     'plots.id',     '=', 'op.plot_id')
            ->join('pesticides','pesticides.id', '=', 'op.pesticide_id')
            ->select([
                'op.id',
                'op.plot_id',
                'plots.name as plot_name',
                'plots.surface_area_ha',
                'op.pesticide_id',
                'pesticides.name as pesticide_name',
                'pesticides.unit as pesticide_unit',
                'pesticides.chemical_composition',
                'op.operation_date',
                'op.quantity_applied',
                'op.target_pest',
                'op.remarks',
                'op.price_at_entry',
            ])
            ->when($plotIds,    fn ($q) => $q->whereIn('op.plot_id', $plotIds))
            ->when($campaignId, fn ($q) => $q->where('op.campaign_id', $campaignId))
            ->when($from,       fn ($q) => $q->where('op.operation_date', '>=', $from))
            ->when($to,         fn ($q) => $q->where('op.operation_date', '<=', $to))
            ->when($keyword !== '', function ($q) use ($keyword) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $keyword) . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('pesticides.name', 'ilike', $like)
                      ->orWhere('pesticides.chemical_composition', 'ilike', $like)
                      ->orWhere('op.target_pest', 'ilike', $like)
                      ->orWhere('op.remarks', 'ilike', $like)
                      ->orWhere('plots.name', 'ilike', $like);
                });
            })
            ->orderBy('plots.name')
            ->orderBy('op.operation_date', 'desc')
            ->get();

        $grouped = $rows->groupBy('plot_id')->map(function ($items, $plotId) {
            $first   = $items->first();
            $surface = (float) ($first->surface_area_ha ?? 0);

            return [
                'plot_id'         => $plotId,
                'plot_name'       => $first->plot_name,
                'surface_area_ha' => $surface,
                'treatments'      => $items->map(function ($r) use ($surface) {
                    $qty = (float) $r->quantity_applied;

                    return [
                        'id'                   => $r->id,
                        'date'                 => $r->operation_date,
                        'pesticide_id'         => $r->pesticide_id,
                        'pesticide_name'       => $r->pesticide_name,
                        'pesticide_unit'       => $r->pesticide_unit,
                        'chemical_composition' => $r->chemical_composition,
                        'quantity_applied'     => $qty,
                        // Per-hectare dose = product quantity / plot surface.
                        // Rounded to 3 decimals to match Tunisia standard.
                        'pesticide_per_ha'     => $surface > 0 ? round($qty / $surface, 3) : null,
                        'target_pest'          => $r->target_pest,
                        'remarks'              => $r->remarks,
                        'price_at_entry'       => (float) $r->price_at_entry,
                    ];
                })->values(),
            ];
        })->values();

        return ApiResponse::ok([
            'plots'   => $grouped,
            'filters' => $this->filterSummary($plotIds, $campaignId, $from, $to)
                + ['q' => $keyword !== '' ? $keyword : null],
        ]);
    }

    public function harvest(ReportRequest $request): JsonResponse
    {
        [$plotIds, $campaignId, $from, $to] = $this->commonFilters($request);

        $rows = DB::table('harvest_operations as op')
            ->join('plots', 'plots.id', '=', 'op.plot_id')
            ->select([
                'op.id',
                'op.plot_id',
                'plots.name as plot_name',
                'op.operation_date',
                'op.num_workers',
                'op.days_worked',
                'op.quantity_harvested',
                'op.daily_rate_at_entry',
            ])
            ->when($plotIds,    fn ($q) => $q->whereIn('op.plot_id', $plotIds))
            ->when($campaignId, fn ($q) => $q->where('op.campaign_id', $campaignId))
            ->when($from,       fn ($q) => $q->where('op.operation_date', '>=', $from))
            ->when($to,         fn ($q) => $q->where('op.operation_date', '<=', $to))
            ->orderBy('plots.name')
            ->orderBy('op.operation_date', 'desc')
            ->get();

        $grouped = $rows->groupBy('plot_id')->map(fn ($items, $plotId) => [
            'plot_id'              => $plotId,
            'plot_name'            => $items->first()->plot_name,
            'total_quantity'       => round($items->sum(fn ($r) => (float) $r->quantity_harvested), 2),
            'total_worker_days'    => round($items->sum(fn ($r) => (float) $r->num_workers * (float) $r->days_worked), 2),
            'harvests'             => $items->map(fn ($r) => [
                'id'                  => $r->id,
                'date'                => $r->operation_date,
                'num_workers'         => (int) $r->num_workers,
                'days_worked'         => (float) $r->days_worked,
                'quantity_harvested'  => (float) $r->quantity_harvested,
                'daily_rate_at_entry' => (float) $r->daily_rate_at_entry,
            ])->values(),
        ])->values();

        return ApiResponse::ok([
            'plots'   => $grouped,
            'filters' => $this->filterSummary($plotIds, $campaignId, $from, $to),
        ]);
    }

    public function productionCost(ReportRequest $request): JsonResponse
    {
        [$plotIds, $campaignId, $from, $to] = $this->commonFilters($request);

        $plots = DB::table('plots')
            ->when($plotIds, fn ($q) => $q->whereIn('id', $plotIds))
            ->select(['id', 'name', 'surface_area_ha'])
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $applyFilters = function ($q) use ($plotIds, $campaignId, $from, $to) {
            return $q
                ->when($plotIds,    fn ($qq) => $qq->whereIn('plot_id', $plotIds))
                ->when($campaignId, fn ($qq) => $qq->where('campaign_id', $campaignId))
                ->when($from,       fn ($qq) => $qq->where('operation_date', '>=', $from))
                ->when($to,         fn ($qq) => $qq->where('operation_date', '<=', $to));
        };

        $irrigationCosts = $applyFilters(DB::table('irrigation_operations')
            ->select(['plot_id', DB::raw('SUM(water_quantity * price_at_entry) AS cost')]))
            ->groupBy('plot_id')->get()->keyBy('plot_id');

        $fertilizationCosts = $applyFilters(DB::table('fertilization_operations')
            ->select(['plot_id', DB::raw('SUM(quantity_applied * price_at_entry) AS cost')]))
            ->groupBy('plot_id')->get()->keyBy('plot_id');

        $phytosanitaryCosts = $applyFilters(DB::table('phytosanitary_operations')
            ->select(['plot_id', DB::raw('SUM(quantity_applied * price_at_entry) AS cost')]))
            ->groupBy('plot_id')->get()->keyBy('plot_id');

        $harvestCosts = $applyFilters(DB::table('harvest_operations')
            ->select(['plot_id', DB::raw('SUM(num_workers * days_worked * daily_rate_at_entry) AS cost')]))
            ->groupBy('plot_id')->get()->keyBy('plot_id');

        $rows = $plots->map(function ($plot) use ($irrigationCosts, $fertilizationCosts, $phytosanitaryCosts, $harvestCosts) {
            $id  = $plot->id;
            $irr = (float) ($irrigationCosts[$id]->cost ?? 0);
            $fer = (float) ($fertilizationCosts[$id]->cost ?? 0);
            $phy = (float) ($phytosanitaryCosts[$id]->cost ?? 0);
            $har = (float) ($harvestCosts[$id]->cost ?? 0);
            $tot = $irr + $fer + $phy + $har;
            $ha  = (float) $plot->surface_area_ha;

            return [
                'plot_id'              => $id,
                'plot_name'            => $plot->name,
                'surface_area_ha'      => $ha,
                'irrigation_cost'      => round($irr, 4),
                'fertilization_cost'   => round($fer, 4),
                'phytosanitary_cost'   => round($phy, 4),
                'harvest_cost'         => round($har, 4),
                'total_cost'           => round($tot, 4),
                'cost_per_ha'          => $ha > 0 ? round($tot / $ha, 4) : null,
            ];
        })->values();

        return ApiResponse::ok([
            'plots'       => $rows,
            'grand_total' => round($rows->sum('total_cost'), 4),
            'filters'     => $this->filterSummary($plotIds, $campaignId, $from, $to),
        ]);
    }

    /**
     * @return array{0: array<int,string>|null, 1: ?string, 2: ?string, 3: ?string}
     */
    private function commonFilters(ReportRequest $request): array
    {
        $data = $request->validated();

        return [
            $data['plot_ids']    ?? null,
            $data['campaign_id'] ?? null,
            $data['date_from']   ?? null,
            $data['date_to']     ?? null,
        ];
    }

    /** @param array<int,string>|null $plotIds */
    private function filterSummary(?array $plotIds, ?string $campaignId, ?string $from, ?string $to): array
    {
        return [
            'date_from'   => $from,
            'date_to'     => $to,
            'plot_ids'    => $plotIds,
            'campaign_id' => $campaignId,
        ];
    }
}
