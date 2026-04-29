<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\Shop;
use App\Models\SubShop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class MessageController extends Controller
{
    private function resolveCurrentShop(User $user, ?int $shopId = null): ?Shop
    {
        if ($shopId) {
            return Shop::find($shopId);
        }

        // Get shop for the user (either owned or through subshop assignment)
        $shop = $user->shop;

        // If user doesn't own a shop, check if they're assigned to subshops
        if (!$shop) {
            $subshop = $user->subshops()->first();
            if ($subshop) {
                $shop = $subshop->shop;
            }
        }

        return $shop;
    }

    private function canMonitorShopMessages(User $user, ?Shop $shop): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if (!$shop) {
            return false;
        }

        return (int) $shop->user_id === (int) $user->id;
    }

    /**
     * Display a listing of messages for the authenticated user.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $shop = $this->resolveCurrentShop($user);
        $canMonitor = $this->canMonitorShopMessages($user, $shop);

        // Super Admins can access messages even without a shop
        if (!$shop && !$user->hasRole('Super Admin')) {
            abort(403, 'No shop associated with your account.');
        }

        // Check if viewing sent messages
        $folder = $request->get('folder');

        if ($folder === 'sent') {
            // Regular users: only messages they sent.
            // Monitoring users (owner/super-admin): all messages in the current shop.
            $query = Message::query();

            if ($canMonitor && $shop) {
                $query->where('shop_id', $shop->id);
            } else {
                $query->where('sender_id', $user->id);
                if (!$user->hasRole('Super Admin') && $shop) {
                    $query->where('shop_id', $shop->id);
                }
            }

            $query->with(['recipients.user', 'sender']);
        } else {
            // Inbox:
            // - Regular users: only messages explicitly addressed to them.
            // - Monitoring users: all messages in the current shop.

            if ($canMonitor && $shop) {
                $query = Message::query()
                    ->where('shop_id', $shop->id)
                    ->with(['sender', 'recipients.user']);
            } else {
                $query = Message::whereHas('recipients', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                    if (!$user->hasRole('Super Admin')) {
                        $q->whereNull('deleted_at');
                    }
                })
                    ->with([
                        'sender',
                        'recipients' => function ($q) use ($user) {
                            $q->where('user_id', $user->id);
                        }
                    ]);

                if (!$user->hasRole('Super Admin') && $shop) {
                    $query->where('shop_id', $shop->id);
                }
            }
        }

        // Apply filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Status filter only applies to inbox messages
        if ($folder !== 'sent' && $request->filled('status')) {
            if ($request->status === 'read') {
                $query->whereHas('recipients', function ($q) use ($user) {
                    $q->where('user_id', $user->id)->where('is_read', true);
                });
            } elseif ($request->status === 'unread') {
                $query->whereHas('recipients', function ($q) use ($user) {
                    $q->where('user_id', $user->id)->where('is_read', false);
                });
            }
        }

        // Sort by created date (newest first)
        $query->orderBy('created_at', 'desc');

        $messages = $query->paginate(20);

        // Add delete URLs to messages
        $messages->getCollection()->transform(function ($message) use ($user, $folder) {
            if ($folder === 'sent') {
                // For sent messages, user can always delete their own messages
                $message->delete_url = route('messages.destroy', $message);
            } else {
                // For inbox messages, check if user is a recipient
                $recipient = $message->recipients->where('user_id', $user->id)->first();
                $message->delete_url = $recipient ? route('messages.destroy', $message) : null;
            }
            return $message;
        });

        return view('messages.index', compact('messages', 'folder', 'canMonitor'));
    }

    /**
     * Show the form for creating a new message.
     */
    public function create(): View
    {
        $user = Auth::user();

        $shop = $this->resolveCurrentShop($user);

        if (!$shop) {
            abort(403, 'No shop associated with your account.');
        }

        // Get potential recipients (shop owner + shopkeepers)
        $recipients = collect();

        // Add shop owner if not current user
        if ($shop->owner && $shop->owner->id !== $user->id) {
            $recipients->push($shop->owner);
        }

        // Add shopkeepers
        if ($shop->shopkeepers()->count() > 0) {
            $recipients = $recipients->merge($shop->shopkeepers()->get());
        }

        return view('messages.create', compact('recipients'));
    }

    /**
     * Store a newly created message.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Get shop - either from request parameter (for configure shop context) or from user
        $shopId = $request->input('shop_id') ? (int) $request->input('shop_id') : null;
        $shop = $this->resolveCurrentShop($user, $shopId);

        if (!$shop) {
            return response()->json(['error' => 'No shop associated with your account.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:email,notification,system,bulk',
            'priority' => 'required|in:low,normal,high,urgent',
            'delivery_methods' => 'array',
            'delivery_methods.*' => 'in:email,in_app,sms',
            'scheduled_at' => 'nullable|date|after:now',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'integer|exists:users,id',
            'shop_id' => 'nullable|exists:shops,id', // Add shop_id validation
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Validate recipients are within the current shop (owner + shopkeepers)
            $allowedRecipients = collect();
            if ($shop->owner) {
                $allowedRecipients->push($shop->owner);
            }
            if ($shop->shopkeepers()->count() > 0) {
                $allowedRecipients = $allowedRecipients->merge($shop->shopkeepers()->get());
            }
            $allowedRecipientIds = $allowedRecipients->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->toArray();

            $requestedRecipientIds = collect($request->input('recipients', []))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->toArray();

            foreach ($requestedRecipientIds as $recipientId) {
                if (!in_array($recipientId, $allowedRecipientIds, true)) {
                    return response()->json([
                        'error' => 'One or more recipients are not part of your shop.',
                    ], 422);
                }
            }

            $message = Message::create([
                'sender_id' => $user->id,
                'shop_id' => $shop->id,
                'subject' => $request->input('subject'),
                'content' => $request->input('content'),
                'type' => $request->input('type'),
                'priority' => $request->input('priority'),
                'is_urgent' => $request->boolean('is_urgent'),
                'delivery_methods' => $request->input('delivery_methods'),
                'scheduled_at' => $request->input('scheduled_at'),
                'sent_at' => $request->input('scheduled_at') ? null : now(),
            ]);

            // Handle recipients
            $recipients = [];
            foreach ($requestedRecipientIds as $recipientId) {
                $recipients[] = [
                    'message_id' => $message->id,
                    'user_id' => $recipientId,
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($recipients)) {
                MessageRecipient::insert($recipients);
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'An error occurred while sending the message.'], 500);
        }

        return response()->json([
                'success' => true,
                'message' => 'Message sent successfully.',
                'message_id' => $message->id
            ]);
    }

    /**
     * Update the specified message.
     */
    public function update(Request $request, Message $message): JsonResponse
    {
        $user = Auth::user();

        // Check if user is the sender of this message
        if ($message->sender_id !== $user->id) {
            return response()->json(['error' => 'You can only edit your own messages.'], 403);
        }

        // Check if message was sent more than 24 hours ago
        if ($message->sent_at && $message->sent_at->diffInHours(now()) > 24) {
            return response()->json(['error' => 'Messages can only be edited within 24 hours of sending.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'priority' => 'required|in:low,normal,high,urgent',
            'is_urgent' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $message->update([
            'subject' => $request->input('subject'),
            'content' => $request->input('content'),
            'priority' => $request->input('priority'),
            'is_urgent' => $request->boolean('is_urgent'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message updated successfully.',
            'message_data' => [
                'id' => $message->id,
                'subject' => $message->subject,
                'content' => $message->content,
                'priority' => $message->priority,
                'is_urgent' => $message->is_urgent,
                'updated_at' => $message->updated_at->diffForHumans(),
            ]
        ]);
    }

    /**
     * Display the specified message.
     */
    public function show(Message $message): View
    {
        $user = Auth::user();

        $shop = $this->resolveCurrentShop($user);
        $canMonitor = $this->canMonitorShopMessages($user, $shop);
        $isSameShop = $shop ? (int) $message->shop_id === (int) $shop->id : false;

        // Check if user is the sender OR a recipient of this message
        $isSender = $message->sender_id === $user->id;
        $recipient = $message->recipients()->where('user_id', $user->id)->first();

        if (!$isSender && !$recipient && !($canMonitor && $isSameShop)) {
            abort(403, 'You are not authorized to view this message.');
        }

        // Mark as read if not already read (only for recipients)
        if ($recipient && !$recipient->is_read) {
            $recipient->markAsRead();
        }

        return view('messages.show', compact('message', 'recipient', 'isSender', 'canMonitor'));
    }

    /**
     * Mark a message as read for the authenticated user.
     */
    public function markAsRead(Request $request, Message $message): JsonResponse
    {
        $user = Auth::user();

        // Check if user is a recipient
        $recipient = $message->recipients()->where('user_id', $user->id)->first();

        if (!$recipient) {
            return response()->json(['error' => 'Message not found.'], 404);
        }

        $recipient->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Get unread messages count for the authenticated user.
     */
    public function getUnreadCount(): JsonResponse
    {
        $user = Auth::user();

        // Get shop for the user (either owned or through subshop assignment)
        $shop = $user->shop;

        // If user doesn't own a shop, check if they're assigned to subshops
        if (!$shop) {
            $subshop = $user->subshops()->first();
            if ($subshop) {
                $shop = $subshop->shop;
            }
        }

        // Super Admins can access message count even without a shop
        if (!$shop && !$user->hasRole('Super Admin')) {
            return response()->json(['count' => 0]);
        }

        $query = MessageRecipient::where('user_id', $user->id)
            ->where('is_read', false);

        // Super Admins don't have soft delete restrictions
        if (!$user->hasRole('Super Admin')) {
            $query->whereNull('deleted_at');
        }

        $count = $query->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Get recent messages for the authenticated user.
     */
    public function getRecentMessages(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Get shop - either from request parameter (for configure shop context) or from user
        $shopId = $request->input('shop_id');
        if ($shopId) {
            $shop = Shop::find($shopId);
        } else {
            // Get shop for the user (either owned or through subshop assignment)
            $shop = $user->shop;

            if (!$shop) {
                $subshop = $user->subshops()->first();
                if ($subshop) {
                    $shop = $subshop->shop;
                }
            }
        }

        // Super Admins can access recent messages even without a shop
        if (!$shop && !$user->hasRole('Super Admin')) {
            return response()->json(['messages' => []]);
        }

        $messages = Message::whereHas('recipients', function ($q) use ($user) {
            $q->where('user_id', $user->id);
            // Only exclude soft-deleted for non-super-admin users
            if (!$user->hasRole('Super Admin')) {
                $q->whereNull('deleted_at');
            }
        })
        ->with(['sender', 'recipients' => function ($q) use ($user) {
            $q->where('user_id', $user->id);
        }]);

        // Super Admins can see all messages, others only their shop's messages
        if (!$user->hasRole('Super Admin')) {
            $messages->where(function ($q) use ($shop) {
                $q->where('shop_id', $shop ? $shop->id : null)
                  ->orWhereNull('shop_id'); // Allow system messages
            });
        }

        $messages = $messages->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($message) use ($user) {
                $recipient = $message->recipients->first();
                return [
                    'id' => $message->id,
                    'subject' => $message->subject,
                    'sender' => $message->sender->name,
                    'type' => $message->type,
                    'priority' => $message->priority,
                    'is_read' => $recipient ? $recipient->is_read : false,
                    'created_at' => $message->created_at->diffForHumans(),
                    'icon' => $message->getTypeIcon(),
                    'badge_class' => $message->getPriorityBadgeClass(),
                ];
            });

        return response()->json(['messages' => $messages]);
    }

    /**
     * Soft delete a message for the authenticated user.
     */
    public function destroy(Request $request, Message $message): JsonResponse
    {
        $user = Auth::user();

        // Allow sender to delete their own sent message (hard delete)
        if ((int) $message->sender_id === (int) $user->id) {
            $message->delete();

            return response()->json(['success' => true, 'message' => 'Message deleted successfully.']);
        }

        // Check if user is a recipient
        $recipient = $message->recipients()->where('user_id', $user->id)->first();

        if (!$recipient) {
            return response()->json(['error' => 'Message not found or already deleted.'], 404);
        }

        // Soft delete for this user (mark as deleted)
        $recipient->delete();

        return response()->json(['success' => true, 'message' => 'Message deleted successfully.']);
    }

    /**
     * Send a message directly to super admin.
     */
    public function sendToSuperAdmin(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Get shop for the user (either owned or through subshop assignment)
        // For users on shop status page, they might not have an active shop
        $shop = $user->shop;

        // If user doesn't own a shop, check if they're assigned to subshops
        if (!$shop) {
            $subshop = $user->subshops()->first();
            if ($subshop) {
                $shop = $subshop->shop;
            }
        }

        // If still no shop (user has no shop association), we'll still allow the message
        // but create it without a shop_id (system-level message)
        $shopId = $shop ? $shop->id : null;

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Find super admin users
            $superAdmins = User::role('Super Admin')->get();

            if ($superAdmins->isEmpty()) {
                return response()->json(['error' => 'No super admin users found.'], 404);
            }

            // Create the message
            $message = Message::create([
                'sender_id' => $user->id,
                'shop_id' => $shopId, // Can be null for users without shop
                'subject' => $request->input('subject'),
                'content' => $request->input('content'),
                'type' => 'notification',
                'priority' => 'high',
                'is_urgent' => true,
                'delivery_methods' => ['in_app'],
                'sent_at' => now(),
            ]);

            // Add all super admins as recipients
            $recipients = [];
            foreach ($superAdmins as $superAdmin) {
                $recipients[] = [
                    'message_id' => $message->id,
                    'user_id' => $superAdmin->id,
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            MessageRecipient::insert($recipients);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Message sent to super admin successfully.',
                'message_id' => $message->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'An error occurred while sending the message.'], 500);
        }
    }
}
