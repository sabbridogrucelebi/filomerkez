<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\ChatApiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatUnreadPerformanceTest extends TestCase
{
    public function test_unread_poll_counts_only_visible_messages_in_one_query(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::statement('CREATE TABLE conversations (id INTEGER PRIMARY KEY, company_id INTEGER)');
        DB::statement('CREATE TABLE conversation_user (user_id INTEGER, conversation_id INTEGER, last_read_message_id INTEGER)');
        DB::statement('CREATE TABLE messages (id INTEGER PRIMARY KEY, conversation_id INTEGER, sender_id INTEGER, deleted_at TEXT)');
        DB::statement('INSERT INTO conversations VALUES (1,1),(2,1),(3,2),(4,1)');
        DB::statement('INSERT INTO conversation_user VALUES (7,1,2),(7,2,NULL),(7,3,NULL),(9,4,NULL)');
        DB::statement("INSERT INTO messages VALUES (1,1,9,NULL),(2,1,9,NULL),(3,1,9,NULL),(4,1,7,NULL),(5,1,9,'2026-11-01'),(6,2,9,NULL),(7,3,9,NULL),(8,4,9,NULL)");
        $user = new User(['company_id' => 1]);
        $user->id = 7;
        $this->actingAs($user);
        $request = Request::create('/chat/unread-count');
        $request->setUserResolver(fn () => $user);
        DB::enableQueryLog();
        $response = app(ChatApiController::class)->unreadCount($request);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame(['count' => 2, 'latest' => true], $response->getData(true));
        $this->assertCount(1, $queries);
    }
}
