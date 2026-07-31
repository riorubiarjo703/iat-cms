<?php

namespace App\Http\Controllers;

use App\Support\HomepageData;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', ['data' => HomepageData::build()]);
    }
}
