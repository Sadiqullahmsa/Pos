<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class ChatBot extends Model
{
    use HasFactory;

    // This model manages the overall chatbot configuration and knowledge base
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'configuration',
        'knowledge_base',
        'supported_languages',
        'default_language',
        'escalation_rules',
        'analytics_config',
    ];

    protected $casts = [
        'configuration' => 'array',
        'knowledge_base' => 'array',
        'supported_languages' => 'array',
        'escalation_rules' => 'array',
        'analytics_config' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Process incoming message and generate response.
     */
    public static function processMessage(string $message, string $channel, string $externalUserId, array $context = []): array
    {
        // Find or create conversation
        $conversation = ChatBotConversation::findOrCreateConversation($channel, $externalUserId, $context);
        
        // Analyze message intent and entities
        $analysis = static::analyzeMessage($message, $conversation->language);
        
        // Generate appropriate response
        $response = static::generateResponse($message, $analysis, $conversation);
        
        // Save messages
        ChatBotMessage::createUserMessage($conversation->id, $message, $analysis);
        ChatBotMessage::createBotMessage($conversation->id, $response['message'], $response);
        
        // Update conversation
        $conversation->updateActivity($analysis);
        
        return $response;
    }

    /**
     * Analyze message for intent and entities.
     */
    private static function analyzeMessage(string $message, string $language = 'en'): array
    {
        $analysis = [
            'intent' => null,
            'confidence' => 0,
            'entities' => [],
            'sentiment' => 'neutral',
            'language' => $language,
        ];

        // Intent patterns for LPG Gas Agency
        $intentPatterns = [
            'order_gas' => [
                'patterns' => ['book gas', 'order cylinder', 'need gas', 'gas cylinder', 'lpg order'],
                'keywords' => ['book', 'order', 'gas', 'cylinder', 'lpg', 'refill'],
            ],
            'track_order' => [
                'patterns' => ['track order', 'order status', 'where is my order', 'delivery status'],
                'keywords' => ['track', 'status', 'delivery', 'order id', 'where'],
            ],
            'complaint' => [
                'patterns' => ['complaint', 'problem', 'issue', 'not working', 'defective'],
                'keywords' => ['complaint', 'problem', 'issue', 'defective', 'wrong', 'broken'],
            ],
            'payment' => [
                'patterns' => ['payment', 'bill', 'pay', 'amount', 'cost', 'price'],
                'keywords' => ['payment', 'pay', 'bill', 'amount', 'cost', 'price', 'money'],
            ],
            'customer_support' => [
                'patterns' => ['help', 'support', 'contact', 'talk to agent', 'human'],
                'keywords' => ['help', 'support', 'agent', 'human', 'contact', 'assistance'],
            ],
            'greeting' => [
                'patterns' => ['hello', 'hi', 'hey', 'good morning', 'good evening'],
                'keywords' => ['hello', 'hi', 'hey', 'good', 'morning', 'evening'],
            ],
            'connection_info' => [
                'patterns' => ['connection details', 'my connection', 'consumer number', 'connection status'],
                'keywords' => ['connection', 'consumer', 'number', 'details', 'info'],
            ],
            'delivery_schedule' => [
                'patterns' => ['delivery time', 'when will deliver', 'delivery schedule', 'delivery slot'],
                'keywords' => ['delivery', 'time', 'schedule', 'slot', 'when'],
            ],
        ];

        $messageLower = strtolower($message);
        $bestMatch = null;
        $highestConfidence = 0;

        foreach ($intentPatterns as $intent => $config) {
            $confidence = static::calculateIntentConfidence($messageLower, $config);
            
            if ($confidence > $highestConfidence && $confidence > 0.3) {
                $highestConfidence = $confidence;
                $bestMatch = $intent;
            }
        }

        if ($bestMatch) {
            $analysis['intent'] = $bestMatch;
            $analysis['confidence'] = $highestConfidence;
        }

        // Extract entities
        $analysis['entities'] = static::extractEntities($message);
        
        // Analyze sentiment
        $analysis['sentiment'] = static::analyzeSentiment($message);

        return $analysis;
    }

    /**
     * Calculate intent confidence score.
     */
    private static function calculateIntentConfidence(string $message, array $config): float
    {
        $score = 0;
        $totalWords = str_word_count($message);
        
        // Check pattern matches
        foreach ($config['patterns'] as $pattern) {
            if (strpos($message, $pattern) !== false) {
                $score += 0.8;
            }
        }
        
        // Check keyword matches
        $keywordMatches = 0;
        foreach ($config['keywords'] as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $keywordMatches++;
            }
        }
        
        if ($keywordMatches > 0) {
            $score += ($keywordMatches / count($config['keywords'])) * 0.6;
        }
        
        return min($score, 1.0);
    }

    /**
     * Extract entities from message.
     */
    private static function extractEntities(string $message): array
    {
        $entities = [];
        
        // Extract phone numbers
        if (preg_match('/(\+?91[\-\s]?)?[6-9]\d{9}/', $message, $matches)) {
            $entities['phone'] = $matches[0];
        }
        
        // Extract order IDs
        if (preg_match('/ORD\d{8}/', $message, $matches)) {
            $entities['order_id'] = $matches[0];
        }
        
        // Extract customer IDs
        if (preg_match('/CUST\d{6}/', $message, $matches)) {
            $entities['customer_id'] = $matches[0];
        }
        
        // Extract numbers (quantities, amounts)
        if (preg_match_all('/\b\d+(?:\.\d+)?\b/', $message, $matches)) {
            $entities['numbers'] = $matches[0];
        }
        
        // Extract dates
        if (preg_match('/\b(?:today|tomorrow|yesterday|\d{1,2}\/\d{1,2}\/\d{2,4})\b/i', $message, $matches)) {
            $entities['date'] = $matches[0];
        }
        
        return $entities;
    }

    /**
     * Analyze message sentiment.
     */
    private static function analyzeSentiment(string $message): string
    {
        $positiveWords = ['good', 'great', 'excellent', 'happy', 'satisfied', 'thank you', 'thanks', 'pleased'];
        $negativeWords = ['bad', 'terrible', 'awful', 'angry', 'disappointed', 'frustrated', 'problem', 'issue', 'complaint'];
        
        $messageLower = strtolower($message);
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            if (strpos($messageLower, $word) !== false) {
                $positiveCount++;
            }
        }
        
        foreach ($negativeWords as $word) {
            if (strpos($messageLower, $word) !== false) {
                $negativeCount++;
            }
        }
        
        if ($positiveCount > $negativeCount) {
            return 'positive';
        } elseif ($negativeCount > $positiveCount) {
            return 'negative';
        }
        
        return 'neutral';
    }

    /**
     * Generate appropriate response based on intent and context.
     */
    private static function generateResponse(string $message, array $analysis, ChatBotConversation $conversation): array
    {
        $intent = $analysis['intent'];
        $entities = $analysis['entities'];
        $context = $conversation->context;

        $response = [
            'message' => '',
            'quick_replies' => [],
            'requires_escalation' => false,
            'template' => null,
            'actions' => [],
        ];

        switch ($intent) {
            case 'greeting':
                $response = static::handleGreeting($conversation, $context);
                break;
                
            case 'order_gas':
                $response = static::handleGasOrder($conversation, $entities, $context);
                break;
                
            case 'track_order':
                $response = static::handleOrderTracking($conversation, $entities, $context);
                break;
                
            case 'complaint':
                $response = static::handleComplaint($conversation, $message, $analysis);
                break;
                
            case 'payment':
                $response = static::handlePaymentInquiry($conversation, $entities, $context);
                break;
                
            case 'customer_support':
                $response = static::handleSupportRequest($conversation, $context);
                break;
                
            case 'connection_info':
                $response = static::handleConnectionInfo($conversation, $entities, $context);
                break;
                
            case 'delivery_schedule':
                $response = static::handleDeliverySchedule($conversation, $entities, $context);
                break;
                
            default:
                $response = static::handleUnknownIntent($message, $conversation);
        }

        return $response;
    }

    /**
     * Handle greeting messages.
     */
    private static function handleGreeting(ChatBotConversation $conversation, array $context): array
    {
        $greetings = [
            "Hello! Welcome to our LPG Gas Agency. How can I help you today?",
            "Hi there! I'm here to assist you with your gas delivery needs.",
            "Good day! How may I assist you with your LPG requirements?",
        ];

        return [
            'message' => $greetings[array_rand($greetings)],
            'quick_replies' => [
                'Book Gas Cylinder',
                'Track My Order',
                'Payment Information',
                'Connect with Agent',
            ],
            'template' => 'greeting',
        ];
    }

    /**
     * Handle gas order requests.
     */
    private static function handleGasOrder(ChatBotConversation $conversation, array $entities, array $context): array
    {
        // Check if customer is identified
        if (!$conversation->customer_id) {
            return [
                'message' => "I'd be happy to help you book a gas cylinder! First, could you please share your registered phone number or customer ID?",
                'quick_replies' => ['Share Phone Number', 'Enter Customer ID'],
                'template' => 'order_identification_required',
                'actions' => ['collect_customer_info'],
            ];
        }

        // Check if customer has active connections
        $customer = Customer::find($conversation->customer_id);
        $activeConnections = $customer->connections()->where('status', 'active')->get();

        if ($activeConnections->isEmpty()) {
            return [
                'message' => "I don't see any active connections for your account. Would you like me to help you with a new connection?",
                'quick_replies' => ['New Connection', 'Contact Support'],
                'template' => 'no_active_connections',
            ];
        }

        if ($activeConnections->count() === 1) {
            $connection = $activeConnections->first();
            
            // Check quota availability
            if ($connection->used_quota >= $connection->monthly_quota) {
                return [
                    'message' => "Your monthly quota is exhausted. Your next refill will be available after quota reset on " . $connection->quota_reset_date->format('d M Y'),
                    'quick_replies' => ['Emergency Order', 'Contact Support'],
                    'template' => 'quota_exhausted',
                ];
            }

            return [
                'message' => "Great! I can book a cylinder for your connection {$connection->connection_id}. The current price is ₹850. Shall I proceed with the booking?",
                'quick_replies' => ['Confirm Order', 'Check Delivery Time', 'Cancel'],
                'template' => 'order_confirmation',
                'actions' => ['prepare_order'],
            ];
        }

        // Multiple connections - let user choose
        $connectionList = $activeConnections->map(function ($conn) {
            return "{$conn->connection_id} ({$conn->connection_type})";
        })->toArray();

        return [
            'message' => "You have multiple connections. Please select the connection for gas booking:\n" . implode("\n", $connectionList),
            'quick_replies' => array_slice($connectionList, 0, 3), // Limit to 3 for UI
            'template' => 'select_connection',
        ];
    }

    /**
     * Handle order tracking requests.
     */
    private static function handleOrderTracking(ChatBotConversation $conversation, array $entities, array $context): array
    {
        if (isset($entities['order_id'])) {
            $order = Order::where('order_id', $entities['order_id'])->first();
            
            if (!$order) {
                return [
                    'message' => "I couldn't find an order with ID {$entities['order_id']}. Please check the order ID and try again.",
                    'quick_replies' => ['Enter Correct Order ID', 'Contact Support'],
                    'template' => 'order_not_found',
                ];
            }

            $statusMessages = [
                'pending' => 'Your order is received and being processed.',
                'confirmed' => 'Your order is confirmed and will be dispatched soon.',
                'processing' => 'Your order is being prepared for dispatch.',
                'dispatched' => 'Your order is dispatched and on the way.',
                'delivered' => 'Your order has been delivered successfully.',
                'cancelled' => 'Your order has been cancelled.',
            ];

            $message = "Order {$order->order_id}:\n";
            $message .= "Status: " . ucfirst($order->status) . "\n";
            $message .= $statusMessages[$order->status] . "\n";
            
            if ($order->delivery) {
                $message .= "Expected delivery: " . $order->delivery->scheduled_time->format('d M Y, h:i A');
            }

            return [
                'message' => $message,
                'quick_replies' => ['Track Live Location', 'Call Delivery Boy', 'Order Again'],
                'template' => 'order_status',
            ];
        }

        return [
            'message' => "Please provide your order ID to track the status. Order ID format: ORD12345678",
            'quick_replies' => ['Recent Orders', 'Contact Support'],
            'template' => 'order_id_required',
        ];
    }

    /**
     * Handle complaint registration.
     */
    private static function handleComplaint(ChatBotConversation $conversation, string $message, array $analysis): array
    {
        // Create complaint record
        $complaint = Complaint::create([
            'customer_id' => $conversation->customer_id,
            'title' => 'Complaint via ChatBot',
            'description' => $message,
            'category' => static::categorizeComplaint($message),
            'priority' => $analysis['sentiment'] === 'negative' ? 'high' : 'medium',
            'source' => $conversation->channel,
        ]);

        return [
            'message' => "I've registered your complaint with ID {$complaint->complaint_id}. Our support team will contact you within 24 hours. Is there anything else I can help you with?",
            'quick_replies' => ['Track Complaint', 'Call Support', 'No, Thank You'],
            'template' => 'complaint_registered',
            'requires_escalation' => true,
        ];
    }

    /**
     * Categorize complaint based on content.
     */
    private static function categorizeComplaint(string $message): string
    {
        $categories = [
            'delivery' => ['delivery', 'late', 'time', 'schedule', 'driver'],
            'product' => ['cylinder', 'gas', 'leak', 'defective', 'quality'],
            'billing' => ['bill', 'payment', 'charge', 'amount', 'price'],
            'service' => ['service', 'support', 'staff', 'behavior'],
        ];

        $messageLower = strtolower($message);
        
        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($messageLower, $keyword) !== false) {
                    return $category;
                }
            }
        }

        return 'others';
    }

    /**
     * Handle payment inquiries.
     */
    private static function handlePaymentInquiry(ChatBotConversation $conversation, array $entities, array $context): array
    {
        if (!$conversation->customer_id) {
            return [
                'message' => "Please provide your customer ID or phone number to check payment information.",
                'quick_replies' => ['Enter Customer ID', 'Enter Phone Number'],
                'template' => 'customer_identification_required',
            ];
        }

        $customer = Customer::find($conversation->customer_id);
        $outstandingAmount = $customer->outstanding_balance;

        if ($outstandingAmount > 0) {
            return [
                'message' => "Your outstanding balance is ₹{$outstandingAmount}. Would you like to make a payment now?",
                'quick_replies' => ['Pay Now', 'Payment History', 'Contact Support'],
                'template' => 'outstanding_balance',
                'actions' => ['show_payment_options'],
            ];
        }

        return [
            'message' => "Great! You have no outstanding payments. Your account is up to date.",
            'quick_replies' => ['Payment History', 'Book Gas', 'Main Menu'],
            'template' => 'no_outstanding',
        ];
    }

    /**
     * Handle support escalation requests.
     */
    private static function handleSupportRequest(ChatBotConversation $conversation, array $context): array
    {
        $conversation->update(['requires_human_intervention' => true]);

        return [
            'message' => "I'm connecting you with our support team. Please hold on while I transfer your chat to a human agent.",
            'quick_replies' => [],
            'template' => 'escalating_to_human',
            'requires_escalation' => true,
            'actions' => ['escalate_to_human'],
        ];
    }

    /**
     * Handle connection info requests.
     */
    private static function handleConnectionInfo(ChatBotConversation $conversation, array $entities, array $context): array
    {
        if (!$conversation->customer_id) {
            return [
                'message' => "Please provide your customer ID to fetch connection details.",
                'quick_replies' => ['Enter Customer ID'],
                'template' => 'customer_id_required',
            ];
        }

        $customer = Customer::find($conversation->customer_id);
        $connections = $customer->connections;

        if ($connections->isEmpty()) {
            return [
                'message' => "You don't have any connections yet. Would you like to apply for a new connection?",
                'quick_replies' => ['New Connection', 'Contact Support'],
                'template' => 'no_connections',
            ];
        }

        $info = "Your connection details:\n";
        foreach ($connections as $conn) {
            $info .= "• {$conn->connection_id} ({$conn->connection_type})\n";
            $info .= "  Status: {$conn->status}\n";
            $info .= "  Quota used: {$conn->used_quota}/{$conn->monthly_quota} kg\n\n";
        }

        return [
            'message' => $info,
            'quick_replies' => ['Book Gas', 'Payment Info', 'Main Menu'],
            'template' => 'connection_details',
        ];
    }

    /**
     * Handle delivery schedule inquiries.
     */
    private static function handleDeliverySchedule(ChatBotConversation $conversation, array $entities, array $context): array
    {
        $activeDeliveries = [];
        
        if ($conversation->customer_id) {
            $customer = Customer::find($conversation->customer_id);
            $activeDeliveries = $customer->orders()
                ->whereIn('status', ['dispatched'])
                ->with('delivery')
                ->get();
        }

        if ($activeDeliveries->isEmpty()) {
            return [
                'message' => "You don't have any active deliveries. Would you like to book a gas cylinder?",
                'quick_replies' => ['Book Gas', 'Order History', 'Main Menu'],
                'template' => 'no_active_deliveries',
            ];
        }

        $schedule = "Your delivery schedule:\n";
        foreach ($activeDeliveries as $order) {
            if ($order->delivery) {
                $schedule .= "• Order {$order->order_id}\n";
                $schedule .= "  Scheduled: " . $order->delivery->scheduled_time->format('d M Y, h:i A') . "\n";
                $schedule .= "  Status: " . ucfirst($order->delivery->status) . "\n\n";
            }
        }

        return [
            'message' => $schedule,
            'quick_replies' => ['Track Live', 'Reschedule', 'Contact Driver'],
            'template' => 'delivery_schedule',
        ];
    }

    /**
     * Handle unknown intents.
     */
    private static function handleUnknownIntent(string $message, ChatBotConversation $conversation): array
    {
        $suggestions = [
            "I'm not sure I understand. Here's what I can help you with:",
            "I didn't quite get that. Let me show you what I can do:",
            "I'm here to help! Here are some things I can assist you with:",
        ];

        return [
            'message' => $suggestions[array_rand($suggestions)],
            'quick_replies' => [
                'Book Gas Cylinder',
                'Track Order',
                'Payment Info',
                'Connect with Agent',
            ],
            'template' => 'fallback',
            'actions' => ['suggest_popular_actions'],
        ];
    }
}

/**
 * ChatBot Conversation Model
 */
class ChatBotConversation extends Model
{
    protected $fillable = [
        'conversation_id',
        'customer_id',
        'channel',
        'external_user_id',
        'session_id',
        'status',
        'context',
        'language',
        'user_profile',
        'started_at',
        'last_activity_at',
        'ended_at',
        'message_count',
        'requires_human_intervention',
        'escalated_to_user_id',
        'satisfaction_rating',
        'feedback',
        'resolved_issues',
        'metadata',
    ];

    protected $casts = [
        'context' => 'array',
        'user_profile' => 'array',
        'resolved_issues' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at' => 'datetime',
        'requires_human_intervention' => 'boolean',
        'satisfaction_rating' => 'decimal:2',
    ];

    /**
     * Find or create conversation.
     */
    public static function findOrCreateConversation(string $channel, string $externalUserId, array $context = []): self
    {
        $conversation = static::where('channel', $channel)
            ->where('external_user_id', $externalUserId)
            ->where('status', 'active')
            ->first();

        if (!$conversation) {
            $conversation = static::create([
                'conversation_id' => 'CONV' . uniqid(),
                'channel' => $channel,
                'external_user_id' => $externalUserId,
                'session_id' => session_id() ?: uniqid(),
                'context' => $context,
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);
        }

        return $conversation;
    }

    /**
     * Update conversation activity.
     */
    public function updateActivity(array $analysis): void
    {
        $this->increment('message_count');
        $this->update(['last_activity_at' => now()]);

        // Check for escalation triggers
        if ($analysis['sentiment'] === 'negative' && $analysis['confidence'] > 0.7) {
            $this->checkEscalationTriggers($analysis);
        }
    }

    /**
     * Check if conversation should be escalated.
     */
    private function checkEscalationTriggers(array $analysis): void
    {
        $escalationKeywords = ['manager', 'supervisor', 'complaint', 'angry', 'furious', 'terrible'];
        
        foreach ($escalationKeywords as $keyword) {
            if (stripos($analysis['entities']['message'] ?? '', $keyword) !== false) {
                $this->update(['requires_human_intervention' => true]);
                break;
            }
        }
    }

    /**
     * Get conversation messages.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatBotMessage::class, 'conversation_id');
    }

    /**
     * Get customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

/**
 * ChatBot Message Model
 */
class ChatBotMessage extends Model
{
    protected $fillable = [
        'message_id',
        'conversation_id',
        'sender_type',
        'sender_user_id',
        'message',
        'message_type',
        'attachments',
        'quick_replies',
        'intent_analysis',
        'entities',
        'confidence_score',
        'is_automated_response',
        'response_template',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'quick_replies' => 'array',
        'intent_analysis' => 'array',
        'entities' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'is_automated_response' => 'boolean',
        'confidence_score' => 'decimal:4',
    ];

    /**
     * Create user message.
     */
    public static function createUserMessage(int $conversationId, string $message, array $analysis): self
    {
        return static::create([
            'message_id' => 'MSG' . uniqid(),
            'conversation_id' => $conversationId,
            'sender_type' => 'user',
            'message' => $message,
            'intent_analysis' => $analysis,
            'entities' => $analysis['entities'] ?? [],
            'confidence_score' => $analysis['confidence'] ?? 0,
            'sent_at' => now(),
        ]);
    }

    /**
     * Create bot message.
     */
    public static function createBotMessage(int $conversationId, string $message, array $response): self
    {
        return static::create([
            'message_id' => 'MSG' . uniqid(),
            'conversation_id' => $conversationId,
            'sender_type' => 'bot',
            'message' => $message,
            'quick_replies' => $response['quick_replies'] ?? [],
            'response_template' => $response['template'] ?? null,
            'is_automated_response' => true,
            'sent_at' => now(),
        ]);
    }

    /**
     * Get conversation.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatBotConversation::class, 'conversation_id');
    }
}
