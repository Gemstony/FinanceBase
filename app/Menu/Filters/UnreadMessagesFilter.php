<?php

namespace App\Menu\Filters;

use JeroenNoten\LaravelAdminLte\Menu\Filters\FilterInterface;
use Illuminate\Contracts\Auth\Guard;

class UnreadMessagesFilter implements FilterInterface
{
    protected $auth;

    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    public function transform($item)
    {
        if (isset($item['label']) && $item['label'] === 'unread_messages_count') {
            $count = $this->auth->check() ? $this->auth->user()->unreadMessagesCount() : 0;
            
            if ($count > 0) {
                $item['label'] = $count;
            } else {
                unset($item['label']);
                unset($item['label_color']);
            }
        }

        return $item;
    }
}
