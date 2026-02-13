<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    protected $fillable = [
        'username', 'email', 'password', 'name', 'picture', 'phone',
        'role', 'preferences', 'is_active', 'department', 'last_login',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'is_active' => 'boolean',
            'last_login' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    public function bookings() { return $this->hasMany(Booking::class); }
    public function vehicles() { return $this->hasMany(Vehicle::class); }
    public function absences() { return $this->hasMany(Absence::class); }
    public function notifications_list() { return $this->hasMany(Notification::class); }
    public function favorites() { return $this->hasMany(Favorite::class); }
    public function recurringBookings() { return $this->hasMany(RecurringBooking::class); }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'superadmin']);
    }
}
