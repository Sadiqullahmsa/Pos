<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('customer_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('kyc_status')) {
            $query->where('kyc_status', $request->input('kyc_status'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('city')) {
            $query->whereJsonContains('address->city', $request->input('city'));
        }

        if ($request->filled('state')) {
            $query->whereJsonContains('address->state', $request->input('state'));
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->input('per_page', 15);
        $customers = $query->with(['activeConnection', 'orders' => function ($q) {
            $q->latest()->limit(5);
        }])->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers,
            'message' => 'Customers retrieved successfully'
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => 'required|string|min:10|max:15|unique:customers,phone',
            'alternate_phone' => 'nullable|string|min:10|max:15',
            'address' => 'required|array',
            'address.street' => 'required|string|max:255',
            'address.area' => 'required|string|max:255',
            'address.city' => 'required|string|max:255',
            'address.state' => 'required|string|max:255',
            'address.pincode' => 'required|string|max:10',
            'id_type' => 'required|in:aadhar,pan,driving_license,passport,voter_id',
            'id_number' => 'required|string|unique:customers,id_number',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'occupation' => 'nullable|string|max:255',
            'monthly_income' => 'nullable|numeric|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'preferred_delivery_time' => 'nullable|string|max:255',
            'preferences' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        try {
            $customer = Customer::create($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'Customer created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer): JsonResponse
    {
        $customer->load([
            'connections.orders' => function ($q) {
                $q->latest()->limit(10);
            },
            'payments' => function ($q) {
                $q->latest()->limit(10);
            },
            'complaints' => function ($q) {
                $q->latest()->limit(5);
            }
        ]);

        return response()->json([
            'success' => true,
            'data' => $customer,
            'message' => 'Customer retrieved successfully'
        ]);
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'nullable',
                'email',
                Rule::unique('customers')->ignore($customer->id)
            ],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'min:10',
                'max:15',
                Rule::unique('customers')->ignore($customer->id)
            ],
            'alternate_phone' => 'sometimes|nullable|string|min:10|max:15',
            'address' => 'sometimes|required|array',
            'address.street' => 'required_with:address|string|max:255',
            'address.area' => 'required_with:address|string|max:255',
            'address.city' => 'required_with:address|string|max:255',
            'address.state' => 'required_with:address|string|max:255',
            'address.pincode' => 'required_with:address|string|max:10',
            'id_type' => 'sometimes|required|in:aadhar,pan,driving_license,passport,voter_id',
            'id_number' => [
                'sometimes',
                'required',
                'string',
                Rule::unique('customers')->ignore($customer->id)
            ],
            'date_of_birth' => 'sometimes|nullable|date|before:today',
            'gender' => 'sometimes|nullable|in:male,female,other',
            'occupation' => 'sometimes|nullable|string|max:255',
            'monthly_income' => 'sometimes|nullable|numeric|min:0',
            'credit_limit' => 'sometimes|nullable|numeric|min:0',
            'preferred_delivery_time' => 'sometimes|nullable|string|max:255',
            'preferences' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        try {
            $customer->update($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'Customer updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        try {
            // Check if customer has active orders
            if ($customer->orders()->whereIn('status', ['pending', 'confirmed', 'processing', 'dispatched'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete customer with active orders'
                ], 400);
            }

            // Soft delete the customer
            $customer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload documents for customer.
     */
    public function uploadDocuments(Request $request, Customer $customer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_document' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'photo' => 'sometimes|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        try {
            $updates = [];

            if ($request->hasFile('id_document')) {
                // Delete old document if exists
                if ($customer->id_document_path) {
                    Storage::disk('public')->delete($customer->id_document_path);
                }

                $path = $request->file('id_document')->store('customer_documents', 'public');
                $updates['id_document_path'] = $path;
            }

            if ($request->hasFile('photo')) {
                // Delete old photo if exists
                if ($customer->photo_path) {
                    Storage::disk('public')->delete($customer->photo_path);
                }

                $path = $request->file('photo')->store('customer_photos', 'public');
                $updates['photo_path'] = $path;
            }

            $customer->update($updates);

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'Documents uploaded successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload documents: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update KYC status for customer.
     */
    public function updateKycStatus(Request $request, Customer $customer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'kyc_status' => 'required|in:pending,verified,rejected',
            'kyc_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        try {
            $customer->update([
                'kyc_status' => $request->input('kyc_status'),
                'kyc_notes' => $request->input('kyc_notes'),
            ]);

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'KYC status updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update KYC status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get orders for customer.
     */
    public function getOrders(Request $request, Customer $customer): JsonResponse
    {
        $query = $customer->orders()->with(['connection', 'payments', 'delivery']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $orders = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $orders,
            'message' => 'Customer orders retrieved successfully'
        ]);
    }

    /**
     * Get connections for customer.
     */
    public function getConnections(Request $request, Customer $customer): JsonResponse
    {
        $connections = $customer->connections()
            ->with(['orders' => function ($q) {
                $q->latest()->limit(5);
            }])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $connections,
            'message' => 'Customer connections retrieved successfully'
        ]);
    }
}
