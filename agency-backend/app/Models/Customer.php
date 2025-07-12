<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Customer extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'name',
        'email',
        'phone',
        'alternate_phone',
        'address',
        'id_type',
        'id_number',
        'id_document_path',
        'kyc_status',
        'kyc_notes',
        'date_of_birth',
        'gender',
        'occupation',
        'monthly_income',
        'photo_path',
        'is_active',
        'credit_limit',
        'outstanding_balance',
        'preferred_delivery_time',
        'preferences',
        'last_order_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'address' => 'array',
        'preferences' => 'array',
        'date_of_birth' => 'date',
        'last_order_date' => 'datetime',
        'is_active' => 'boolean',
        'credit_limit' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'monthly_income' => 'decimal:2',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'id_document_path',
        'kyc_notes',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'customer_id';
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            if (!$customer->customer_id) {
                $customer->customer_id = 'CUST' . str_pad(
                    (Customer::withTrashed()->count() + 1),
                    6,
                    '0',
                    STR_PAD_LEFT
                );
            }
        });
    }

    /**
     * Get all connections for this customer.
     */
    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }

    /**
     * Get all orders for this customer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get all payments for this customer.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get all complaints for this customer.
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    /**
     * Get the active connection for this customer.
     */
    public function activeConnection(): HasOne
    {
        return $this->hasOne(Connection::class)->where('status', 'active');
    }

    /**
     * Get the primary connection for this customer.
     */
    public function primaryConnection(): HasOne
    {
        return $this->hasOne(Connection::class)->oldest();
    }

    /**
     * Scope to get verified customers.
     */
    public function scopeVerified($query)
    {
        return $query->where('kyc_status', 'verified');
    }

    /**
     * Scope to get active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get customers with outstanding balance.
     */
    public function scopeWithOutstandingBalance($query)
    {
        return $query->where('outstanding_balance', '>', 0);
    }

    /**
     * Get the formatted address.
     */
    public function getFormattedAddressAttribute(): string
    {
        if (!$this->address) return '';

        $address = $this->address;
        return implode(', ', array_filter([
            $address['street'] ?? '',
            $address['area'] ?? '',
            $address['city'] ?? '',
            $address['state'] ?? '',
            $address['pincode'] ?? '',
        ]));
    }

    /**
     * Get the full name with title.
     */
    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Get the KYC status badge.
     */
    public function getKycStatusBadgeAttribute(): array
    {
        $statuses = [
            'pending' => ['color' => 'warning', 'text' => 'Pending'],
            'verified' => ['color' => 'success', 'text' => 'Verified'],
            'rejected' => ['color' => 'danger', 'text' => 'Rejected'],
        ];

        return $statuses[$this->kyc_status] ?? ['color' => 'secondary', 'text' => 'Unknown'];
    }

    /**
     * Get the customer's age.
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->date_of_birth) return null;

        return $this->date_of_birth->age;
    }

    /**
     * Get the customer's total orders count.
     */
    public function getTotalOrdersAttribute(): int
    {
        return $this->orders()->count();
    }

    /**
     * Get the customer's total spent amount.
     */
    public function getTotalSpentAttribute(): float
    {
        return $this->orders()->where('status', 'delivered')->sum('final_amount');
    }

    /**
     * Get the customer's last order.
     */
    public function getLastOrderAttribute(): ?Order
    {
        return $this->orders()->latest()->first();
    }

    /**
     * Check if customer is eligible for new order.
     */
    public function isEligibleForOrder(): bool
    {
        if (!$this->is_active) return false;
        if ($this->kyc_status !== 'verified') return false;
        if ($this->outstanding_balance > $this->credit_limit) return false;

        return true;
    }

    /**
     * Update customer's outstanding balance.
     */
    public function updateOutstandingBalance(float $amount): self
    {
        $this->outstanding_balance += $amount;
        $this->save();

        return $this;
    }

    /**
     * Get activity log options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'kyc_status', 'is_active', 'outstanding_balance'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the customer's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name . ' (' . $this->customer_id . ')';
    }
}
