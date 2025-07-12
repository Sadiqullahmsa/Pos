<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Template;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Invoice::with(['customer', 'order', 'currency'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

            if ($request->has('date_from')) {
                $query->whereDate('invoice_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('invoice_date', '<=', $request->date_to);
            }

            if ($request->has('amount_min')) {
                $query->where('total_amount', '>=', $request->amount_min);
            }

            if ($request->has('amount_max')) {
                $query->where('total_amount', '<=', $request->amount_max);
            }

            if ($request->has('currency_code')) {
                $query->whereHas('currency', function($q) use ($request) {
                    $q->where('code', $request->currency_code);
                });
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                      ->orWhere('reference_number', 'like', "%{$search}%")
                      ->orWhereHas('customer', function($cq) use ($search) {
                          $cq->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $invoices = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $invoices,
                'summary' => [
                    'total_invoices' => Invoice::count(),
                    'total_amount' => Invoice::sum('total_amount'),
                    'paid_amount' => Invoice::where('status', 'paid')->sum('total_amount'),
                    'pending_amount' => Invoice::where('status', 'pending')->sum('total_amount'),
                    'overdue_amount' => Invoice::where('status', 'overdue')->sum('total_amount'),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Invoice listing failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoices'
            ], 500);
        }
    }

    /**
     * Store a newly created invoice
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'order_id' => 'nullable|exists:orders,id',
                'invoice_date' => 'required|date',
                'due_date' => 'required|date|after_or_equal:invoice_date',
                'currency_id' => 'required|exists:currencies,id',
                'template_id' => 'nullable|exists:templates,id',
                'items' => 'required|array|min:1',
                'items.*.description' => 'required|string|max:255',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
                'items.*.discount_rate' => 'nullable|numeric|min:0|max:100',
                'discount_amount' => 'nullable|numeric|min:0',
                'tax_amount' => 'nullable|numeric|min:0',
                'shipping_amount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
                'terms_and_conditions' => 'nullable|string|max:2000',
                'is_recurring' => 'boolean',
                'recurring_interval' => 'nullable|string|in:daily,weekly,monthly,yearly',
                'recurring_count' => 'nullable|integer|min:1',
                'send_email' => 'boolean',
                'auto_reminder' => 'boolean',
                'reminder_days' => 'nullable|integer|min:1',
            ]);

            DB::beginTransaction();

            $customer = Customer::findOrFail($request->customer_id);
            $currency = Currency::findOrFail($request->currency_id);

            // Calculate totals
            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;
            $processedItems = [];

            foreach ($request->items as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $itemDiscount = $itemTotal * (($item['discount_rate'] ?? 0) / 100);
                $itemTaxable = $itemTotal - $itemDiscount;
                $itemTax = $itemTaxable * (($item['tax_rate'] ?? 0) / 100);

                $processedItems[] = [
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'discount_rate' => $item['discount_rate'] ?? 0,
                    'line_total' => $itemTotal,
                    'discount_amount' => $itemDiscount,
                    'tax_amount' => $itemTax,
                    'final_amount' => $itemTotal - $itemDiscount + $itemTax,
                ];

                $subtotal += $itemTotal;
                $totalTax += $itemTax;
                $totalDiscount += $itemDiscount;
            }

            $additionalDiscount = $request->discount_amount ?? 0;
            $additionalTax = $request->tax_amount ?? 0;
            $shippingAmount = $request->shipping_amount ?? 0;

            $totalAmount = $subtotal - $totalDiscount - $additionalDiscount + $totalTax + $additionalTax + $shippingAmount;

            $invoice = Invoice::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'customer_id' => $request->customer_id,
                'order_id' => $request->order_id,
                'currency_id' => $request->currency_id,
                'template_id' => $request->template_id,
                'invoice_date' => $request->invoice_date,
                'due_date' => $request->due_date,
                'status' => 'pending',
                'items' => $processedItems,
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount + $additionalDiscount,
                'tax_amount' => $totalTax + $additionalTax,
                'shipping_amount' => $shippingAmount,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
                'terms_and_conditions' => $request->terms_and_conditions,
                'is_recurring' => $request->is_recurring ?? false,
                'recurring_interval' => $request->recurring_interval,
                'recurring_count' => $request->recurring_count,
                'auto_reminder' => $request->auto_reminder ?? false,
                'reminder_days' => $request->reminder_days,
                'created_by' => Auth::id(),
                'metadata' => [
                    'created_source' => 'web',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);

            // Generate PDF
            $pdfPath = $this->generateAndStorePDF($invoice);
            $invoice->update(['pdf_path' => $pdfPath]);

            // Send email if requested
            if ($request->send_email) {
                $this->sendInvoiceEmail($invoice);
            }

            // Set up recurring invoice if applicable
            if ($request->is_recurring) {
                $this->setupRecurringInvoice($invoice);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully',
                'data' => $invoice->load(['customer', 'currency'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Invoice creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create invoice'
            ], 500);
        }
    }

    /**
     * Display the specified invoice
     */
    public function show(Invoice $invoice): JsonResponse
    {
        try {
            $invoice->load(['customer', 'order', 'currency', 'template']);
            
            return response()->json([
                'success' => true,
                'data' => $invoice,
                'payment_history' => $invoice->payment_history ?? [],
                'download_links' => [
                    'pdf' => route('invoices.pdf', $invoice->id),
                    'thermal' => route('invoices.thermal', $invoice->id),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Invoice retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoice'
            ], 500);
        }
    }

    /**
     * Update the specified invoice
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update paid invoice'
                ], 400);
            }

            $request->validate([
                'customer_id' => 'sometimes|exists:customers,id',
                'order_id' => 'nullable|exists:orders,id',
                'invoice_date' => 'sometimes|date',
                'due_date' => 'sometimes|date|after_or_equal:invoice_date',
                'currency_id' => 'sometimes|exists:currencies,id',
                'template_id' => 'nullable|exists:templates,id',
                'items' => 'sometimes|array|min:1',
                'items.*.description' => 'required|string|max:255',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
                'items.*.discount_rate' => 'nullable|numeric|min:0|max:100',
                'discount_amount' => 'nullable|numeric|min:0',
                'tax_amount' => 'nullable|numeric|min:0',
                'shipping_amount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
                'terms_and_conditions' => 'nullable|string|max:2000',
            ]);

            // Recalculate totals if items are updated
            if ($request->has('items')) {
                $subtotal = 0;
                $totalTax = 0;
                $totalDiscount = 0;
                $processedItems = [];

                foreach ($request->items as $item) {
                    $itemTotal = $item['quantity'] * $item['unit_price'];
                    $itemDiscount = $itemTotal * (($item['discount_rate'] ?? 0) / 100);
                    $itemTaxable = $itemTotal - $itemDiscount;
                    $itemTax = $itemTaxable * (($item['tax_rate'] ?? 0) / 100);

                    $processedItems[] = [
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'tax_rate' => $item['tax_rate'] ?? 0,
                        'discount_rate' => $item['discount_rate'] ?? 0,
                        'line_total' => $itemTotal,
                        'discount_amount' => $itemDiscount,
                        'tax_amount' => $itemTax,
                        'final_amount' => $itemTotal - $itemDiscount + $itemTax,
                    ];

                    $subtotal += $itemTotal;
                    $totalTax += $itemTax;
                    $totalDiscount += $itemDiscount;
                }

                $additionalDiscount = $request->discount_amount ?? $invoice->discount_amount;
                $additionalTax = $request->tax_amount ?? $invoice->tax_amount;
                $shippingAmount = $request->shipping_amount ?? $invoice->shipping_amount;

                $totalAmount = $subtotal - $totalDiscount - $additionalDiscount + $totalTax + $additionalTax + $shippingAmount;

                $invoice->update([
                    'items' => $processedItems,
                    'subtotal' => $subtotal,
                    'discount_amount' => $totalDiscount + $additionalDiscount,
                    'tax_amount' => $totalTax + $additionalTax,
                    'shipping_amount' => $shippingAmount,
                    'total_amount' => $totalAmount,
                ]);
            }

            $invoice->update($request->except(['items']));

            // Regenerate PDF if content changed
            if ($request->has('items') || $request->has('notes') || $request->has('terms_and_conditions')) {
                $pdfPath = $this->generateAndStorePDF($invoice);
                $invoice->update(['pdf_path' => $pdfPath]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoice updated successfully',
                'data' => $invoice->load(['customer', 'currency'])
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update invoice'
            ], 500);
        }
    }

    /**
     * Remove the specified invoice
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        try {
            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete paid invoice'
                ], 400);
            }

            // Delete PDF file if exists
            if ($invoice->pdf_path && Storage::disk('public')->exists($invoice->pdf_path)) {
                Storage::disk('public')->delete($invoice->pdf_path);
            }

            $invoice->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Invoice deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice deletion failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete invoice'
            ], 500);
        }
    }

    /**
     * Update invoice status
     */
    public function updateStatus(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:pending,sent,viewed,paid,overdue,cancelled,refunded',
                'notes' => 'nullable|string|max:1000',
            ]);

            $oldStatus = $invoice->status;
            $invoice->update([
                'status' => $request->status,
                'status_updated_at' => now(),
                'status_updated_by' => Auth::id(),
            ]);

            // Handle status-specific actions
            if ($request->status === 'paid') {
                $invoice->update(['paid_at' => now()]);
            }

            // Log status change
            $this->logStatusChange($invoice, $oldStatus, $request->status, $request->notes);

            return response()->json([
                'success' => true,
                'message' => 'Invoice status updated successfully',
                'data' => $invoice
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice status update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update invoice status'
            ], 500);
        }
    }

    /**
     * Send invoice via email
     */
    public function sendInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'nullable|email',
                'subject' => 'nullable|string|max:255',
                'message' => 'nullable|string|max:1000',
                'send_copy' => 'boolean',
            ]);

            $this->sendInvoiceEmail($invoice, $request->all());

            $invoice->update([
                'status' => 'sent',
                'sent_at' => now(),
                'sent_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice sent successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice sending failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invoice'
            ], 500);
        }
    }

    /**
     * Record payment for invoice
     */
    public function recordPayment(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|string|max:50',
                'payment_date' => 'required|date',
                'transaction_id' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($request->amount > ($invoice->total_amount - $invoice->paid_amount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds remaining balance'
                ], 400);
            }

            $paymentHistory = $invoice->payment_history ?? [];
            $paymentHistory[] = [
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'transaction_id' => $request->transaction_id,
                'notes' => $request->notes,
                'recorded_by' => Auth::id(),
                'recorded_at' => now(),
            ];

            $newPaidAmount = $invoice->paid_amount + $request->amount;
            $newStatus = $newPaidAmount >= $invoice->total_amount ? 'paid' : 'partially_paid';

            $invoice->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newStatus,
                'payment_history' => $paymentHistory,
                'last_payment_at' => $request->payment_date,
            ]);

            if ($newStatus === 'paid') {
                $invoice->update(['paid_at' => now()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => $invoice
            ]);

        } catch (\Exception $e) {
            Log::error('Payment recording failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment'
            ], 500);
        }
    }

    /**
     * Generate PDF for invoice download
     */
    public function generatePdf(Invoice $invoice)
    {
        try {
            $invoice->load(['customer', 'currency']);
            
            $template = $invoice->template ?? Template::where('type', 'invoice')->where('is_default', true)->first();
            
            $html = $this->renderInvoiceHtml($invoice, $template, 'a4');
            
            $pdf = PDF::loadHTML($html);
            $pdf->setPaper('A4', 'portrait');
            
            return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
        } catch (\Exception $e) {
            Log::error('PDF generation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF'
            ], 500);
        }
    }

    /**
     * Generate thermal print for invoice
     */
    public function generateThermalPrint(Invoice $invoice)
    {
        try {
            $invoice->load(['customer', 'currency']);
            
            $template = Template::where('type', 'invoice_thermal')->where('is_default', true)->first();
            
            $html = $this->renderInvoiceHtml($invoice, $template, 'thermal');
            
            $pdf = PDF::loadHTML($html);
            $pdf->setPaper([0, 0, 226.77, 841.89], 'portrait'); // 80mm thermal paper
            
            return $pdf->download("invoice-thermal-{$invoice->invoice_number}.pdf");
        } catch (\Exception $e) {
            Log::error('Thermal print generation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate thermal print'
            ], 500);
        }
    }

    /**
     * Duplicate invoice
     */
    public function duplicateInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            $request->validate([
                'invoice_date' => 'required|date',
                'due_date' => 'required|date|after_or_equal:invoice_date',
                'customer_id' => 'nullable|exists:customers,id',
            ]);

            $duplicateData = $invoice->toArray();
            unset($duplicateData['id'], $duplicateData['invoice_number'], $duplicateData['created_at'], $duplicateData['updated_at']);

            $duplicateData['invoice_number'] = $this->generateInvoiceNumber();
            $duplicateData['invoice_date'] = $request->invoice_date;
            $duplicateData['due_date'] = $request->due_date;
            $duplicateData['customer_id'] = $request->customer_id ?? $invoice->customer_id;
            $duplicateData['status'] = 'pending';
            $duplicateData['paid_amount'] = 0;
            $duplicateData['payment_history'] = [];
            $duplicateData['created_by'] = Auth::id();

            $duplicate = Invoice::create($duplicateData);

            // Generate PDF for duplicate
            $pdfPath = $this->generateAndStorePDF($duplicate);
            $duplicate->update(['pdf_path' => $pdfPath]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice duplicated successfully',
                'data' => $duplicate->load(['customer', 'currency'])
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice duplication failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate invoice'
            ], 500);
        }
    }

    /**
     * Get invoice templates
     */
    public function getTemplates(): JsonResponse
    {
        try {
            $templates = Template::where('type', 'invoice')
                ->orWhere('type', 'invoice_thermal')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);
        } catch (\Exception $e) {
            Log::error('Template retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates'
            ], 500);
        }
    }

    /**
     * Create invoice template
     */
    public function createTemplate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|string|in:invoice,invoice_thermal',
                'content' => 'required|string',
                'is_default' => 'boolean',
            ]);

            if ($request->is_default) {
                Template::where('type', $request->type)->update(['is_default' => false]);
            }

            $template = Template::create([
                'name' => $request->name,
                'type' => $request->type,
                'content' => $request->content,
                'is_default' => $request->is_default ?? false,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => $template
            ]);

        } catch (\Exception $e) {
            Log::error('Template creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template'
            ], 500);
        }
    }

    /**
     * Get next invoice number
     */
    public function getNextInvoiceNumber(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['next_number' => $this->generateInvoiceNumber()]
        ]);
    }

    /**
     * Bulk generate invoices
     */
    public function bulkGenerate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'invoices' => 'required|array|min:1',
                'invoices.*.customer_id' => 'required|exists:customers,id',
                'invoices.*.order_id' => 'nullable|exists:orders,id',
                'invoices.*.amount' => 'required|numeric|min:0.01',
                'invoices.*.currency_id' => 'required|exists:currencies,id',
                'invoice_date' => 'required|date',
                'due_date' => 'required|date|after_or_equal:invoice_date',
                'send_email' => 'boolean',
            ]);

            $generated = [];
            DB::beginTransaction();

            foreach ($request->invoices as $invoiceData) {
                $invoice = Invoice::create([
                    'invoice_number' => $this->generateInvoiceNumber(),
                    'customer_id' => $invoiceData['customer_id'],
                    'order_id' => $invoiceData['order_id'] ?? null,
                    'currency_id' => $invoiceData['currency_id'],
                    'invoice_date' => $request->invoice_date,
                    'due_date' => $request->due_date,
                    'total_amount' => $invoiceData['amount'],
                    'status' => 'pending',
                    'created_by' => Auth::id(),
                ]);

                $generated[] = $invoice;

                if ($request->send_email) {
                    $this->sendInvoiceEmail($invoice);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Invoices generated successfully',
                'data' => ['generated_count' => count($generated)]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk invoice generation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate invoices'
            ], 500);
        }
    }

    /**
     * Get invoice analytics
     */
    public function getAnalytics(): JsonResponse
    {
        try {
            $analytics = [
                'total_invoices' => Invoice::count(),
                'total_amount' => Invoice::sum('total_amount'),
                'paid_amount' => Invoice::sum('paid_amount'),
                'pending_amount' => Invoice::where('status', 'pending')->sum('total_amount'),
                'overdue_amount' => Invoice::where('status', 'overdue')->sum('total_amount'),
                'average_invoice_value' => Invoice::avg('total_amount'),
                'payment_rate' => Invoice::where('status', 'paid')->count() / max(Invoice::count(), 1) * 100,
                'status_breakdown' => Invoice::groupBy('status')->selectRaw('status, count(*) as count, sum(total_amount) as amount')->get(),
                'monthly_trends' => Invoice::selectRaw('DATE_FORMAT(invoice_date, "%Y-%m") as month, count(*) as count, sum(total_amount) as amount')
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get(),
                'top_customers' => Invoice::with('customer')
                    ->selectRaw('customer_id, count(*) as invoice_count, sum(total_amount) as total_amount')
                    ->groupBy('customer_id')
                    ->orderBy('total_amount', 'desc')
                    ->limit(10)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);
        } catch (\Exception $e) {
            Log::error('Invoice analytics failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve analytics'
            ], 500);
        }
    }

    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Y') . '-';
        $lastInvoice = Invoice::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = intval(substr($lastInvoice->invoice_number, strlen($prefix)));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate PDF and store
     */
    private function generateAndStorePDF(Invoice $invoice): string
    {
        $invoice->load(['customer', 'currency']);
        
        $template = $invoice->template ?? Template::where('type', 'invoice')->where('is_default', true)->first();
        
        $html = $this->renderInvoiceHtml($invoice, $template, 'a4');
        
        $pdf = PDF::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        
        $filename = "invoice-{$invoice->invoice_number}.pdf";
        $path = "invoices/{$filename}";
        
        Storage::disk('public')->put($path, $pdf->output());
        
        return $path;
    }

    /**
     * Render invoice HTML
     */
    private function renderInvoiceHtml(Invoice $invoice, $template, string $format): string
    {
        if ($template && $template->content) {
            $html = $template->content;
        } else {
            $html = $this->getDefaultInvoiceTemplate($format);
        }

        // Replace placeholders
        $replacements = [
            '{{invoice_number}}' => $invoice->invoice_number,
            '{{invoice_date}}' => $invoice->invoice_date->format('Y-m-d'),
            '{{due_date}}' => $invoice->due_date->format('Y-m-d'),
            '{{customer_name}}' => $invoice->customer->name,
            '{{customer_address}}' => $invoice->customer->address,
            '{{customer_email}}' => $invoice->customer->email,
            '{{customer_phone}}' => $invoice->customer->phone,
            '{{subtotal}}' => number_format($invoice->subtotal, 2),
            '{{discount}}' => number_format($invoice->discount_amount, 2),
            '{{tax}}' => number_format($invoice->tax_amount, 2),
            '{{shipping}}' => number_format($invoice->shipping_amount, 2),
            '{{total}}' => number_format($invoice->total_amount, 2),
            '{{currency}}' => $invoice->currency->symbol,
            '{{notes}}' => $invoice->notes ?? '',
            '{{terms}}' => $invoice->terms_and_conditions ?? '',
        ];

        // Add items
        $itemsHtml = '';
        foreach ($invoice->items as $item) {
            $itemsHtml .= "<tr>";
            $itemsHtml .= "<td>{$item['description']}</td>";
            $itemsHtml .= "<td>{$item['quantity']}</td>";
            $itemsHtml .= "<td>" . number_format($item['unit_price'], 2) . "</td>";
            $itemsHtml .= "<td>" . number_format($item['final_amount'], 2) . "</td>";
            $itemsHtml .= "</tr>";
        }
        $replacements['{{items}}'] = $itemsHtml;

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    /**
     * Get default invoice template
     */
    private function getDefaultInvoiceTemplate(string $format): string
    {
        if ($format === 'thermal') {
            return view('invoices.thermal-template')->render();
        }
        
        return view('invoices.a4-template')->render();
    }

    /**
     * Send invoice email
     */
    private function sendInvoiceEmail(Invoice $invoice, array $options = []): void
    {
        // Implementation for sending invoice email
        // This would integrate with mail services
    }

    /**
     * Setup recurring invoice
     */
    private function setupRecurringInvoice(Invoice $invoice): void
    {
        // Implementation for setting up recurring invoice
        // This would create scheduled jobs for recurring invoices
    }

    /**
     * Log status change
     */
    private function logStatusChange(Invoice $invoice, string $oldStatus, string $newStatus, ?string $notes): void
    {
        $statusHistory = $invoice->status_history ?? [];
        $statusHistory[] = [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'changed_by' => Auth::id(),
            'changed_at' => now(),
        ];

        $invoice->update(['status_history' => $statusHistory]);
    }
}
