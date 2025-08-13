<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

try {
    $service = $app->make('App\Services\PerformanceMonitoringService');
    $report = $service->generateComprehensiveReport();
    
    echo "=== PERFORMANCE MONITORING TEST ===\n";
    echo "Timestamp: " . $report['timestamp'] . "\n";
    echo "Overall Health Score: " . $report['overall_health']['score'] . "\n";
    echo "Overall Health Grade: " . $report['overall_health']['grade'] . "\n";
    echo "Overall Health Status: " . $report['overall_health']['status'] . "\n\n";
    
    echo "=== CDO METRICS ===\n";
    if (isset($report['cdo_metrics'])) {
        echo "Active CDO Properties: " . ($report['cdo_metrics']['total_active'] ?? 'N/A') . "\n";
        echo "Location Validation Rate: " . ($report['cdo_metrics']['location_validation_rate'] ?? 'N/A') . "%\n";
    }
    
    echo "\n=== RECOMMENDATIONS ===\n";
    foreach ($report['recommendations'] as $rec) {
        echo "• " . $rec . "\n";
    }
    
    echo "\n✅ Performance monitoring is working correctly!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
