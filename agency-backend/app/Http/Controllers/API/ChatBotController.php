<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatBot;
use App\Models\ChatBotConversation;
use App\Models\ChatBotMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatBotController extends Controller
{
    /**
     * Process incoming message from user
     */
    public function processMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'channel' => 'required|string|in:web,whatsapp,telegram,sms,mobile',
            'external_user_id' => 'required|string',
            'context' => 'sometimes|array',
        ]);

        try {
            $response = ChatBot::processMessage(
                $request->get('message'),
                $request->get('channel'),
                $request->get('external_user_id'),
                $request->get('context', [])
            );

            return response()->json([
                'success' => true,
                'data' => $response,
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('ChatBot processing error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Sorry, I encountered an error processing your message. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get conversation history
     */
    public function getConversation(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => 'required|string',
            'external_user_id' => 'required|string',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $conversation = ChatBotConversation::where('channel', $request->get('channel'))
            ->where('external_user_id', $request->get('external_user_id'))
            ->with(['messages' => function ($query) use ($request) {
                $query->latest()->limit($request->get('limit', 50));
            }])
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => true,
                'data' => [
                    'conversation' => null,
                    'messages' => []
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $conversation,
                'messages' => $conversation->messages->reverse()->values(),
                'conversation_stats' => [
                    'total_messages' => $conversation->messages()->count(),
                    'satisfaction_score' => $conversation->satisfaction_score,
                    'resolved_issues' => $conversation->resolved_issues_count,
                ]
            ]
        ]);
    }

    /**
     * Get chatbot analytics and insights
     */
    public function analytics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'week');
        $cacheKey = "chatbot_analytics_{$period}";

        $analytics = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($period) {
            $startDate = match($period) {
                'today' => now()->startOfDay(),
                'week' => now()->subWeek(),
                'month' => now()->subMonth(),
                'quarter' => now()->subQuarter(),
                'year' => now()->subYear(),
                default => now()->subWeek(),
            };

            return [
                'conversation_metrics' => $this->getConversationMetrics($startDate),
                'intent_analysis' => $this->getIntentAnalysis($startDate),
                'satisfaction_metrics' => $this->getSatisfactionMetrics($startDate),
                'performance_metrics' => $this->getPerformanceMetrics($startDate),
                'channel_analysis' => $this->getChannelAnalysis($startDate),
                'resolution_rates' => $this->getResolutionRates($startDate),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $analytics,
            'period' => $period
        ]);
    }

    /**
     * Update conversation satisfaction score
     */
    public function updateSatisfaction(Request $request, ChatBotConversation $conversation): JsonResponse
    {
        $request->validate([
            'score' => 'required|integer|min:1|max:5',
            'feedback' => 'sometimes|string|max:500',
        ]);

        $conversation->update([
            'satisfaction_score' => $request->get('score'),
            'feedback' => $request->get('feedback'),
            'rated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your feedback!'
        ]);
    }

    /**
     * Escalate conversation to human agent
     */
    public function escalateToHuman(Request $request, ChatBotConversation $conversation): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:200',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
        ]);

        $conversation->update([
            'status' => 'escalated',
            'escalated_at' => now(),
            'escalation_reason' => $request->get('reason'),
            'priority' => $request->get('priority', 'medium'),
        ]);

        // Notify human agents (implement notification logic)
        $this->notifyHumanAgents($conversation, $request->get('reason'));

        return response()->json([
            'success' => true,
            'message' => 'Your conversation has been escalated to a human agent. Someone will assist you shortly.',
            'estimated_wait_time' => $this->calculateEstimatedWaitTime()
        ]);
    }

    /**
     * Get available quick replies for current context
     */
    public function getQuickReplies(Request $request): JsonResponse
    {
        $context = $request->get('context', []);
        $lastIntent = $request->get('last_intent');

        $quickReplies = $this->generateQuickReplies($context, $lastIntent);

        return response()->json([
            'success' => true,
            'data' => $quickReplies
        ]);
    }

    /**
     * Get chatbot knowledge base
     */
    public function getKnowledgeBase(): JsonResponse
    {
        $knowledgeBase = [
            'faqs' => $this->getFAQs(),
            'common_intents' => $this->getCommonIntents(),
            'service_hours' => $this->getServiceHours(),
            'contact_info' => $this->getContactInfo(),
            'emergency_procedures' => $this->getEmergencyProcedures(),
        ];

        return response()->json([
            'success' => true,
            'data' => $knowledgeBase
        ]);
    }

    /**
     * Train chatbot with new data
     */
    public function trainBot(Request $request): JsonResponse
    {
        $request->validate([
            'training_data' => 'required|array',
            'training_type' => 'required|string|in:intent,entity,response,faq',
        ]);

        // Implement training logic here
        $result = $this->performTraining(
            $request->get('training_data'),
            $request->get('training_type')
        );

        return response()->json([
            'success' => true,
            'message' => 'Training completed successfully',
            'data' => $result
        ]);
    }

    /**
     * Get bot configuration
     */
    public function getConfiguration(): JsonResponse
    {
        $config = [
            'supported_languages' => ['en', 'hi', 'mr', 'gu'],
            'supported_channels' => ['web', 'whatsapp', 'telegram', 'sms', 'mobile'],
            'business_hours' => [
                'start' => '09:00',
                'end' => '18:00',
                'timezone' => 'Asia/Kolkata'
            ],
            'escalation_triggers' => [
                'sentiment_threshold' => -0.5,
                'unresolved_messages' => 5,
                'specific_keywords' => ['complaint', 'manager', 'cancel']
            ],
            'response_settings' => [
                'max_response_time' => 2000, // milliseconds
                'typing_indicator' => true,
                'enable_suggestions' => true
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $config
        ]);
    }

    // Private helper methods

    private function getConversationMetrics($startDate): array
    {
        return [
            'total_conversations' => ChatBotConversation::where('created_at', '>=', $startDate)->count(),
            'active_conversations' => ChatBotConversation::where('status', 'active')->count(),
            'resolved_conversations' => ChatBotConversation::where('status', 'resolved')->where('resolved_at', '>=', $startDate)->count(),
            'escalated_conversations' => ChatBotConversation::where('status', 'escalated')->where('escalated_at', '>=', $startDate)->count(),
            'average_conversation_length' => $this->getAverageConversationLength($startDate),
            'total_messages' => ChatBotMessage::where('created_at', '>=', $startDate)->count(),
        ];
    }

    private function getIntentAnalysis($startDate): array
    {
        return ChatBotMessage::where('created_at', '>=', $startDate)
            ->where('message_type', 'user')
            ->whereNotNull('intent')
            ->selectRaw('intent, COUNT(*) as count')
            ->groupBy('intent')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function getSatisfactionMetrics($startDate): array
    {
        $ratings = ChatBotConversation::where('rated_at', '>=', $startDate)
            ->whereNotNull('satisfaction_score')
            ->pluck('satisfaction_score');

        return [
            'average_rating' => $ratings->avg(),
            'total_ratings' => $ratings->count(),
            'rating_distribution' => $ratings->countBy()->toArray(),
            'satisfaction_rate' => $ratings->filter(fn($score) => $score >= 4)->count() / max($ratings->count(), 1) * 100,
        ];
    }

    private function getPerformanceMetrics($startDate): array
    {
        return [
            'average_response_time' => $this->getAverageResponseTime($startDate),
            'successful_resolutions' => $this->getSuccessfulResolutions($startDate),
            'intent_recognition_accuracy' => $this->getIntentRecognitionAccuracy($startDate),
            'conversation_completion_rate' => $this->getConversationCompletionRate($startDate),
        ];
    }

    private function getChannelAnalysis($startDate): array
    {
        return ChatBotConversation::where('created_at', '>=', $startDate)
            ->selectRaw('channel, COUNT(*) as conversations, AVG(satisfaction_score) as avg_satisfaction')
            ->groupBy('channel')
            ->get()
            ->toArray();
    }

    private function getResolutionRates($startDate): array
    {
        $total = ChatBotConversation::where('created_at', '>=', $startDate)->count();
        $resolved = ChatBotConversation::where('status', 'resolved')->where('resolved_at', '>=', $startDate)->count();
        $escalated = ChatBotConversation::where('status', 'escalated')->where('escalated_at', '>=', $startDate)->count();

        return [
            'total_conversations' => $total,
            'bot_resolved' => $resolved,
            'escalated_to_human' => $escalated,
            'bot_resolution_rate' => $total > 0 ? ($resolved / $total) * 100 : 0,
            'escalation_rate' => $total > 0 ? ($escalated / $total) * 100 : 0,
        ];
    }

    private function notifyHumanAgents(ChatBotConversation $conversation, string $reason): void
    {
        // Implement notification logic (email, Slack, SMS, etc.)
    }

    private function calculateEstimatedWaitTime(): string
    {
        // Calculate based on current queue and average handling time
        return '5-10 minutes';
    }

    private function generateQuickReplies(array $context, ?string $lastIntent): array
    {
        $quickReplies = [];

        switch ($lastIntent) {
            case 'gas_order':
                $quickReplies = [
                    ['text' => 'Book Regular Cylinder', 'payload' => 'book_regular'],
                    ['text' => 'Book Subsidy Cylinder', 'payload' => 'book_subsidy'],
                    ['text' => 'Emergency Delivery', 'payload' => 'emergency_delivery'],
                ];
                break;
            case 'order_tracking':
                $quickReplies = [
                    ['text' => 'Track My Order', 'payload' => 'track_order'],
                    ['text' => 'Cancel Order', 'payload' => 'cancel_order'],
                    ['text' => 'Reschedule Delivery', 'payload' => 'reschedule'],
                ];
                break;
            case 'payment':
                $quickReplies = [
                    ['text' => 'Pay Outstanding', 'payload' => 'pay_outstanding'],
                    ['text' => 'Payment History', 'payload' => 'payment_history'],
                    ['text' => 'Payment Methods', 'payload' => 'payment_methods'],
                ];
                break;
            default:
                $quickReplies = [
                    ['text' => 'Book Gas Cylinder', 'payload' => 'book_gas'],
                    ['text' => 'Track Order', 'payload' => 'track_order'],
                    ['text' => 'Make Payment', 'payload' => 'make_payment'],
                    ['text' => 'Speak to Agent', 'payload' => 'human_agent'],
                ];
        }

        return $quickReplies;
    }

    // Additional helper methods...
    private function getFAQs(): array { return []; }
    private function getCommonIntents(): array { return []; }
    private function getServiceHours(): array { return []; }
    private function getContactInfo(): array { return []; }
    private function getEmergencyProcedures(): array { return []; }
    private function performTraining(array $data, string $type): array { return []; }
    private function getAverageConversationLength($startDate): float { return 0.0; }
    private function getAverageResponseTime($startDate): float { return 0.0; }
    private function getSuccessfulResolutions($startDate): int { return 0; }
    private function getIntentRecognitionAccuracy($startDate): float { return 0.0; }
    private function getConversationCompletionRate($startDate): float { return 0.0; }
}