<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel("sync.user.{id}", function ($user, $id) {
    return $user->id === $id;
});
