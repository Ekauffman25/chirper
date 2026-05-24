<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(User $user)
    {
        $chirps = $user->chirps()->latest()->with('user')->paginate(20);

        return view('users.show', [
            'user' => $user,
            'chirps' => $chirps,
        ]);
    }
}
