<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_bot_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->string('channel'); // whatsapp, telegram, web_chat, app, sms
            $table->string('external_user_id'); // WhatsApp phone number, Telegram user ID, etc.
            $table->string('session_id');
            $table->enum('status', ['active', 'completed', 'escalated', 'abandoned'])->default('active');
            $table->json('context'); // Conversation context and state
            $table->string('language')->default('en');
            $table->json('user_profile')->nullable(); // User information from the channel
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('message_count')->default(0);
            $table->boolean('requires_human_intervention')->default(false);
            $table->foreignId('escalated_to_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('satisfaction_rating', 3, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->json('resolved_issues')->nullable(); // Issues resolved in this conversation
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamps();
            $table->index('conversation_id');
            $table->index('customer_id');
            $table->index('channel');
            $table->index('status');
            $table->index('started_at');
        });

        Schema::create('chat_bot_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->foreignId('conversation_id')->constrained('chat_bot_conversations')->onDelete('cascade');
            $table->enum('sender_type', ['user', 'bot', 'agent']);
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('message');
            $table->string('message_type')->default('text'); // text, image, document, location, quick_reply
            $table->json('attachments')->nullable(); // File attachments
            $table->json('quick_replies')->nullable(); // Quick reply options
            $table->json('intent_analysis')->nullable(); // AI intent detection results
            $table->json('entities')->nullable(); // Extracted entities
            $table->decimal('confidence_score', 5, 4)->nullable(); // AI confidence
            $table->boolean('is_automated_response')->default(false);
            $table->string('response_template')->nullable(); // Template used for response
            $table->timestamp('sent_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index('conversation_id');
            $table->index('sender_type');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_bot_conversations');
    }
};
