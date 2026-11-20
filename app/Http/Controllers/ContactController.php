<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        ContactMessage::create($validated);

        return response()->json(['ok' => true]);
    }

    public function index(Request $request)
    {
        $messages = ContactMessage::orderByDesc('created_at')->get();

        if ($request->wantsJson()) {
            return response()->json($messages->map(fn (ContactMessage $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'subject' => $m->subject,
                'message' => $m->message,
                'created_at' => $m->created_at->toIso8601String(),
            ]));
        }

        return view('admin-messages', compact('messages'));
    }
}
