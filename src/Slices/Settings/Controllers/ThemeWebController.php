<?php

namespace LaraSlice\Slices\Settings\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Routing\Controller;

class ThemeWebController extends Controller
{
    /**
     * Render the interactive Theme Studio.
     */
    public function index(): View
    {
        return view('settings::theme');
    }
}
