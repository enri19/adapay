<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
  public function create(Request $r)
  {
    return view('contact');
  }

  public function store(Request $r)
  {
    $data = $r->validate([
      'name'    => ['required','string','max:100'],
      'email'   => ['required','email','max:120'],
      'subject' => ['required','string','max:150'],
      'message' => ['required','string','max:2000'],
      'hp'      => ['nullable','string','max:30'], // opsional WA/telepon
      'website' => ['nullable','string','max:0'],  // honeypot (tidak diisi manusia)
    ],[
      'website.max' => 'Spam detected.', // kalau ada yang isi honeypot
    ]);

    // simple store ke table contact_messages (buat tabelnya kalau belum)
    DB::table('contact_messages')->insert([
      'name'       => $data['name'],
      'email'      => $data['email'],
      'hp'         => $r->input('hp'),
      'subject'    => $data['subject'],
      'message'    => $data['message'],
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    return back()->with('ok','Pesan kamu sudah kami terima. Kami akan balas di jam kerja.');
  }
}
