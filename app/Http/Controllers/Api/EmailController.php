<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function sendMail(Request $request)
    {
        dd($request->all());
        Mail::to($request->email)->send(new TestEmail);

        return response()->json(['message' => 'Mail sent successfully!']);
    }
}
