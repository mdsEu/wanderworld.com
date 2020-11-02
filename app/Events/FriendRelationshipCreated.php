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

class FriendRelationshipCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $friend1;
    public $friend2;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(AppUser $friend1, AppUser $friend2)
    {
        $this->friend1 = $friend1;
        $this->friend2 = $friend2;
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
