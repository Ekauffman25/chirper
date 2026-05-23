<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    //
}

class ChirpController extends Controller
{
    public function index()
    {
        return view('home');
    }
}
