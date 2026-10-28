<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminAnalyticsController extends Controller
{
    private const RANGES = ['today', '7d', '30d', 'custom'];

    public function index(Request $request)
    {
        $range = in_array($request->input('range'), self::RANGES, true)
            ? $request->input('range')
            : '7d';

        return view('admin.analytics', [
            'departments' => Department::orderBy('name')->get(),
            'filters' => [
                'range' => $range,
                'from' => $request->input('from') ?: today()->subDays(6)->toDateString(),
                'to' => $request->input('to') ?: today()->toDateString(),
                'department_id' => $request->input('department_id'),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::in(self::RANGES)],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:range,custom'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_if:range,custom', 'after_or_equal:from'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        [$from, $to] = $this->resolveRange($validated);
        $departmentId = isset($validated['department_id'])
            ? (int) $validated['department_id']
            : null;

        return response()->json(
            $this->build($from, $to, $departmentId),
            200,
            [],
            JSON_PRESERVE_ZERO_FRACTION
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(array $validated): array
    {
        $today = today();
        $range = $validated['range'] ?? (isset($validated['from'], $validated['to']) ? 'custom' : '7d');

        if ($range === 'custom') {
            $from = Carbon::createFromFormat('Y-m-d', $validated['from'])->startOfDay();
            $to = Carbon::createFromFormat('Y-m-d', $validated['to'])->startOfDay();
        } elseif ($range === 'today') {
            $from = $today->copy();
            $to = $today->copy();
        } elseif ($range === '30d') {
            $from = $today->copy()->subDays(29);
            $to = $today->copy();
        } else {
            $from = $today->copy()->subDays(6);
            $to = $today->copy();
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > 90) {
            $from = $to->copy()->subDays(90);
        }

        return [$from, $to];
    }

    private function build(Carbon $from, Carbon $to, ?int $departmentId): array
    {
        $base = $this->baseQuery($from, $to, $departmentId);

        $statusRows = (clone $base)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $completed = (int) ($statusRows['completed'] ?? 0);
        $noResponse = (int) ($statusRows['no_response'] ?? 0) + (int) ($statusRows['skipped'] ?? 0);
        $waiting = (int) ($statusRows['waiting'] ?? 0);
        $serving = (int) ($statusRows['serving'] ?? 0);
        $total = (int) $statusRows->sum();

        $durations = (clone $base)
            ->selectRaw(
                'AVG(CASE WHEN served_at IS NOT NULL THEN '
                .$this->minutesBetween('created_at', 'served_at').' END) as avg_wait'
            )
            ->selectRaw(
                "AVG(CASE WHEN status = 'completed' AND served_at IS NOT NULL AND completed_at IS NOT NULL THEN "
                .$this->minutesBetween('served_at', 'completed_at').' END) as avg_service'
            )
            ->first();

        return [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => (int) $from->diffInDays($to) + 1,
                'label' => $from->isSameDay($to)
                    ? $from->format('M j, Y')
                    : $from->format('M j, Y').' – '.$to->format('M j, Y'),
            ],
            'kpis' => [
                'total' => $total,
                'completed' => $completed,
                'no_response' => $noResponse,
                'waiting' => $waiting,
                'serving' => $serving,
                'avg_wait_minutes' => $this->roundMinutes($durations?->avg_wait),
                'avg_service_minutes' => $this->roundMinutes($durations?->avg_service),
                'no_show_rate' => $total > 0 ? round(($noResponse / $total) * 100, 1) : 0.0,
            ],
            'hourly' => $this->hourly($base),
            'statuses' => [
                'labels' => ['Completed', 'No response / skipped', 'Waiting', 'Serving'],
                'data' => [$completed, $noResponse, $waiting, $serving],
            ],
            'trend' => $this->trend($base, $from, $to),
            'departments' => $this->departments($base),
            'purposes' => $this->purposes($base),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    private function baseQuery(Carbon $from, Carbon $to, ?int $departmentId): Builder
    {
        return DB::table('queue_entries')
            ->whereBetween('queue_date', [$from->toDateString(), $to->toDateString()])
            ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId));
    }

    private function hourly(Builder $base): array
    {
        $hourExpression = $this->hourExpression('created_at');
        $rows = (clone $base)
            ->selectRaw("{$hourExpression} as hour")
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw($hourExpression)
            ->get();

        $data = array_fill(0, 24, 0);
        foreach ($rows as $row) {
            $data[(int) $row->hour] = (int) $row->total;
        }

        $labels = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $labels[] = Carbon::createFromTime($hour)->format('g A');
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function trend(Builder $base, Carbon $from, Carbon $to): array
    {
        $dateExpression = $this->dateExpression('queue_date');
        $waitExpression = $this->minutesBetween('created_at', 'served_at');
        $serviceExpression = $this->minutesBetween('served_at', 'completed_at');

        $rows = (clone $base)
            ->selectRaw("{$dateExpression} as day")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status IN ('no_response', 'skipped') THEN 1 ELSE 0 END) as no_response")
            ->selectRaw("AVG(CASE WHEN served_at IS NOT NULL THEN {$waitExpression} END) as avg_wait")
            ->selectRaw(
                "AVG(CASE WHEN status = 'completed' AND served_at IS NOT NULL AND completed_at IS NOT NULL "
                ."THEN {$serviceExpression} END) as avg_service"
            )
            ->groupByRaw($dateExpression)
            ->orderByRaw($dateExpression)
            ->get()
            ->keyBy('day');

        $labels = [];
        $total = [];
        $completed = [];
        $noResponse = [];
        $avgWait = [];
        $avgService = [];

        for ($date = $from->copy(); $date->lessThanOrEqualTo($to); $date->addDay()) {
            $key = $date->toDateString();
            $row = $rows->get($key);

            $labels[] = $date->format('M j');
            $total[] = (int) ($row?->total ?? 0);
            $completed[] = (int) ($row?->completed ?? 0);
            $noResponse[] = (int) ($row?->no_response ?? 0);
            $avgWait[] = $this->roundMinutes($row?->avg_wait);
            $avgService[] = $this->roundMinutes($row?->avg_service);
        }

        return [
            'labels' => $labels,
            'total' => $total,
            'completed' => $completed,
            'no_response' => $noResponse,
            'avg_wait' => $avgWait,
            'avg_service' => $avgService,
        ];
    }

    private function departments(Builder $base): array
    {
        $rows = (clone $base)
            ->selectRaw('department_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status IN ('no_response', 'skipped') THEN 1 ELSE 0 END) as no_response")
            ->selectRaw("SUM(CASE WHEN status IN ('waiting', 'serving') THEN 1 ELSE 0 END) as pending")
            ->groupBy('department_id')
            ->orderByDesc('total')
            ->get();

        $ids = $rows->pluck('department_id')->filter()->values();
        $names = $ids->isEmpty()
            ? collect()
            : Department::whereIn('id', $ids)->pluck('name', 'id');

        return [
            'labels' => $rows->map(fn ($row) => $row->department_id
                ? ($names[$row->department_id] ?? 'Department '.$row->department_id)
                : 'Unassigned')->all(),
            'completed' => $rows->map(fn ($row) => (int) $row->completed)->all(),
            'no_response' => $rows->map(fn ($row) => (int) $row->no_response)->all(),
            'pending' => $rows->map(fn ($row) => (int) $row->pending)->all(),
        ];
    }

    private function purposes(Builder $base): array
    {
        $rows = (clone $base)
            ->selectRaw("COALESCE(purpose, 'Unspecified') as label")
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw("COALESCE(purpose, 'Unspecified')")
            ->orderByDesc('total')
            ->get();

        $top = $rows->take(8);
        $other = (int) $rows->slice(8)->sum('total');

        $labels = $top->pluck('label')->all();
        $data = $top->pluck('total')->map(fn ($value) => (int) $value)->all();

        if ($other > 0) {
            $labels[] = 'Other';
            $data[] = $other;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function roundMinutes(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }

    private function dateExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }

    private function hourExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%H', {$column})",
            'pgsql' => "to_char({$column}, 'HH24')",
            default => "DATE_FORMAT({$column}, '%H')",
        };
    }

    private function minutesBetween(string $start, string $end): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "(julianday({$end}) - julianday({$start})) * 1440.0",
            'pgsql' => "(EXTRACT(EPOCH FROM ({$end} - {$start})) / 60.0)",
            default => "(TIMESTAMPDIFF(SECOND, {$start}, {$end}) / 60.0)",
        };
    }
}
