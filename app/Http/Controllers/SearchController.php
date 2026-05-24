<?php

namespace App\Http\Controllers;

use App\Models\Chirp;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = trim($request->query('query', ''));

        $users = collect();
        $chirps = collect();

        if ($query !== '') {
            $users = User::query()
                ->when(is_numeric($query), fn ($builder) => $builder->where('id', $query))
                ->orWhere('name', 'like', "%{$query}%")
                ->limit(20)
                ->get();

            $chirps = Chirp::with('user')
                ->where('message', 'like', "%{$query}%")
                ->latest()
                ->limit(50)
                ->get();
        }

        return view('search.results', [
            'query' => $query,
            'users' => $users,
            'chirps' => $chirps,
        ]);
    }

    public function suggestions(Request $request)
    {
        $query = trim($request->query('query', ''));

        if ($query === '') {
            return response()->json(['users' => [], 'chirps' => []]);
        }

        $users = User::query()
            ->when(is_numeric($query), fn ($builder) => $builder->where('id', $query))
            ->orWhere('name', 'like', "%{$query}%")
            ->limit(5)
            ->get(['id', 'name', 'email']);

        $chirps = Chirp::with('user')
            ->where('message', 'like', "%{$query}%")
            ->latest()
            ->limit(5)
            ->get(['id', 'message', 'user_id'])
            ->load('user:id,name');

        return response()->json([
            'users' => $users,
            'chirps' => $chirps,
        ]);
    }
}
