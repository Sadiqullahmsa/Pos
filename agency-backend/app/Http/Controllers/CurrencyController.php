<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CurrencyController extends Controller
{
    /**
     * Display a listing of currencies
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Currency::orderBy('is_base', 'desc')
                ->orderBy('is_active', 'desc')
                ->orderBy('name', 'asc');

            // Apply filters
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('is_base')) {
                $query->where('is_base', $request->boolean('is_base'));
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('symbol', 'like', "%{$search}%");
                });
            }

            $currencies = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $currencies,
                'summary' => [
                    'total_currencies' => Currency::count(),
                    'active_currencies' => Currency::where('is_active', true)->count(),
                    'base_currency' => Currency::where('is_base', true)->first(),
                    'last_rate_update' => Currency::whereNotNull('last_rate_update')
                        ->orderBy('last_rate_update', 'desc')
                        ->first()?->last_rate_update,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Currency listing failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve currencies'
            ], 500);
        }
    }

    /**
     * Store a newly created currency
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:currencies',
                'code' => 'required|string|max:3|unique:currencies',
                'symbol' => 'required|string|max:10',
                'decimal_places' => 'required|integer|min:0|max:8',
                'exchange_rate' => 'required|numeric|min:0.000001',
                'is_active' => 'boolean',
                'auto_update_rates' => 'boolean',
                'rate_source' => 'nullable|string|in:fixer,openexchangerates,exchangerate-api,manual',
                'format_template' => 'nullable|string|max:100',
                'position' => 'nullable|string|in:before,after',
            ]);

            DB::beginTransaction();

            $currency = Currency::create([
                'name' => $request->name,
                'code' => strtoupper($request->code),
                'symbol' => $request->symbol,
                'decimal_places' => $request->decimal_places,
                'exchange_rate' => $request->exchange_rate,
                'is_active' => $request->is_active ?? true,
                'is_base' => false, // New currencies are never base by default
                'auto_update_rates' => $request->auto_update_rates ?? false,
                'rate_source' => $request->rate_source ?? 'manual',
                'format_template' => $request->format_template ?? '{symbol}{amount}',
                'position' => $request->position ?? 'before',
                'created_by' => Auth::id(),
                'metadata' => [
                    'created_source' => 'web',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);

            // Update exchange rates if auto-update is enabled
            if ($request->auto_update_rates && $request->rate_source !== 'manual') {
                $this->updateSingleCurrencyRate($currency);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Currency created successfully',
                'data' => $currency
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Currency creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create currency'
            ], 500);
        }
    }

    /**
     * Display the specified currency
     */
    public function show(Currency $currency): JsonResponse
    {
        try {
            $currency->load(['rateHistory']);
            
            return response()->json([
                'success' => true,
                'data' => $currency,
                'rate_history' => $currency->rate_history ?? [],
                'conversion_examples' => $this->getConversionExamples($currency),
            ]);
        } catch (\Exception $e) {
            Log::error('Currency retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve currency'
            ], 500);
        }
    }

    /**
     * Update the specified currency
     */
    public function update(Request $request, Currency $currency): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'sometimes|string|max:255|unique:currencies,name,' . $currency->id,
                'code' => 'sometimes|string|max:3|unique:currencies,code,' . $currency->id,
                'symbol' => 'sometimes|string|max:10',
                'decimal_places' => 'sometimes|integer|min:0|max:8',
                'exchange_rate' => 'sometimes|numeric|min:0.000001',
                'is_active' => 'sometimes|boolean',
                'auto_update_rates' => 'sometimes|boolean',
                'rate_source' => 'nullable|string|in:fixer,openexchangerates,exchangerate-api,manual',
                'format_template' => 'nullable|string|max:100',
                'position' => 'nullable|string|in:before,after',
            ]);

            $oldRate = $currency->exchange_rate;
            $currency->update($request->all());

            // Log rate change if exchange rate was updated
            if ($request->has('exchange_rate') && $oldRate != $request->exchange_rate) {
                $this->logRateChange($currency, $oldRate, $request->exchange_rate, 'manual_update');
            }

            return response()->json([
                'success' => true,
                'message' => 'Currency updated successfully',
                'data' => $currency
            ]);

        } catch (\Exception $e) {
            Log::error('Currency update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update currency'
            ], 500);
        }
    }

    /**
     * Remove the specified currency
     */
    public function destroy(Currency $currency): JsonResponse
    {
        try {
            if ($currency->is_base) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete base currency'
                ], 400);
            }

            // Check if currency is in use
            if ($this->isCurrencyInUse($currency)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete currency that is in use'
                ], 400);
            }

            $currency->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Currency deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Currency deletion failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete currency'
            ], 500);
        }
    }

    /**
     * Get active currencies
     */
    public function getActiveCurrencies(): JsonResponse
    {
        try {
            $currencies = Currency::where('is_active', true)
                ->orderBy('is_base', 'desc')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $currencies
            ]);
        } catch (\Exception $e) {
            Log::error('Active currencies retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active currencies'
            ], 500);
        }
    }

    /**
     * Update currency status
     */
    public function updateStatus(Request $request, Currency $currency): JsonResponse
    {
        try {
            $request->validate([
                'is_active' => 'required|boolean',
                'reason' => 'nullable|string|max:255',
            ]);

            if ($currency->is_base && !$request->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot deactivate base currency'
                ], 400);
            }

            $currency->update([
                'is_active' => $request->is_active,
                'status_updated_at' => now(),
                'status_updated_by' => Auth::id(),
            ]);

            // Log status change
            $this->logStatusChange($currency, $request->is_active, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Currency status updated successfully',
                'data' => $currency
            ]);

        } catch (\Exception $e) {
            Log::error('Currency status update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update currency status'
            ], 500);
        }
    }

    /**
     * Set base currency
     */
    public function setBaseCurrency(Request $request, Currency $currency): JsonResponse
    {
        try {
            if (!$currency->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot set inactive currency as base'
                ], 400);
            }

            DB::beginTransaction();

            // Remove base status from current base currency
            Currency::where('is_base', true)->update(['is_base' => false]);

            // Set new base currency
            $currency->update([
                'is_base' => true,
                'exchange_rate' => 1.0, // Base currency always has rate of 1
                'base_set_at' => now(),
                'base_set_by' => Auth::id(),
            ]);

            // Recalculate all other exchange rates relative to new base
            $this->recalculateExchangeRates($currency);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Base currency updated successfully',
                'data' => $currency
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Base currency update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update base currency'
            ], 500);
        }
    }

    /**
     * Update exchange rates
     */
    public function updateExchangeRates(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'source' => 'nullable|string|in:fixer,openexchangerates,exchangerate-api,all',
                'currencies' => 'nullable|array',
                'currencies.*' => 'exists:currencies,code',
                'force_update' => 'boolean',
            ]);

            $source = $request->source ?? 'all';
            $targetCurrencies = $request->currencies;
            $forceUpdate = $request->force_update ?? false;

            $results = [];
            $errors = [];

            // Get currencies to update
            $query = Currency::where('is_active', true)
                ->where('is_base', false);

            if ($targetCurrencies) {
                $query->whereIn('code', $targetCurrencies);
            } else {
                $query->where('auto_update_rates', true);
            }

            $currencies = $query->get();

            foreach ($currencies as $currency) {
                try {
                    $rateSource = $source === 'all' ? $currency->rate_source : $source;
                    
                    if ($rateSource === 'manual') {
                        continue; // Skip manual currencies
                    }

                    $newRate = $this->fetchExchangeRate($currency->code, $rateSource);
                    
                    if ($newRate && ($forceUpdate || $this->shouldUpdateRate($currency, $newRate))) {
                        $oldRate = $currency->exchange_rate;
                        
                        $currency->update([
                            'exchange_rate' => $newRate,
                            'last_rate_update' => now(),
                            'rate_source_last_used' => $rateSource,
                        ]);

                        $this->logRateChange($currency, $oldRate, $newRate, 'auto_update');
                        
                        $results[] = [
                            'currency' => $currency->code,
                            'old_rate' => $oldRate,
                            'new_rate' => $newRate,
                            'status' => 'updated'
                        ];
                    } else {
                        $results[] = [
                            'currency' => $currency->code,
                            'current_rate' => $currency->exchange_rate,
                            'status' => 'no_change'
                        ];
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'currency' => $currency->code,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Exchange rates update completed',
                'data' => [
                    'updated' => count(array_filter($results, fn($r) => $r['status'] === 'updated')),
                    'unchanged' => count(array_filter($results, fn($r) => $r['status'] === 'no_change')),
                    'errors' => count($errors),
                    'results' => $results,
                    'errors' => $errors,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Exchange rates update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update exchange rates'
            ], 500);
        }
    }

    /**
     * Get exchange rate history
     */
    public function getRateHistory(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'currency_code' => 'required|exists:currencies,code',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
                'limit' => 'nullable|integer|min:1|max:1000',
            ]);

            $currency = Currency::where('code', $request->currency_code)->first();
            $history = $currency->rate_history ?? [];

            // Filter by date range if provided
            if ($request->date_from || $request->date_to) {
                $history = array_filter($history, function($record) use ($request) {
                    $recordDate = Carbon::parse($record['timestamp']);
                    
                    if ($request->date_from && $recordDate->lt(Carbon::parse($request->date_from))) {
                        return false;
                    }
                    
                    if ($request->date_to && $recordDate->gt(Carbon::parse($request->date_to))) {
                        return false;
                    }
                    
                    return true;
                });
            }

            // Sort by timestamp descending
            usort($history, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });

            // Limit results
            if ($request->limit) {
                $history = array_slice($history, 0, $request->limit);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'currency' => $currency,
                    'history' => $history,
                    'total_records' => count($history),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Rate history retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve rate history'
            ], 500);
        }
    }

    /**
     * Convert amount between currencies
     */
    public function convertAmount(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0',
                'from_currency' => 'required|exists:currencies,code',
                'to_currency' => 'required|exists:currencies,code',
                'use_live_rates' => 'boolean',
            ]);

            $fromCurrency = Currency::where('code', $request->from_currency)->first();
            $toCurrency = Currency::where('code', $request->to_currency)->first();

            if (!$fromCurrency->is_active || !$toCurrency->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or both currencies are not active'
                ], 400);
            }

            $amount = $request->amount;
            $useLiveRates = $request->use_live_rates ?? false;

            // Get exchange rates
            $fromRate = $fromCurrency->exchange_rate;
            $toRate = $toCurrency->exchange_rate;

            // Fetch live rates if requested
            if ($useLiveRates) {
                if (!$fromCurrency->is_base) {
                    $liveFromRate = $this->fetchExchangeRate($fromCurrency->code, $fromCurrency->rate_source);
                    if ($liveFromRate) {
                        $fromRate = $liveFromRate;
                    }
                }
                
                if (!$toCurrency->is_base) {
                    $liveToRate = $this->fetchExchangeRate($toCurrency->code, $toCurrency->rate_source);
                    if ($liveToRate) {
                        $toRate = $liveToRate;
                    }
                }
            }

            // Convert amount
            $convertedAmount = $this->convertCurrency($amount, $fromRate, $toRate);

            return response()->json([
                'success' => true,
                'data' => [
                    'original_amount' => $amount,
                    'converted_amount' => $convertedAmount,
                    'from_currency' => [
                        'code' => $fromCurrency->code,
                        'symbol' => $fromCurrency->symbol,
                        'rate' => $fromRate,
                        'formatted' => $this->formatCurrency($amount, $fromCurrency),
                    ],
                    'to_currency' => [
                        'code' => $toCurrency->code,
                        'symbol' => $toCurrency->symbol,
                        'rate' => $toRate,
                        'formatted' => $this->formatCurrency($convertedAmount, $toCurrency),
                    ],
                    'exchange_rate' => $toRate / $fromRate,
                    'calculation_time' => now(),
                    'rates_source' => $useLiveRates ? 'live' : 'cached',
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Currency conversion failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert currency'
            ], 500);
        }
    }

    /**
     * Fetch exchange rate from external API
     */
    private function fetchExchangeRate(string $currencyCode, string $source): ?float
    {
        try {
            $baseCurrency = Currency::where('is_base', true)->first();
            $cacheKey = "exchange_rate_{$source}_{$baseCurrency->code}_{$currencyCode}";
            
            // Check cache first (cache for 5 minutes)
            $cachedRate = Cache::get($cacheKey);
            if ($cachedRate) {
                return $cachedRate;
            }

            $rate = match($source) {
                'fixer' => $this->fetchFromFixer($baseCurrency->code, $currencyCode),
                'openexchangerates' => $this->fetchFromOpenExchangeRates($baseCurrency->code, $currencyCode),
                'exchangerate-api' => $this->fetchFromExchangeRateAPI($baseCurrency->code, $currencyCode),
                default => null,
            };

            if ($rate) {
                Cache::put($cacheKey, $rate, now()->addMinutes(5));
            }

            return $rate;
        } catch (\Exception $e) {
            Log::error('Exchange rate fetch failed', [
                'source' => $source,
                'currency' => $currencyCode,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Fetch rate from Fixer.io
     */
    private function fetchFromFixer(string $base, string $target): ?float
    {
        $apiKey = config('services.fixer.api_key');
        if (!$apiKey) {
            return null;
        }

        $response = Http::get("https://api.fixer.io/latest", [
            'access_key' => $apiKey,
            'base' => $base,
            'symbols' => $target,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['rates'][$target] ?? null;
        }

        return null;
    }

    /**
     * Fetch rate from OpenExchangeRates
     */
    private function fetchFromOpenExchangeRates(string $base, string $target): ?float
    {
        $apiKey = config('services.openexchangerates.api_key');
        if (!$apiKey) {
            return null;
        }

        $response = Http::get("https://openexchangerates.org/api/latest.json", [
            'app_id' => $apiKey,
            'base' => $base,
            'symbols' => $target,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['rates'][$target] ?? null;
        }

        return null;
    }

    /**
     * Fetch rate from ExchangeRate-API
     */
    private function fetchFromExchangeRateAPI(string $base, string $target): ?float
    {
        $response = Http::get("https://api.exchangerate-api.com/v4/latest/{$base}");

        if ($response->successful()) {
            $data = $response->json();
            return $data['rates'][$target] ?? null;
        }

        return null;
    }

    /**
     * Convert currency amount
     */
    private function convertCurrency(float $amount, float $fromRate, float $toRate): float
    {
        // Convert to base currency first, then to target currency
        $baseAmount = $amount / $fromRate;
        return $baseAmount * $toRate;
    }

    /**
     * Format currency amount
     */
    private function formatCurrency(float $amount, Currency $currency): string
    {
        $formatted = number_format($amount, $currency->decimal_places);
        $template = $currency->format_template ?? '{symbol}{amount}';
        
        return str_replace(
            ['{symbol}', '{amount}', '{code}'],
            [$currency->symbol, $formatted, $currency->code],
            $template
        );
    }

    /**
     * Check if currency is in use
     */
    private function isCurrencyInUse(Currency $currency): bool
    {
        // Check if currency is used in invoices, orders, etc.
        // This would check all relevant tables
        return false; // Placeholder implementation
    }

    /**
     * Update single currency rate
     */
    private function updateSingleCurrencyRate(Currency $currency): void
    {
        if ($currency->is_base || $currency->rate_source === 'manual') {
            return;
        }

        $newRate = $this->fetchExchangeRate($currency->code, $currency->rate_source);
        
        if ($newRate) {
            $oldRate = $currency->exchange_rate;
            $currency->update([
                'exchange_rate' => $newRate,
                'last_rate_update' => now(),
            ]);
            
            $this->logRateChange($currency, $oldRate, $newRate, 'auto_update');
        }
    }

    /**
     * Check if rate should be updated
     */
    private function shouldUpdateRate(Currency $currency, float $newRate): bool
    {
        $threshold = 0.01; // 1% change threshold
        $oldRate = $currency->exchange_rate;
        $changePercent = abs(($newRate - $oldRate) / $oldRate) * 100;
        
        return $changePercent >= $threshold;
    }

    /**
     * Log rate change
     */
    private function logRateChange(Currency $currency, float $oldRate, float $newRate, string $source): void
    {
        $history = $currency->rate_history ?? [];
        $history[] = [
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
            'change_percent' => (($newRate - $oldRate) / $oldRate) * 100,
            'source' => $source,
            'timestamp' => now(),
            'updated_by' => Auth::id(),
        ];

        // Keep only last 100 records
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }

        $currency->update(['rate_history' => $history]);
    }

    /**
     * Log status change
     */
    private function logStatusChange(Currency $currency, bool $isActive, ?string $reason): void
    {
        $statusHistory = $currency->status_history ?? [];
        $statusHistory[] = [
            'status' => $isActive ? 'activated' : 'deactivated',
            'reason' => $reason,
            'timestamp' => now(),
            'changed_by' => Auth::id(),
        ];

        $currency->update(['status_history' => $statusHistory]);
    }

    /**
     * Recalculate exchange rates relative to new base
     */
    private function recalculateExchangeRates(Currency $newBaseCurrency): void
    {
        // Implementation for recalculating rates when base currency changes
        // This would involve complex calculations to maintain relative rates
    }

    /**
     * Get conversion examples
     */
    private function getConversionExamples(Currency $currency): array
    {
        $baseCurrency = Currency::where('is_base', true)->first();
        $examples = [];

        if ($baseCurrency && $currency->id !== $baseCurrency->id) {
            $amounts = [1, 10, 100, 1000];
            
            foreach ($amounts as $amount) {
                $examples[] = [
                    'amount' => $amount,
                    'from' => $this->formatCurrency($amount, $currency),
                    'to' => $this->formatCurrency(
                        $this->convertCurrency($amount, $currency->exchange_rate, $baseCurrency->exchange_rate),
                        $baseCurrency
                    ),
                ];
            }
        }

        return $examples;
    }
}
