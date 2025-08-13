<?php

namespace App\Http\Controllers;

use App\Services\PerformanceMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PerformanceMonitoringController extends Controller
{
    private PerformanceMonitoringService $performanceService;

    public function __construct(PerformanceMonitoringService $performanceService)
    {
        $this->performanceService = $performanceService;
    }

    /**
     * Display performance monitoring dashboard
     */
    public function dashboard(): View
    {
        $performanceData = $this->performanceService->generateComprehensiveReport();
        
        return view('admin.performance.dashboard', compact('performanceData'));
    }

    /**
     * Get real-time performance metrics
     */
    public function getMetrics(): JsonResponse
    {
        $metrics = [
            'database' => $this->performanceService->getDatabaseMetrics(),
            'cache' => $this->performanceService->getCacheMetrics(),
            'system' => $this->performanceService->getSystemMetrics(),
            'cdo_specific' => $this->performanceService->getCdoPropertyMetrics()
        ];

        return response()->json($metrics);
    }

    /**
     * Get CDO-specific performance metrics
     */
    public function getCdoMetrics(): JsonResponse
    {
        $cdoMetrics = $this->performanceService->getCdoPropertyMetrics();
        
        return response()->json([
            'status' => 'success',
            'data' => $cdoMetrics,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Generate comprehensive performance report
     */
    public function generateReport(Request $request): JsonResponse
    {
        $this->performanceService->startTimer('report_generation');
        
        $report = $this->performanceService->generateComprehensiveReport();
        
        $this->performanceService->endTimer('report_generation');
        
        // Record custom metric for report generation
        $this->performanceService->recordMetric(
            'performance_report_generated',
            1,
            ['user_id' => auth()->id()]
        );

        return response()->json([
            'status' => 'success',
            'report' => $report,
            'generated_at' => now()->toISOString()
        ]);
    }

    /**
     * Get system health status
     */
    public function getHealthStatus(): JsonResponse
    {
        $report = $this->performanceService->generateComprehensiveReport();
        $healthScore = $report['overall_health'];

        return response()->json([
            'status' => 'success',
            'health' => [
                'score' => $healthScore['score'],
                'grade' => $healthScore['grade'],
                'status' => $healthScore['status'],
                'components' => $healthScore['component_scores'] ?? [],
                'timestamp' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get performance trends (historical data)
     */
    public function getTrends(Request $request): JsonResponse
    {
        $period = $request->get('period', '24h'); // 24h, 7d, 30d
        
        // This would typically pull from a time-series database
        // For now, we'll generate sample trend data
        $trends = $this->generateSampleTrends($period);

        return response()->json([
            'status' => 'success',
            'trends' => $trends,
            'period' => $period
        ]);
    }

    /**
     * Export performance data
     */
    public function exportData(Request $request)
    {
        $format = $request->get('format', 'json'); // json, csv, pdf
        $report = $this->performanceService->generateComprehensiveReport();

        switch ($format) {
            case 'csv':
                return $this->exportAsCsv($report);
            case 'pdf':
                return $this->exportAsPdf($report);
            default:
                return response()->json($report)
                    ->header('Content-Disposition', 'attachment; filename="performance-report-' . date('Y-m-d-H-i-s') . '.json"');
        }
    }

    /**
     * Get performance alerts
     */
    public function getAlerts(): JsonResponse
    {
        $report = $this->performanceService->generateComprehensiveReport();
        $alerts = [];

        // Check for performance issues and generate alerts
        if (isset($report['performance']['database']['query_time_ms']) && 
            $report['performance']['database']['query_time_ms'] > 200) {
            $alerts[] = [
                'type' => 'warning',
                'component' => 'database',
                'message' => 'Database queries are slower than expected',
                'value' => $report['performance']['database']['query_time_ms'],
                'threshold' => 200,
                'severity' => 'medium'
            ];
        }

        if (isset($report['performance']['system']['memory_usage_percentage']) && 
            $report['performance']['system']['memory_usage_percentage'] > 85) {
            $alerts[] = [
                'type' => 'critical',
                'component' => 'memory',
                'message' => 'High memory usage detected',
                'value' => $report['performance']['system']['memory_usage_percentage'],
                'threshold' => 85,
                'severity' => 'high'
            ];
        }

        // CDO-specific alerts
        if (isset($report['cdo_metrics']['total_active']) && 
            $report['cdo_metrics']['total_active'] < 5) {
            $alerts[] = [
                'type' => 'info',
                'component' => 'cdo_properties',
                'message' => 'Low number of active CDO properties',
                'value' => $report['cdo_metrics']['total_active'],
                'threshold' => 5,
                'severity' => 'low'
            ];
        }

        return response()->json([
            'status' => 'success',
            'alerts' => $alerts,
            'count' => count($alerts)
        ]);
    }

    /**
     * Generate sample trend data
     */
    private function generateSampleTrends(string $period): array
    {
        $points = match($period) {
            '24h' => 24,
            '7d' => 7,
            '30d' => 30,
            default => 24
        };

        $trends = [
            'database_response_time' => [],
            'memory_usage' => [],
            'cdo_property_queries' => [],
            'user_activity' => []
        ];

        for ($i = 0; $i < $points; $i++) {
            $timestamp = now()->subHours($points - $i)->toISOString();
            
            $trends['database_response_time'][] = [
                'timestamp' => $timestamp,
                'value' => rand(20, 150) // milliseconds
            ];
            
            $trends['memory_usage'][] = [
                'timestamp' => $timestamp,
                'value' => rand(40, 85) // percentage
            ];
            
            $trends['cdo_property_queries'][] = [
                'timestamp' => $timestamp,
                'value' => rand(10, 50) // queries per hour
            ];
            
            $trends['user_activity'][] = [
                'timestamp' => $timestamp,
                'value' => rand(5, 25) // active users
            ];
        }

        return $trends;
    }

    /**
     * Export data as CSV
     */
    private function exportAsCsv(array $report)
    {
        $filename = 'performance-report-' . date('Y-m-d-H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($report) {
            $file = fopen('php://output', 'w');
            
            // Header
            fputcsv($file, ['Metric', 'Value', 'Status', 'Timestamp']);
            
            // Database metrics
            if (isset($report['performance']['database'])) {
                $db = $report['performance']['database'];
                fputcsv($file, ['Database Response Time (ms)', $db['query_time_ms'] ?? 'N/A', 'Info', now()]);
                fputcsv($file, ['Database Connections', $db['connection_count'] ?? 'N/A', 'Info', now()]);
            }
            
            // System metrics
            if (isset($report['performance']['system'])) {
                $sys = $report['performance']['system'];
                fputcsv($file, ['Memory Usage (%)', $sys['memory_usage_percentage'] ?? 'N/A', 'Info', now()]);
                fputcsv($file, ['CPU Usage (%)', $sys['cpu_usage'] ?? 'N/A', 'Info', now()]);
            }
            
            // CDO metrics
            if (isset($report['cdo_metrics'])) {
                $cdo = $report['cdo_metrics'];
                fputcsv($file, ['Active CDO Properties', $cdo['total_active'] ?? 'N/A', 'Info', now()]);
                fputcsv($file, ['Location Validation Rate (%)', $cdo['location_validation_rate'] ?? 'N/A', 'Info', now()]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export data as PDF (placeholder)
     */
    private function exportAsPdf(array $report)
    {
        // This would require a PDF library like DomPDF or similar
        // For now, return JSON with a message
        return response()->json([
            'message' => 'PDF export feature coming soon',
            'alternative' => 'Use CSV export for now'
        ], 501);
    }
}
