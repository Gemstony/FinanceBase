<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\SubShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;

class User extends Authenticatable
{
    use HasRoles, AuthenticationLoggable;
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'profile_image',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    /**
     * Get the shop owned by the user
     */
    public function shop()
    {
        return $this->hasOne(Shop::class);
    }

    /**
     * Subshops this user is assigned to (as shopkeeper/staff)
     */
    public function subshops()
    {
        return $this->belongsToMany(SubShop::class, 'subshop_user', 'user_id', 'subshop_id')
            ->withTimestamps()
            ->withPivot(['role', 'permissions', 'is_active']);
    }

    /**
     * Check if user is assigned to a particular subshop
     */
    public function isAssignedToSubshop(int $subshopId): bool
    {
        return $this->subshops()->where('sub_shops.id', $subshopId)->exists();
    }

    /**
     * Check if user has a shop
     */
    public function hasShop()
    {
        return $this->shop()->exists();
    }

    /**
     * Check if user can access a particular subshop
     */
    public function canAccessSubshop(int $subshopId): bool
    {
        // Owner of the parent shop OR assigned via pivot
        $owns = Shop::where('shops.user_id', $this->id)
            ->join('sub_shops', 'sub_shops.shop_id', '=', 'shops.id')
            ->where('sub_shops.id', $subshopId)
            ->exists();

        return $owns || $this->isAssignedToSubshop($subshopId);
    }

    /**
     * Messages sent by this user
     */
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Messages received by this user
     */
    public function receivedMessages()
    {
        return $this->belongsToMany(Message::class, 'message_recipients')
            ->withPivot(['is_read', 'read_at', 'delivery_status'])
            ->withTimestamps();
    }

    /**
     * Unread messages for this user
     */
    public function unreadMessages()
    {
        return $this->receivedMessages()->wherePivot('is_read', false);
    }

    /**
     * Get count of unread messages
     */
    public function unreadMessagesCount(): int
    {
        // Get shop for the user (either owned or through subshop assignment)
        $shop = $this->shop;

        // If user doesn't own a shop, check if they're assigned to subshops
        if (!$shop) {
            $subshop = $this->subshops()->first();
            if ($subshop) {
                $shop = $subshop->shop;
            }
        }

        // Super Admins can see message count even without a shop
        if (!$shop && !$this->hasRole('Super Admin')) {
            return 0;
        }

        $query = MessageRecipient::where('user_id', $this->id)
            ->where('is_read', false);

        // Super Admins don't have soft delete restrictions
        if (!$this->hasRole('Super Admin')) {
            $query->whereNull('deleted_at');
        }

        return $query->count();
    }


      /**
     * Get count of pending expenses
     */
    public function PendingExpensesCount(): int
    {
        // Get shop for the user (either owned or through subshop assignment)
        $shop = $this->shop;
        $subshopId = session('subshop_id');

        // If user doesn't own a shop, check if they're assigned to subshops
        if (!$shop) {
            $subshop = $this->subshops()->first();
            if ($subshop) {
                $shop = $subshop->shop;
            }
        }

        // Super Admins can see message count even without a shop
        if (!$shop && !$this->hasRole('Super Admin')) {
            return 0;
        }

        $query = Expenses::where('status', 'pending')
            ->where('subshop_id', $subshopId);

        // Super Admins don't have soft delete restrictions
        // if (!$this->hasRole('Super Admin')) {
        //     $query->whereNull('deleted_at');
        // }

        return $query->count();
    }


          /**
     * Get count of pending writeoffs
     */
    public function PendingWriteoffsCount(): int
    {
        // Get shop for the user (either owned or through subshop assignment)
        $shop = $this->shop;
        $subshopId = session('subshop_id');

        // If user doesn't own a shop, check if they're assigned to subshops
        if (!$shop) {
            $subshop = $this->subshops()->first();
            if ($subshop) {
                $shop = $subshop->shop;
            }
        }

        // Super Admins can see message count even without a shop
        if (!$shop && !$this->hasRole('Super Admin')) {
            return 0;
        }

        $query = WriteOff::where('status', 'pending')
            ->where('subshop_id', $subshopId);

        // Super Admins don't have soft delete restrictions
        // if (!$this->hasRole('Super Admin')) {
        //     $query->whereNull('deleted_at');
        // }

        return $query->count();
    }
}
