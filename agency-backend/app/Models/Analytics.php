<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Analytics extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'metric_type',
        'metric_name',
        'period_type',
        'period_date',
        'dimensions',
        'value',
        'unit',
        'metadata',
        'predictions',
        'confidence_score',
        'anomaly_detection',
        'trends',
        'data_source',
        'is_prediction',
        'is_anomaly',
        'calculated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'dimensions' => 'array',
        'metadata' => 'array',
        'predictions' => 'array',
        'anomaly_detection' => 'array',
        'trends' => 'array',
        'period_date' => 'date',
        'calculated_at' => 'datetime',
        'is_prediction' => 'boolean',
        'is_anomaly' => 'boolean',
        'confidence_score' => 'decimal:4',
        'value' => 'decimal:4',
    ];

    /**
     * Scope for specific metric type.
     */
    public function scopeMetricType(Builder $query, string $type): Builder
    {
        return $query->where('metric_type', $type);
    }

    /**
     * Scope for date range.
     */
    public function scopeDateRange(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('period_date', [$start, $end]);
    }

    /**
     * Scope for predictions only.
     */
    public function scopePredictions(Builder $query): Builder
    {
        return $query->where('is_prediction', true);
    }

    /**
     * Scope for anomalies only.
     */
    public function scopeAnomalies(Builder $query): Builder
    {
        return $query->where('is_anomaly', true);
    }

    /**
     * Generate sales analytics.
     */
    public static function generateSalesAnalytics(Carbon $date = null): void
    {
        $date = $date ?? now();
        
        // Daily sales metrics
        $dailySales = Order::whereDate('created_at', $date)
            ->where('status', 'delivered')
            ->sum('final_amount');

        static::updateOrCreate([
            'metric_type' => 'sales',
            'metric_name' => 'daily_revenue',
            'period_type' => 'daily',
            'period_date' => $date->toDateString(),
        ], [
            'value' => $dailySales,
            'unit' => 'currency',
            'calculated_at' => now(),
        ]);

        // Customer segmentation analytics
        static::generateCustomerSegmentAnalytics($date);
        
        // Product performance analytics
        static::generateProductPerformanceAnalytics($date);
        
        // Geographic analytics
        static::generateGeographicAnalytics($date);
    }

    /**
     * Generate customer segment analytics.
     */
    public static function generateCustomerSegmentAnalytics(Carbon $date): void
    {
        $segments = [
            'new_customers' => Customer::whereDate('created_at', $date)->count(),
            'active_customers' => Customer::whereHas('orders', function ($q) use ($date) {
                $q->whereDate('created_at', $date);
            })->count(),
            'high_value_customers' => Customer::whereHas('orders', function ($q) use ($date) {
                $q->whereDate('created_at', '>=', $date->copy()->subDays(30))
                  ->groupBy('customer_id')
                  ->havingRaw('SUM(final_amount) > 5000');
            })->count(),
        ];

        foreach ($segments as $segment => $count) {
            static::updateOrCreate([
                'metric_type' => 'customer_behavior',
                'metric_name' => $segment,
                'period_type' => 'daily',
                'period_date' => $date->toDateString(),
            ], [
                'value' => $count,
                'unit' => 'count',
                'calculated_at' => now(),
            ]);
        }
    }

    /**
     * Generate product performance analytics.
     */
    public static function generateProductPerformanceAnalytics(Carbon $date): void
    {
        $productPerformance = Order::whereDate('created_at', $date)
            ->where('status', 'delivered')
            ->join('connections', 'orders.connection_id', '=', 'connections.id')
            ->select('connections.cylinder_type', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(final_amount) as revenue'))
            ->groupBy('connections.cylinder_type')
            ->get();

        foreach ($productPerformance as $product) {
            static::updateOrCreate([
                'metric_type' => 'product_performance',
                'metric_name' => 'orders_by_type',
                'period_type' => 'daily',
                'period_date' => $date->toDateString(),
                'dimensions' => ['product_type' => $product->cylinder_type],
            ], [
                'value' => $product->orders_count,
                'unit' => 'count',
                'metadata' => ['revenue' => $product->revenue],
                'calculated_at' => now(),
            ]);
        }
    }

    /**
     * Generate geographic analytics.
     */
    public static function generateGeographicAnalytics(Carbon $date): void
    {
        $geographicData = Order::whereDate('created_at', $date)
            ->where('status', 'delivered')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->select(
                DB::raw('JSON_EXTRACT(customers.address, "$.city") as city'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(final_amount) as revenue')
            )
            ->groupBy('city')
            ->get();

        foreach ($geographicData as $location) {
            static::updateOrCreate([
                'metric_type' => 'geographic',
                'metric_name' => 'orders_by_city',
                'period_type' => 'daily',
                'period_date' => $date->toDateString(),
                'dimensions' => ['city' => $location->city],
            ], [
                'value' => $location->orders_count,
                'unit' => 'count',
                'metadata' => ['revenue' => $location->revenue],
                'calculated_at' => now(),
            ]);
        }
    }

    /**
     * Generate demand forecasting.
     */
    public static function generateDemandForecasting(): array
    {
        // Get historical data for the last 90 days
        $historicalData = static::where('metric_type', 'sales')
            ->where('metric_name', 'daily_revenue')
            ->where('period_date', '>=', now()->subDays(90))
            ->orderBy('period_date')
            ->get();

        if ($historicalData->count() < 30) {
            return ['error' => 'Insufficient historical data for forecasting'];
        }

        // Simple moving average prediction
        $predictions = static::calculateMovingAverageForecast($historicalData);
        
        // Linear regression prediction
        $regressionPredictions = static::calculateLinearRegressionForecast($historicalData);
        
        // Seasonal analysis
        $seasonalAnalysis = static::analyzeSeasonality($historicalData);

        // Store predictions
        foreach ($predictions as $index => $prediction) {
            $predictionDate = now()->addDays($index + 1);
            
            static::updateOrCreate([
                'metric_type' => 'sales',
                'metric_name' => 'daily_revenue',
                'period_type' => 'daily',
                'period_date' => $predictionDate->toDateString(),
                'is_prediction' => true,
            ], [
                'value' => $prediction['value'],
                'confidence_score' => $prediction['confidence'],
                'predictions' => [
                    'moving_average' => $prediction['value'],
                    'linear_regression' => $regressionPredictions[$index] ?? null,
                    'seasonal_factor' => $seasonalAnalysis['factors'][$predictionDate->dayOfWeek] ?? 1,
                ],
                'metadata' => [
                    'forecast_method' => 'ensemble',
                    'historical_days' => $historicalData->count(),
                ],
                'calculated_at' => now(),
            ]);
        }

        return [
            'predictions_generated' => count($predictions),
            'forecast_period_days' => 30,
            'confidence_range' => [0.7, 0.9],
            'seasonal_analysis' => $seasonalAnalysis,
        ];
    }

    /**
     * Calculate moving average forecast.
     */
    private static function calculateMovingAverageForecast($historicalData, $forecastDays = 30): array
    {
        $values = $historicalData->pluck('value')->toArray();
        $windowSize = min(7, count($values)); // 7-day moving average
        
        $predictions = [];
        
        for ($i = 0; $i < $forecastDays; $i++) {
            // Calculate moving average from last window
            $lastValues = array_slice($values, -$windowSize);
            $average = array_sum($lastValues) / count($lastValues);
            
            // Add some randomness based on historical variance
            $variance = static::calculateVariance($lastValues);
            $confidence = max(0.5, 1 - ($variance / $average * 0.1));
            
            $predictions[] = [
                'value' => round($average, 2),
                'confidence' => round($confidence, 4),
            ];
            
            // Add prediction to values for next iteration
            $values[] = $average;
        }
        
        return $predictions;
    }

    /**
     * Calculate linear regression forecast.
     */
    private static function calculateLinearRegressionForecast($historicalData, $forecastDays = 30): array
    {
        $values = $historicalData->pluck('value')->toArray();
        $n = count($values);
        
        if ($n < 2) return [];
        
        // Calculate linear regression coefficients
        $x = range(1, $n);
        $xy = array_map(function($i, $val) { return ($i + 1) * $val; }, array_keys($values), $values);
        $x2 = array_map(function($i) { return ($i + 1) * ($i + 1); }, array_keys($values));
        
        $sumX = array_sum($x);
        $sumY = array_sum($values);
        $sumXY = array_sum($xy);
        $sumX2 = array_sum($x2);
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;
        
        // Generate predictions
        $predictions = [];
        for ($i = 1; $i <= $forecastDays; $i++) {
            $predictionValue = $intercept + $slope * ($n + $i);
            $predictions[] = max(0, round($predictionValue, 2)); // Ensure non-negative
        }
        
        return $predictions;
    }

    /**
     * Analyze seasonality patterns.
     */
    private static function analyzeSeasonality($historicalData): array
    {
        $dayOfWeekData = [];
        
        foreach ($historicalData as $record) {
            $dayOfWeek = Carbon::parse($record->period_date)->dayOfWeek;
            $dayOfWeekData[$dayOfWeek][] = $record->value;
        }
        
        $seasonalFactors = [];
        $overall_average = $historicalData->avg('value');
        
        for ($day = 0; $day < 7; $day++) {
            if (isset($dayOfWeekData[$day])) {
                $dayAverage = array_sum($dayOfWeekData[$day]) / count($dayOfWeekData[$day]);
                $seasonalFactors[$day] = $dayAverage / $overall_average;
            } else {
                $seasonalFactors[$day] = 1.0;
            }
        }
        
        return [
            'factors' => $seasonalFactors,
            'strongest_day' => array_keys($seasonalFactors, max($seasonalFactors))[0],
            'weakest_day' => array_keys($seasonalFactors, min($seasonalFactors))[0],
        ];
    }

    /**
     * Detect anomalies in metrics.
     */
    public static function detectAnomalies(string $metricType, Carbon $date = null): array
    {
        $date = $date ?? now();
        
        // Get data for the last 30 days
        $historicalData = static::where('metric_type', $metricType)
            ->where('period_date', '>=', $date->copy()->subDays(30))
            ->where('period_date', '<', $date)
            ->where('is_prediction', false)
            ->orderBy('period_date')
            ->get();

        if ($historicalData->count() < 10) {
            return ['error' => 'Insufficient data for anomaly detection'];
        }

        $values = $historicalData->pluck('value')->toArray();
        $mean = array_sum($values) / count($values);
        $variance = static::calculateVariance($values);
        $stdDev = sqrt($variance);
        
        // Z-score method for anomaly detection
        $threshold = 2.5; // Standard deviations
        $anomalies = [];
        
        foreach ($historicalData as $record) {
            $zScore = abs(($record->value - $mean) / $stdDev);
            
            if ($zScore > $threshold) {
                $anomalies[] = [
                    'date' => $record->period_date,
                    'value' => $record->value,
                    'z_score' => round($zScore, 2),
                    'deviation' => round($record->value - $mean, 2),
                    'severity' => $zScore > 3 ? 'high' : 'medium',
                ];
                
                // Mark as anomaly in database
                $record->update([
                    'is_anomaly' => true,
                    'anomaly_detection' => [
                        'z_score' => $zScore,
                        'threshold' => $threshold,
                        'severity' => $zScore > 3 ? 'high' : 'medium',
                        'detected_at' => now()->toISOString(),
                    ],
                ]);
            }
        }
        
        return [
            'anomalies_detected' => count($anomalies),
            'anomalies' => $anomalies,
            'analysis_period' => 30,
            'threshold' => $threshold,
            'statistics' => [
                'mean' => round($mean, 2),
                'std_dev' => round($stdDev, 2),
                'variance' => round($variance, 2),
            ],
        ];
    }

    /**
     * Calculate variance.
     */
    private static function calculateVariance(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);
        
        return array_sum($squaredDifferences) / count($values);
    }

    /**
     * Generate comprehensive business insights.
     */
    public static function generateBusinessInsights(): array
    {
        $insights = [];
        
        // Revenue trends
        $revenueInsights = static::analyzeRevenueTrends();
        $insights['revenue'] = $revenueInsights;
        
        // Customer insights
        $customerInsights = static::analyzeCustomerBehavior();
        $insights['customers'] = $customerInsights;
        
        // Operational efficiency
        $operationalInsights = static::analyzeOperationalEfficiency();
        $insights['operations'] = $operationalInsights;
        
        // Market opportunities
        $marketInsights = static::identifyMarketOpportunities();
        $insights['market_opportunities'] = $marketInsights;
        
        return $insights;
    }

    /**
     * Analyze revenue trends.
     */
    private static function analyzeRevenueTrends(): array
    {
        $last30Days = static::where('metric_type', 'sales')
            ->where('metric_name', 'daily_revenue')
            ->where('period_date', '>=', now()->subDays(30))
            ->where('is_prediction', false)
            ->orderBy('period_date')
            ->get();

        $previous30Days = static::where('metric_type', 'sales')
            ->where('metric_name', 'daily_revenue')
            ->where('period_date', '>=', now()->subDays(60))
            ->where('period_date', '<', now()->subDays(30))
            ->where('is_prediction', false)
            ->get();

        $currentPeriodAvg = $last30Days->avg('value');
        $previousPeriodAvg = $previous30Days->avg('value');
        
        $growthRate = $previousPeriodAvg > 0 
            ? (($currentPeriodAvg - $previousPeriodAvg) / $previousPeriodAvg) * 100 
            : 0;

        return [
            'current_period_avg' => round($currentPeriodAvg, 2),
            'previous_period_avg' => round($previousPeriodAvg, 2),
            'growth_rate' => round($growthRate, 2),
            'trend' => $growthRate > 5 ? 'strong_growth' : ($growthRate > 0 ? 'growth' : 'decline'),
            'total_revenue_30d' => round($last30Days->sum('value'), 2),
        ];
    }

    /**
     * Analyze customer behavior.
     */
    private static function analyzeCustomerBehavior(): array
    {
        $newCustomers = static::where('metric_type', 'customer_behavior')
            ->where('metric_name', 'new_customers')
            ->where('period_date', '>=', now()->subDays(30))
            ->sum('value');

        $activeCustomers = static::where('metric_type', 'customer_behavior')
            ->where('metric_name', 'active_customers')
            ->where('period_date', '>=', now()->subDays(30))
            ->avg('value');

        return [
            'new_customers_30d' => $newCustomers,
            'avg_daily_active' => round($activeCustomers, 0),
            'customer_acquisition_trend' => $newCustomers > 50 ? 'strong' : 'moderate',
        ];
    }

    /**
     * Analyze operational efficiency.
     */
    private static function analyzeOperationalEfficiency(): array
    {
        // Calculate delivery success rate
        $totalDeliveries = Delivery::where('created_at', '>=', now()->subDays(30))->count();
        $successfulDeliveries = Delivery::where('created_at', '>=', now()->subDays(30))
            ->where('status', 'delivered')->count();
        
        $successRate = $totalDeliveries > 0 ? ($successfulDeliveries / $totalDeliveries) * 100 : 0;
        
        // Calculate average delivery time
        $avgDeliveryTime = Delivery::where('created_at', '>=', now()->subDays(30))
            ->where('status', 'delivered')
            ->whereNotNull('delivery_time')
            ->whereNotNull('pickup_time')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, pickup_time, delivery_time)) as avg_hours')
            ->value('avg_hours');

        return [
            'delivery_success_rate' => round($successRate, 1),
            'avg_delivery_time_hours' => round($avgDeliveryTime, 1),
            'efficiency_score' => round(($successRate + (24 - min($avgDeliveryTime, 24)) * 4.17) / 2, 1),
        ];
    }

    /**
     * Identify market opportunities.
     */
    private static function identifyMarketOpportunities(): array
    {
        $opportunities = [];
        
        // Geographic expansion opportunities
        $topCities = static::where('metric_type', 'geographic')
            ->where('metric_name', 'orders_by_city')
            ->where('period_date', '>=', now()->subDays(30))
            ->groupBy('dimensions->city')
            ->selectRaw('dimensions->>"$.city" as city, SUM(value) as total_orders')
            ->orderBy('total_orders', 'desc')
            ->limit(5)
            ->get();

        if ($topCities->count() > 0) {
            $opportunities['geographic_expansion'] = [
                'top_performing_cities' => $topCities->pluck('city'),
                'recommendation' => 'Consider expanding operations in top-performing cities',
            ];
        }

        // Product opportunities
        $productPerformance = static::where('metric_type', 'product_performance')
            ->where('period_date', '>=', now()->subDays(30))
            ->groupBy('dimensions->product_type')
            ->selectRaw('dimensions->>"$.product_type" as product_type, SUM(value) as total_orders')
            ->orderBy('total_orders', 'desc')
            ->get();

        if ($productPerformance->count() > 0) {
            $opportunities['product_optimization'] = [
                'best_performing_products' => $productPerformance->pluck('product_type'),
                'recommendation' => 'Focus inventory on high-performing product types',
            ];
        }

        return $opportunities;
    }
}
