<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PrivacyController extends Controller
{
  public function show(Request $r)
  {
    $version     = '1.0';
    $lastUpdated = '02 Sep 2025';
    return view('legal.privacy', compact('version','lastUpdated'));
  }
}
