<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Builder;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'category',
        'key',
        'name',
        'description',
        'value',
        'type',
        'options',
        'validation_rules',
        'is_encrypted',
        'is_public',
        'requires_restart',
        'sort_order',
        'is_active',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'value' => 'array',
        'options' => 'array',
        'validation_rules' => 'array',
        'metadata' => 'array',
        'is_encrypted' => 'boolean',
        'is_public' => 'boolean',
        'requires_restart' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($setting) {
            // Clear cache when setting is updated
            Cache::forget("setting.{$setting->key}");
            Cache::forget("settings.{$setting->category}");
            Cache::forget('settings.all');
        });

        static::deleted(function ($setting) {
            Cache::forget("setting.{$setting->key}");
            Cache::forget("settings.{$setting->category}");
            Cache::forget('settings.all');
        });
    }

    /**
     * Scope for public settings.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope for category.
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for active settings.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get setting value with automatic decryption.
     */
    public function getValueAttribute($value)
    {
        $decodedValue = json_decode($value, true);
        
        if ($this->is_encrypted && $decodedValue) {
            try {
                return Crypt::decrypt($decodedValue);
            } catch (\Exception $e) {
                return $decodedValue;
            }
        }

        return $decodedValue;
    }

    /**
     * Set setting value with automatic encryption.
     */
    public function setValueAttribute($value)
    {
        if ($this->is_encrypted) {
            $value = Crypt::encrypt($value);
        }

        $this->attributes['value'] = json_encode($value);
    }

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->where('is_active', true)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, $value, array $options = []): self
    {
        $setting = static::where('key', $key)->first();

        if ($setting) {
            $setting->update(['value' => $value] + $options);
        } else {
            $setting = static::create([
                'key' => $key,
                'value' => $value,
                'category' => $options['category'] ?? 'general',
                'name' => $options['name'] ?? $key,
                'type' => $options['type'] ?? 'text',
            ] + $options);
        }

        return $setting;
    }

    /**
     * Get all settings by category.
     */
    public static function getByCategory(string $category): array
    {
        return Cache::remember("settings.{$category}", 3600, function () use ($category) {
            return static::where('category', $category)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    /**
     * Get all public settings.
     */
    public static function getPublicSettings(): array
    {
        return Cache::remember('settings.public', 3600, function () {
            return static::where('is_public', true)
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('sort_order')
                ->get()
                ->groupBy('category')
                ->map(function ($settings) {
                    return $settings->pluck('value', 'key');
                })
                ->toArray();
        });
    }

    /**
     * Get all settings.
     */
    public static function getAllSettings(): array
    {
        return Cache::remember('settings.all', 3600, function () {
            return static::where('is_active', true)
                ->orderBy('category')
                ->orderBy('sort_order')
                ->get()
                ->groupBy('category')
                ->map(function ($settings) {
                    return $settings->mapWithKeys(function ($setting) {
                        return [
                            $setting->key => [
                                'value' => $setting->value,
                                'name' => $setting->name,
                                'description' => $setting->description,
                                'type' => $setting->type,
                                'options' => $setting->options,
                                'is_public' => $setting->is_public,
                                'requires_restart' => $setting->requires_restart,
                            ]
                        ];
                    });
                })
                ->toArray();
        });
    }

    /**
     * Initialize default system settings.
     */
    public static function initializeDefaults(): void
    {
        $defaults = [
            // System Settings
            'system' => [
                'app_name' => [
                    'value' => 'LPG Gas Agency',
                    'name' => 'Application Name',
                    'type' => 'text',
                    'is_public' => true,
                ],
                'app_url' => [
                    'value' => env('APP_URL', 'http://localhost'),
                    'name' => 'Application URL',
                    'type' => 'url',
                    'is_public' => true,
                ],
                'timezone' => [
                    'value' => 'Asia/Kolkata',
                    'name' => 'Default Timezone',
                    'type' => 'select',
                    'options' => ['Asia/Kolkata', 'UTC', 'America/New_York'],
                    'is_public' => true,
                ],
                'date_format' => [
                    'value' => 'Y-m-d',
                    'name' => 'Date Format',
                    'type' => 'select',
                    'options' => ['Y-m-d', 'd/m/Y', 'm/d/Y'],
                    'is_public' => true,
                ],
                'time_format' => [
                    'value' => 'H:i:s',
                    'name' => 'Time Format',
                    'type' => 'select',
                    'options' => ['H:i:s', 'h:i:s A'],
                    'is_public' => true,
                ],
                'maintenance_mode' => [
                    'value' => false,
                    'name' => 'Maintenance Mode',
                    'type' => 'boolean',
                    'requires_restart' => true,
                ],
            ],

            // Currency Settings
            'currency' => [
                'default_currency' => [
                    'value' => 'INR',
                    'name' => 'Default Currency',
                    'type' => 'select',
                    'options' => ['INR', 'USD', 'EUR', 'GBP'],
                    'is_public' => true,
                ],
                'currency_position' => [
                    'value' => 'before',
                    'name' => 'Currency Symbol Position',
                    'type' => 'select',
                    'options' => ['before', 'after'],
                    'is_public' => true,
                ],
                'decimal_places' => [
                    'value' => 2,
                    'name' => 'Decimal Places',
                    'type' => 'number',
                    'is_public' => true,
                ],
                'auto_update_rates' => [
                    'value' => true,
                    'name' => 'Auto Update Exchange Rates',
                    'type' => 'boolean',
                ],
            ],

            // Business Settings
            'business' => [
                'company_name' => [
                    'value' => 'LPG Gas Agency Pvt Ltd',
                    'name' => 'Company Name',
                    'type' => 'text',
                    'is_public' => true,
                ],
                'company_address' => [
                    'value' => '',
                    'name' => 'Company Address',
                    'type' => 'textarea',
                    'is_public' => true,
                ],
                'company_phone' => [
                    'value' => '',
                    'name' => 'Company Phone',
                    'type' => 'text',
                    'is_public' => true,
                ],
                'company_email' => [
                    'value' => '',
                    'name' => 'Company Email',
                    'type' => 'email',
                    'is_public' => true,
                ],
                'company_logo' => [
                    'value' => '',
                    'name' => 'Company Logo',
                    'type' => 'file',
                    'is_public' => true,
                ],
                'business_hours' => [
                    'value' => '09:00-18:00',
                    'name' => 'Business Hours',
                    'type' => 'text',
                    'is_public' => true,
                ],
                'working_days' => [
                    'value' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                    'name' => 'Working Days',
                    'type' => 'multiselect',
                    'options' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                    'is_public' => true,
                ],
            ],

            // Notification Settings
            'notification' => [
                'email_notifications' => [
                    'value' => true,
                    'name' => 'Email Notifications',
                    'type' => 'boolean',
                ],
                'sms_notifications' => [
                    'value' => true,
                    'name' => 'SMS Notifications',
                    'type' => 'boolean',
                ],
                'push_notifications' => [
                    'value' => true,
                    'name' => 'Push Notifications',
                    'type' => 'boolean',
                ],
                'whatsapp_notifications' => [
                    'value' => true,
                    'name' => 'WhatsApp Notifications',
                    'type' => 'boolean',
                ],
            ],

            // Security Settings
            'security' => [
                'session_lifetime' => [
                    'value' => 1440,
                    'name' => 'Session Lifetime (minutes)',
                    'type' => 'number',
                    'requires_restart' => true,
                ],
                'password_min_length' => [
                    'value' => 8,
                    'name' => 'Minimum Password Length',
                    'type' => 'number',
                ],
                'require_2fa' => [
                    'value' => false,
                    'name' => 'Require Two-Factor Authentication',
                    'type' => 'boolean',
                ],
                'login_attempts' => [
                    'value' => 5,
                    'name' => 'Max Login Attempts',
                    'type' => 'number',
                ],
                'lockout_duration' => [
                    'value' => 15,
                    'name' => 'Lockout Duration (minutes)',
                    'type' => 'number',
                ],
            ],

            // API Settings
            'api' => [
                'rate_limit_per_minute' => [
                    'value' => 60,
                    'name' => 'API Rate Limit Per Minute',
                    'type' => 'number',
                ],
                'api_timeout' => [
                    'value' => 30,
                    'name' => 'API Timeout (seconds)',
                    'type' => 'number',
                ],
                'enable_api_logging' => [
                    'value' => true,
                    'name' => 'Enable API Logging',
                    'type' => 'boolean',
                ],
            ],

            // Order Settings
            'order' => [
                'auto_confirm_orders' => [
                    'value' => false,
                    'name' => 'Auto Confirm Orders',
                    'type' => 'boolean',
                ],
                'order_expiry_hours' => [
                    'value' => 24,
                    'name' => 'Order Expiry Hours',
                    'type' => 'number',
                ],
                'allow_order_cancellation' => [
                    'value' => true,
                    'name' => 'Allow Order Cancellation',
                    'type' => 'boolean',
                ],
                'cancellation_deadline_hours' => [
                    'value' => 2,
                    'name' => 'Cancellation Deadline (hours)',
                    'type' => 'number',
                ],
            ],

            // Delivery Settings
            'delivery' => [
                'delivery_radius_km' => [
                    'value' => 50,
                    'name' => 'Delivery Radius (km)',
                    'type' => 'number',
                ],
                'delivery_slots' => [
                    'value' => ['09:00-12:00', '12:00-15:00', '15:00-18:00'],
                    'name' => 'Delivery Time Slots',
                    'type' => 'array',
                ],
                'enable_route_optimization' => [
                    'value' => true,
                    'name' => 'Enable Route Optimization',
                    'type' => 'boolean',
                ],
                'track_delivery_gps' => [
                    'value' => true,
                    'name' => 'Track Delivery GPS',
                    'type' => 'boolean',
                ],
            ],

            // Emergency Settings
            'emergency' => [
                'emergency_hotline' => [
                    'value' => '1800-123-4567',
                    'name' => 'Emergency Hotline',
                    'type' => 'text',
                    'is_public' => true,
                ],
                'auto_escalate_critical' => [
                    'value' => true,
                    'name' => 'Auto Escalate Critical Emergencies',
                    'type' => 'boolean',
                ],
                'emergency_response_time_minutes' => [
                    'value' => 30,
                    'name' => 'Emergency Response Time (minutes)',
                    'type' => 'number',
                ],
                'emergency_notification_list' => [
                    'value' => [],
                    'name' => 'Emergency Notification List',
                    'type' => 'array',
                ],
            ],
        ];

        foreach ($defaults as $category => $settings) {
            foreach ($settings as $key => $config) {
                $fullKey = "{$category}.{$key}";
                
                if (!static::where('key', $fullKey)->exists()) {
                    static::create([
                        'category' => $category,
                        'key' => $fullKey,
                        'name' => $config['name'],
                        'description' => $config['description'] ?? null,
                        'value' => $config['value'],
                        'type' => $config['type'],
                        'options' => $config['options'] ?? null,
                        'is_encrypted' => $config['is_encrypted'] ?? false,
                        'is_public' => $config['is_public'] ?? false,
                        'requires_restart' => $config['requires_restart'] ?? false,
                        'sort_order' => $config['sort_order'] ?? 0,
                    ]);
                }
            }
        }
    }

    /**
     * Validate setting value against rules.
     */
    public function validateValue($value): bool
    {
        if (!$this->validation_rules) {
            return true;
        }

        $validator = validator(['value' => $value], [
            'value' => $this->validation_rules
        ]);

        return !$validator->fails();
    }

    /**
     * Get setting configuration for frontend.
     */
    public function getConfigAttribute(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'options' => $this->options,
            'validation_rules' => $this->validation_rules,
            'is_public' => $this->is_public,
            'requires_restart' => $this->requires_restart,
            'value' => $this->is_encrypted ? '***ENCRYPTED***' : $this->value,
        ];
    }

    /**
     * Bulk update settings.
     */
    public static function bulkUpdate(array $settings): array
    {
        $results = [];
        
        foreach ($settings as $key => $value) {
            try {
                $setting = static::where('key', $key)->first();
                
                if ($setting) {
                    if ($setting->validateValue($value)) {
                        $setting->update(['value' => $value]);
                        $results[$key] = ['status' => 'success'];
                    } else {
                        $results[$key] = ['status' => 'error', 'message' => 'Validation failed'];
                    }
                } else {
                    $results[$key] = ['status' => 'error', 'message' => 'Setting not found'];
                }
            } catch (\Exception $e) {
                $results[$key] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Export settings configuration.
     */
    public static function exportConfig(): array
    {
        return static::where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($setting) {
                return [
                    'category' => $setting->category,
                    'key' => $setting->key,
                    'name' => $setting->name,
                    'description' => $setting->description,
                    'value' => $setting->value,
                    'type' => $setting->type,
                    'options' => $setting->options,
                    'validation_rules' => $setting->validation_rules,
                    'is_encrypted' => $setting->is_encrypted,
                    'is_public' => $setting->is_public,
                    'requires_restart' => $setting->requires_restart,
                    'sort_order' => $setting->sort_order,
                    'metadata' => $setting->metadata,
                ];
            })
            ->groupBy('category')
            ->toArray();
    }

    /**
     * Import settings configuration.
     */
    public static function importConfig(array $config): array
    {
        $results = ['success' => 0, 'errors' => []];

        foreach ($config as $category => $settings) {
            foreach ($settings as $setting) {
                try {
                    static::updateOrCreate(
                        ['key' => $setting['key']],
                        $setting
                    );
                    $results['success']++;
                } catch (\Exception $e) {
                    $results['errors'][] = "Failed to import {$setting['key']}: " . $e->getMessage();
                }
            }
        }

        return $results;
    }
}
