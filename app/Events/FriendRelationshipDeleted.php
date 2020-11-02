<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use App\Models\AppUser;


class FriendRelationshipDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user1;
    public $user2;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(AppUser $user1, AppUser $user2)
    {
        $this->user1 = $user1;
        $this->user2 = $user2;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
