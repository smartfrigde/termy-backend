<?php

use Illuminate\Support\Facades\Broadcast;
use \App\Http\Controllers\WebSocket\WsController;

Broadcast::channel("sync.user.{id}", [WsController::class, "syncConnect"]);
