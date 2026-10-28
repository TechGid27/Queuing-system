<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Staff console realtime channel: admins or active staff of the department.
Broadcast::channel('queue.{departmentId}.staff', function ($user, $departmentId) {
    if (! $user || ! $user->is_active) {
        return false;
    }

    if ($user->role === 'admin') {
        return true;
    }

    return $user->role === 'staff'
        && (int) $user->department_id === (int) $departmentId;
});
