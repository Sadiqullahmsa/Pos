<?php

namespace App\Http\Controllers;

use App\Models\Emergency;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class EmergencyController extends Controller
{
    /**
     * Display a listing of emergencies
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Emergency::with(['reporter', 'assignedTo', 'responseTeam'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $emergencies = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $emergencies,
                'summary' => [
                    'total' => Emergency::count(),
                    'active' => Emergency::whereIn('status', ['reported', 'acknowledged', 'responding'])->count(),
                    'resolved' => Emergency::where('status', 'resolved')->count(),
                    'closed' => Emergency::where('status', 'closed')->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Emergency listing failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve emergencies'
            ], 500);
        }
    }

    /**
     * Store a newly created emergency
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|string|in:gas_leak,fire,explosion,equipment_failure,safety_incident,medical_emergency,natural_disaster,security_breach,other',
                'priority' => 'required|string|in:low,medium,high,critical',
                'location' => 'required|string|max:255',
                'description' => 'required|string|max:2000',
                'contact_info' => 'required|string|max:500',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'affected_area' => 'nullable|string|max:500',
                'estimated_casualties' => 'nullable|integer|min:0',
                'environmental_impact' => 'nullable|string|max:1000',
                'immediate_actions_taken' => 'nullable|string|max:1000',
                'resources_required' => 'nullable|string|max:1000',
                'witnesses' => 'nullable|array',
                'witnesses.*.name' => 'required|string|max:255',
                'witnesses.*.contact' => 'required|string|max:255',
                'witnesses.*.statement' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $emergency = Emergency::create([
                'emergency_id' => $this->generateEmergencyId(),
                'type' => $request->type,
                'priority' => $request->priority,
                'status' => 'reported',
                'location' => $request->location,
                'description' => $request->description,
                'contact_info' => $request->contact_info,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'affected_area' => $request->affected_area,
                'estimated_casualties' => $request->estimated_casualties,
                'environmental_impact' => $request->environmental_impact,
                'immediate_actions_taken' => $request->immediate_actions_taken,
                'resources_required' => $request->resources_required,
                'witnesses' => $request->witnesses,
                'reporter_id' => Auth::id(),
                'reported_at' => now(),
                'escalation_level' => 1,
                'response_sla' => $this->calculateResponseSLA($request->priority),
                'resolution_sla' => $this->calculateResolutionSLA($request->priority),
                'compliance_requirements' => $this->getComplianceRequirements($request->type),
                'metadata' => [
                    'source' => 'web',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_by' => Auth::user()->name,
                ]
            ]);

            // Auto-assign based on priority and type
            $this->autoAssignEmergency($emergency);

            // Send immediate notifications
            $this->sendEmergencyNotifications($emergency);

            // Log timeline event
            $this->logTimelineEvent($emergency, 'created', 'Emergency reported');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Emergency reported successfully',
                'data' => $emergency->load(['reporter', 'assignedTo'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Emergency creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to report emergency'
            ], 500);
        }
    }

    /**
     * Display the specified emergency
     */
    public function show(Emergency $emergency): JsonResponse
    {
        try {
            $emergency->load(['reporter', 'assignedTo', 'responseTeam']);
            
            return response()->json([
                'success' => true,
                'data' => $emergency,
                'timeline' => $emergency->timeline ?? [],
                'evidence' => $emergency->evidence ?? [],
                'response_metrics' => $this->calculateResponseMetrics($emergency),
            ]);
        } catch (\Exception $e) {
            Log::error('Emergency retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve emergency details'
            ], 500);
        }
    }

    /**
     * Update the specified emergency
     */
    public function update(Request $request, Emergency $emergency): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'sometimes|string|in:gas_leak,fire,explosion,equipment_failure,safety_incident,medical_emergency,natural_disaster,security_breach,other',
                'priority' => 'sometimes|string|in:low,medium,high,critical',
                'location' => 'sometimes|string|max:255',
                'description' => 'sometimes|string|max:2000',
                'contact_info' => 'sometimes|string|max:500',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'affected_area' => 'nullable|string|max:500',
                'estimated_casualties' => 'nullable|integer|min:0',
                'environmental_impact' => 'nullable|string|max:1000',
                'immediate_actions_taken' => 'nullable|string|max:1000',
                'resources_required' => 'nullable|string|max:1000',
                'witnesses' => 'nullable|array',
                'response_actions' => 'nullable|string|max:2000',
                'current_status_details' => 'nullable|string|max:1000',
            ]);

            $oldData = $emergency->toArray();
            $emergency->update($request->all());

            // Log changes
            $this->logTimelineEvent($emergency, 'updated', 'Emergency details updated', [
                'changes' => array_diff_assoc($request->all(), $oldData),
                'updated_by' => Auth::user()->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Emergency updated successfully',
                'data' => $emergency->load(['reporter', 'assignedTo'])
            ]);

        } catch (\Exception $e) {
            Log::error('Emergency update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update emergency'
            ], 500);
        }
    }

    /**
     * Remove the specified emergency
     */
    public function destroy(Emergency $emergency): JsonResponse
    {
        try {
            if ($emergency->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete active emergency'
                ], 400);
            }

            $emergency->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Emergency deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Emergency deletion failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete emergency'
            ], 500);
        }
    }

    /**
     * Update emergency status
     */
    public function updateStatus(Request $request, Emergency $emergency): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:reported,acknowledged,responding,resolved,closed',
                'notes' => 'nullable|string|max:1000',
            ]);

            $oldStatus = $emergency->status;
            $emergency->update([
                'status' => $request->status,
                'status_updated_at' => now(),
                'status_updated_by' => Auth::id(),
            ]);

            // Log status change
            $this->logTimelineEvent($emergency, 'status_changed', 
                "Status changed from {$oldStatus} to {$request->status}",
                ['notes' => $request->notes]
            );

            // Handle status-specific actions
            $this->handleStatusChange($emergency, $oldStatus, $request->status);

            return response()->json([
                'success' => true,
                'message' => 'Emergency status updated successfully',
                'data' => $emergency
            ]);

        } catch (\Exception $e) {
            Log::error('Emergency status update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update emergency status'
            ], 500);
        }
    }

    /**
     * Acknowledge emergency
     */
    public function acknowledge(Request $request, Emergency $emergency): JsonResponse
    {
        try {
            $request->validate([
                'notes' => 'nullable|string|max:1000',
                'estimated_response_time' => 'nullable|integer|min:1',
            ]);

            $emergency->update([
                'status' => 'acknowledged',
                'acknowledged_at' => now(),
                'acknowledged_by' => Auth::id(),
                'assigned_to' => Auth::id(),
                'estimated_response_time' => $request->estimated_response_time,
            ]);

            $this->logTimelineEvent($emergency, 'acknowledged', 
                'Emergency acknowledged and assigned',
                ['notes' => $request->notes]
            );

            return response()->json([
                'success' => true,
                'message' => 'Emergency acknowledged successfully',
                'data' => $emergency
            ]);

        } catch (\Exception $e) {
            Log::error('Emergency acknowledgment failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to acknowledge emergency'
            ], 500);
        }
    }

    /**
     * Escalate emergency
     */
    public function escalate(Request $request, Emergency $emergency): JsonResponse
    {
        try {
            $request->validate([
                'escalation_reason' => 'required|string|max:1000',
                'escalate_to' => 'nullable|exists:users,id',
                'notify_authorities' => 'boolean',
            ]);

            $emergency->update([
                'escalation_level' => $emergency->escalation_level + 1,
                'escalated_at' => now(),
                'escalated_by' => Auth::id(),
                'escalation_reason' => $request->escalation_reason,
                'priority' => $this->escalatePriority($emergency->priority),
            ]);

            if ($request->escalate_to) {
                $emergency->update(['assigned_to' => $request->escalate_to]);
            }

            $this->logTimelineEvent($emergency, 'escalated', 
                "Emergency escalated to level {$emergency->escalation_level}",
                ['reason' => $request->escalation_reason]
            );

            // Notify authorities if required
            if ($request->notify_authorities) {
                $this->notifyAuthorities($emergency);
            }

            return response()->json([
                'success' => true,
                'message' => 'Emergency escalated successfully',
                'data' => $emergency
            ]);

        } catch (\Exception $e) {
            Log::error('Emergency escalation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to escalate emergency'
            ], 500);
        }
    }

    /**
     * Resolve emergency
     */
    public function resolve(Request $request, Emergency $emergency): JsonResponse
    {
        try {
            $request->validate([
                'resolution_summary' => 'required|string|max:2000',
                'actions_taken' => 'required|string|max:2000',
                'root_cause' => 'nullable|string|max:1000',
                'preventive_measures' => 'nullable|string|max:1000',
                'lessons_learned' => 'nullable|string|max:1000',
                'follow_up_required' => 'boolean',
                'follow_up_details' => 'nullable|string|max:1000',
            ]);

            $emergency->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => Auth::id(),
                'resolution_summary' => $request->resolution_summary,
                'actions_taken' => $request->actions_taken,
                'root_cause' => $request->root_cause,
                'preventive_measures' => $request->preventive_measures,
                'lessons_learned' => $request->lessons_learned,
                'follow_up_required' => $request->follow_up_required,
                'follow_up_details' => $request->follow_up_details,
                'total_response_time' => $this->calculateResponseTime($emergency),
            ]);

            $this->logTimelineEvent($emergency, 'resolved', 
                'Emergency resolved',
                ['resolution_summary' => $request->resolution_summary]
            );

            return response()->json([
                'success' => true,
                'message' => 'Emergency resolved successfully',
                'data' => $emergency
            ]);

        } catch (\Exception $e) {
            Log::error('Emergency resolution failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve emergency'
            ], 500);
        }
    }

    /**
     * Close emergency
     */
    public function close(Request $request, Emergency $emergency): JsonResponse
    {
        try {
            $request->validate([
                'closure_notes' => 'required|string|max:1000',
                'satisfaction_rating' => 'nullable|integer|min:1|max:5',
                'compliance_verified' => 'boolean',
            ]);

            $emergency->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => Auth::id(),
                'closure_notes' => $request->closure_notes,
                'satisfaction_rating' => $request->satisfaction_rating,
                'compliance_verified' => $request->compliance_verified,
            ]);

            $this->logTimelineEvent($emergency, 'closed', 
                'Emergency closed',
                ['closure_notes' => $request->closure_notes]
            );

            return response()->json([
                'success' => true,
                'message' => 'Emergency closed successfully',
                'data' => $emergency
            ]);

        } catch (\Exception $e) {
            Log::error('Emergency closure failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to close emergency'
            ], 500);
        }
    }

    /**
     * Get emergency timeline
     */
    public function getTimeline(Emergency $emergency): JsonResponse
    {
        try {
            $timeline = $emergency->timeline ?? [];
            
            return response()->json([
                'success' => true,
                'data' => $timeline
            ]);
        } catch (\Exception $e) {
            Log::error('Emergency timeline retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve emergency timeline'
            ], 500);
        }
    }

    /**
     * Add evidence to emergency
     */
    public function addEvidence(Request $request, Emergency $emergency): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|string|in:photo,video,document,audio,witness_statement',
                'description' => 'required|string|max:500',
                'file' => 'required|file|max:50240', // 50MB max
                'timestamp' => 'nullable|date',
                'location' => 'nullable|string|max:255',
                'collected_by' => 'nullable|string|max:255',
            ]);

            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('emergency_evidence', $filename, 'public');

            $evidence = $emergency->evidence ?? [];
            $evidence[] = [
                'id' => uniqid(),
                'type' => $request->type,
                'description' => $request->description,
                'filename' => $filename,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'timestamp' => $request->timestamp ?? now(),
                'location' => $request->location,
                'collected_by' => $request->collected_by ?? Auth::user()->name,
                'added_at' => now(),
                'added_by' => Auth::id(),
            ];

            $emergency->update(['evidence' => $evidence]);

            $this->logTimelineEvent($emergency, 'evidence_added', 
                'Evidence added: ' . $request->description,
                ['type' => $request->type, 'filename' => $filename]
            );

            return response()->json([
                'success' => true,
                'message' => 'Evidence added successfully',
                'data' => $evidence
            ]);

        } catch (\Exception $e) {
            Log::error('Evidence addition failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to add evidence'
            ], 500);
        }
    }

    /**
     * Get emergency types
     */
    public function getEmergencyTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'gas_leak' => 'Gas Leak',
                'fire' => 'Fire',
                'explosion' => 'Explosion',
                'equipment_failure' => 'Equipment Failure',
                'safety_incident' => 'Safety Incident',
                'medical_emergency' => 'Medical Emergency',
                'natural_disaster' => 'Natural Disaster',
                'security_breach' => 'Security Breach',
                'other' => 'Other'
            ]
        ]);
    }

    /**
     * Get emergency statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = [
                'total_emergencies' => Emergency::count(),
                'active_emergencies' => Emergency::whereIn('status', ['reported', 'acknowledged', 'responding'])->count(),
                'resolved_emergencies' => Emergency::where('status', 'resolved')->count(),
                'closed_emergencies' => Emergency::where('status', 'closed')->count(),
                'average_response_time' => Emergency::whereNotNull('total_response_time')->avg('total_response_time'),
                'priority_breakdown' => Emergency::groupBy('priority')->selectRaw('priority, count(*) as count')->get(),
                'type_breakdown' => Emergency::groupBy('type')->selectRaw('type, count(*) as count')->get(),
                'monthly_trends' => Emergency::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, count(*) as count')
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Emergency statistics retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve emergency statistics'
            ], 500);
        }
    }

    /**
     * Generate unique emergency ID
     */
    private function generateEmergencyId(): string
    {
        return 'EMR-' . date('Y') . '-' . str_pad(Emergency::count() + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate response SLA based on priority
     */
    private function calculateResponseSLA(string $priority): int
    {
        return match($priority) {
            'critical' => 15, // 15 minutes
            'high' => 30,     // 30 minutes
            'medium' => 60,   // 1 hour
            'low' => 240,     // 4 hours
            default => 60
        };
    }

    /**
     * Calculate resolution SLA based on priority
     */
    private function calculateResolutionSLA(string $priority): int
    {
        return match($priority) {
            'critical' => 2,  // 2 hours
            'high' => 4,      // 4 hours
            'medium' => 8,    // 8 hours
            'low' => 24,      // 24 hours
            default => 8
        };
    }

    /**
     * Get compliance requirements based on emergency type
     */
    private function getComplianceRequirements(string $type): array
    {
        $requirements = [
            'gas_leak' => ['safety_inspection', 'environmental_assessment', 'regulatory_reporting'],
            'fire' => ['fire_department_notification', 'insurance_claim', 'safety_audit'],
            'explosion' => ['investigation_report', 'safety_compliance', 'regulatory_notification'],
            'equipment_failure' => ['maintenance_report', 'safety_inspection'],
            'safety_incident' => ['incident_report', 'safety_training_review'],
            'medical_emergency' => ['medical_report', 'safety_review'],
            'natural_disaster' => ['damage_assessment', 'insurance_claim'],
            'security_breach' => ['security_audit', 'data_breach_notification'],
            'other' => ['general_report']
        ];

        return $requirements[$type] ?? [];
    }

    /**
     * Auto-assign emergency based on priority and type
     */
    private function autoAssignEmergency(Emergency $emergency): void
    {
        // This would implement auto-assignment logic based on availability, expertise, etc.
        // For now, we'll assign to the first available emergency responder
        $responder = User::where('role', 'emergency_responder')
            ->where('is_active', true)
            ->first();

        if ($responder) {
            $emergency->update(['assigned_to' => $responder->id]);
        }
    }

    /**
     * Send emergency notifications
     */
    private function sendEmergencyNotifications(Emergency $emergency): void
    {
        // Implementation for sending notifications to relevant parties
        // This would integrate with notification services
    }

    /**
     * Log timeline event
     */
    private function logTimelineEvent(Emergency $emergency, string $event, string $description, array $metadata = []): void
    {
        $timeline = $emergency->timeline ?? [];
        $timeline[] = [
            'event' => $event,
            'description' => $description,
            'timestamp' => now(),
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'metadata' => $metadata,
        ];

        $emergency->update(['timeline' => $timeline]);
    }

    /**
     * Calculate response metrics
     */
    private function calculateResponseMetrics(Emergency $emergency): array
    {
        $reportedAt = Carbon::parse($emergency->reported_at);
        $acknowledgedAt = $emergency->acknowledged_at ? Carbon::parse($emergency->acknowledged_at) : null;
        $resolvedAt = $emergency->resolved_at ? Carbon::parse($emergency->resolved_at) : null;

        return [
            'response_time' => $acknowledgedAt ? $reportedAt->diffInMinutes($acknowledgedAt) : null,
            'resolution_time' => $resolvedAt ? $reportedAt->diffInMinutes($resolvedAt) : null,
            'sla_compliance' => [
                'response' => $acknowledgedAt ? $reportedAt->diffInMinutes($acknowledgedAt) <= $emergency->response_sla : false,
                'resolution' => $resolvedAt ? $reportedAt->diffInHours($resolvedAt) <= $emergency->resolution_sla : false,
            ],
        ];
    }

    /**
     * Handle status change actions
     */
    private function handleStatusChange(Emergency $emergency, string $oldStatus, string $newStatus): void
    {
        // Implementation for status-specific actions
        // This would handle notifications, workflow triggers, etc.
    }

    /**
     * Escalate priority
     */
    private function escalatePriority(string $currentPriority): string
    {
        return match($currentPriority) {
            'low' => 'medium',
            'medium' => 'high',
            'high' => 'critical',
            'critical' => 'critical',
            default => 'medium'
        };
    }

    /**
     * Notify authorities
     */
    private function notifyAuthorities(Emergency $emergency): void
    {
        // Implementation for notifying relevant authorities
        // This would integrate with external notification systems
    }

    /**
     * Calculate total response time
     */
    private function calculateResponseTime(Emergency $emergency): ?int
    {
        if (!$emergency->reported_at || !$emergency->resolved_at) {
            return null;
        }

        return Carbon::parse($emergency->reported_at)->diffInMinutes($emergency->resolved_at);
    }
}
