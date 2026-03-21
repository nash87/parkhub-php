<?php

namespace App\Events;

use App\Models\ParkingLot;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OccupancyChangedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly ParkingLot $lot) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('parking.updates'),
            new Channel('lot.'.$this->lot->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'occupancy.changed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $total = $this->lot->total_slots ?? 0;
        $available = $this->lot->available_slots ?? 0;
        $occupied = $total - $available;

        return [
            'lot_id' => $this->lot->id,
            'lot_name' => $this->lot->name,
            'total_slots' => $total,
            'available_slots' => $available,
            'occupied_slots' => $occupied,
            'occupancy_rate' => $total > 0 ? round(($occupied / $total) * 100, 1) : 0,
        ];
    }
}
