<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AgreementController extends Controller
{
  public function show(Request $r)
  {
    // versi dokumen & tanggal update bisa kamu ganti kapan saja
    $version      = '1.0';
    $lastUpdated  = '02 Sep 2025';
    return view('legal.agreement', compact('version', 'lastUpdated'));
  }
}
