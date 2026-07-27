<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Chat\Attachment;
use App\Models\User;
use App\Events\Chat\MessageSent;
use App\Events\Chat\UserReceivedMessage;
use App\Events\Chat\CallEvent;
use App\Jobs\SendFcmpushNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatApiController extends Controller
{
    public function users(Request $request)
    {
        $query = User::where('id', '!=', $request->user()->id);
        
        if (!$request->user()->isSuperAdmin()) {
            $query->where('company_id', $request->user()->company_id);
        }
        
        $users = $query->select('id', 'name', 'email', 'profile_photo')->get()->map(function ($u) {
            $u->profile_photo_url = $u->profile_photo ? url('storage/' . $u->profile_photo) : null;
            return $u;
        });
        return response()->json($users);
    }

    public function conversations(Request $request)
    {
        $user = $request->user();
        
        // Only show conversations where this user hasn't soft-deleted
        $conversations = $user->conversations()
            ->wherePivot('deleted_at', null)
            ->with(['users' => function ($query) use ($user) {
                // Load ALL other users (even if they left) so names always show
                $query->where('users.id', '!=', $user->id)->select('users.id', 'name', 'profile_photo');
            }])
            ->with('lastMessage')
            ->orderByDesc(
                Message::select('created_at')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->orderByDesc('created_at')
                    ->limit(1)
            )
            ->get();

        $result = $conversations->map(function ($conv) use ($user) {
            $otherUsers = $conv->users;
            $name = $conv->type === 'group' ? $conv->name : ($otherUsers->first()->name ?? 'Bilinmeyen Kullanıcı');
            $otherUser = $otherUsers->first();
            $photo = $otherUser && $otherUser->profile_photo ? url('storage/' . $otherUser->profile_photo) : null;
            
            $pivot = $conv->pivot;
            $unreadQuery = Message::where('conversation_id', $conv->id)
                ->where('sender_id', '!=', $user->id);
            if ($pivot->last_read_message_id) {
                $unreadQuery->where('id', '>', $pivot->last_read_message_id);
            }
            $unreadCount = $unreadQuery->count();

            return [
                'id' => $conv->id,
                'type' => $conv->type,
                'name' => $name,
                'profile_photo_url' => $photo,
                'participants' => $otherUsers->pluck('name')->join(', '),
                'last_message' => $conv->lastMessage ? $conv->lastMessage->body : null,
                'last_message_time' => $conv->lastMessage ? $conv->lastMessage->created_at->format('H:i') : null,
                'last_message_read' => true,
                'unread_count' => $unreadCount,
            ];
        });

        return response()->json($result);
    }

    public function storeConversation(Request $request)
    {
        $request->validate([
            'type' => 'required|in:direct,group',
            'users' => 'required|array',
            'users.*' => 'exists:users,id',
            'name' => 'required_if:type,group|nullable|string'
        ]);

        $userIds = $request->users;
        $userIds[] = $request->user()->id;
        $userIds = array_unique($userIds);

        $companyId = $request->user()->company_id;
        if (!$companyId && count($userIds) > 1) {
            $otherUser = User::find($request->users[0]);
            $companyId = $otherUser ? $otherUser->company_id : null;
        }

        // Fallback for demo users or super admins without a company
        if (!$companyId) {
            $companyId = \App\Models\Company::first()->id ?? 1;
        }

        if ($request->type === 'direct' && count($userIds) === 2) {
            $existing = Conversation::where('company_id', $companyId)
                ->where('type', 'direct')
                ->whereHas('users', function ($q) use ($userIds) {
                    $q->whereIn('users.id', $userIds);
                }, '=', 2)
                ->first();

            if ($existing) {
                // Restore if soft-deleted
                $existing->users()->updateExistingPivot($request->user()->id, [
                    'deleted_at' => null
                ]);
                return response()->json(['id' => $existing->id, 'type' => 'direct']);
            }
        }

        $conversation = Conversation::create([
            'company_id' => $companyId,
            'type' => $request->type,
            'name' => $request->name,
        ]);

        $conversation->users()->attach($userIds);

        return response()->json(['id' => $conversation->id, 'type' => $conversation->type]);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        if (!$conversation->users->contains($request->user()->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Mark as read
        $lastMessage = $conversation->messages()->latest()->first();
        if ($lastMessage) {
            $conversation->users()->updateExistingPivot($request->user()->id, [
                'last_read_message_id' => $lastMessage->id
            ]);
        }

        $messages = $conversation->messages()
            ->with(['sender:id,name,profile_photo', 'attachments'])
            ->oldest()
            ->get();

        $result = $messages->map(function ($msg) use ($request) {
            if ($msg->deleted_for_everyone) {
                return [
                    'id' => $msg->id,
                    'is_mine' => $msg->sender_id === $request->user()->id,
                    'sender_name' => $msg->sender ? $msg->sender->name : 'System',
                    'sender_photo' => $msg->sender && $msg->sender->profile_photo ? url('storage/' . $msg->sender->profile_photo) : null,
                    'body' => '🚫 Bu mesaj silindi',
                    'type' => 'system',
                    'time' => $msg->created_at->format('H:i'),
                    'is_read' => true,
                    'is_deleted' => true,
                    'attachments' => []
                ];
            }

            return [
                'id' => $msg->id,
                'is_mine' => $msg->sender_id === $request->user()->id,
                'sender_name' => $msg->sender ? $msg->sender->name : 'System',
                'sender_photo' => $msg->sender && $msg->sender->profile_photo ? url('storage/' . $msg->sender->profile_photo) : null,
                'body' => $msg->body,
                'type' => $msg->type,
                'time' => $msg->created_at->format('H:i'),
                'is_read' => true,
                'is_deleted' => false,
                'attachments' => $msg->attachments->map(function ($att) {
                    return [
                        'id' => $att->id,
                        'filename' => $att->filename,
                        'mime_type' => $att->mime_type,
                        'url' => url('storage/' . $att->path),
                    ];
                })
            ];
        });

        return response()->json($result);
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        if (!$conversation->users->contains($request->user()->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'body' => 'nullable|string',
            'attachments.*' => 'nullable|file|max:10240' // 10MB
        ]);

        if (!$request->body && !$request->hasFile('attachments')) {
            return response()->json(['message' => 'Message is empty'], 422);
        }

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $request->body,
            'type' => $request->hasFile('attachments') ? 'attachment' : 'text',
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('chat_attachments', 'public');
                $message->attachments()->create([
                    'path' => $path,
                    'filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                ]);
            }
        }

        // Auto mark as read for sender
        $conversation->users()->updateExistingPivot($request->user()->id, [
            'last_read_message_id' => $message->id
        ]);

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Exception $e) {
            // Broadcasting may fail silently
        }

        // Send notifications and UserReceivedMessage to all other participants
        $otherUsers = $conversation->users()->where('users.id', '!=', $request->user()->id)->get();
        
        foreach ($otherUsers as $user) {
            try {
                broadcast(new UserReceivedMessage($user->id, $conversation->id))->toOthers();
            } catch (\Exception $e) {}

            // Background Push Notification via Expo
            if (!empty($user->expo_push_token)) {
                $senderName = $request->user()->name;
                $msgBody = $message->type === 'attachment' ? 'Bir dosya gönderdi 📁' : $message->body;
                
                dispatch(new SendFcmpushNotification(
                    $user->id,
                    "Yeni Mesaj: $senderName",
                    $msgBody,
                    [
                        'type' => 'chat',
                        'conversation_id' => $conversation->id
                    ]
                ));
            }
        }

        return response()->json(['status' => 'sent', 'message' => $message->load('attachments')]);
    }

    public function handleCall(Request $request, Conversation $conversation)
    {
        if (!$conversation->users->contains($request->user()->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'type' => 'required|in:video,voice',
            'action' => 'required|in:start,accept,reject,end',
            'room_id' => 'required|string',
        ]);

        $caller = $request->user();
        $action = $request->action;
        $type = $request->type;
        $roomId = $request->room_id;
        
        $otherUsers = $conversation->users()->where('users.id', '!=', $caller->id)->get();

        // Broadcast WebRTC/Jitsi signaling event to all other users
        foreach ($otherUsers as $user) {
            broadcast(new CallEvent($user->id, $conversation->id, $caller->id, $caller->name, $type, $action, $roomId))->toOthers();
        }

        // If starting a call, send push notifications to offline users
        if ($action === 'start') {
            $title = $type === 'video' ? '📹 Görüntülü Arama' : '📞 Sesli Arama';
            $body = "{$caller->name} sizi arıyor...";

            foreach ($otherUsers as $user) {
                if (!empty($user->expo_push_token)) {
                    dispatch(new SendFcmpushNotification(
                        $user->id,
                        $title,
                        $body,
                        [
                            'type' => 'call',
                            'call_type' => $type,
                            'conversation_id' => $conversation->id,
                            'room_id' => $roomId,
                            'caller_name' => $caller->name
                        ]
                    ));
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    // ── Delete a single message ──
    public function deleteMessage(Request $request, Conversation $conversation, Message $message)
    {
        if (!$conversation->users->contains($request->user()->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($message->conversation_id !== $conversation->id) {
            return response()->json(['message' => 'Message not in conversation'], 404);
        }

        $forEveryone = $request->input('for_everyone', false);

        if ($forEveryone) {
            // Only sender can delete for everyone
            if ($message->sender_id !== $request->user()->id) {
                return response()->json(['message' => 'Sadece kendi mesajınızı herkesten silebilirsiniz'], 403);
            }
            $message->update(['deleted_for_everyone' => true, 'body' => null]);
            // Delete attachments
            foreach ($message->attachments as $att) {
                Storage::disk('public')->delete($att->path);
                $att->delete();
            }
        } else {
            // Soft delete just for requesting user (we mark as deleted)
            $message->delete();
        }

        return response()->json(['status' => 'deleted']);
    }

    // ── Delete entire conversation (soft-delete from pivot, don't detach) ──
    public function deleteConversation(Request $request, Conversation $conversation)
    {
        if (!$conversation->users->contains($request->user()->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Soft-delete: set deleted_at on pivot instead of detaching
        $conversation->users()->updateExistingPivot($request->user()->id, [
            'deleted_at' => now()
        ]);

        return response()->json(['status' => 'deleted']);
    }

    // ── Bulk delete conversations ──
    public function bulkDeleteConversations(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $user = $request->user();

        foreach ($request->ids as $convId) {
            $conv = Conversation::find($convId);
            if ($conv && $conv->users->contains($user->id)) {
                $conv->users()->updateExistingPivot($user->id, ['deleted_at' => now()]);
            }
        }

        return response()->json(['status' => 'deleted', 'count' => count($request->ids)]);
    }

    // ── Upload profile photo ──
    public function uploadProfilePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:5120' // 5MB
        ]);

        $user = $request->user();

        // Delete old photo
        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        $path = $request->file('photo')->store('profile_photos', 'public');
        $user->update(['profile_photo' => $path]);

        return response()->json([
            'status' => 'uploaded',
            'profile_photo_url' => url('storage/' . $path)
        ]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $conversations = $user->conversations()->with('users')->get();
        
        $totalUnread = 0;
        foreach ($conversations as $conv) {
            $pivot = $conv->pivot;
            $unreadQuery = Message::where('conversation_id', $conv->id)
                ->where('sender_id', '!=', $user->id);
                
            if ($pivot->last_read_message_id) {
                $unreadQuery->where('id', '>', $pivot->last_read_message_id);
            }
            $totalUnread += $unreadQuery->count();
        }

        return response()->json([
            'count' => $totalUnread,
            'latest' => true
        ]);
    }
}
