<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerifyEmailController extends Controller
{
    public function notice(Request $request): Response|RedirectResponse
    {
        if ($request->user('customer')->hasVerifiedEmail()) {
            return redirect()->route('orders.index');
        }

        return Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        abort_unless(hash_equals((string) $request->route('id'), (string) $customer->getKey()), 403);
        abort_unless(hash_equals((string) $request->route('hash'), sha1($customer->getEmailForVerification())), 403);

        if (! $customer->hasVerifiedEmail() && $customer->markEmailAsVerified()) {
            event(new Verified($customer));
        }

        return redirect()->route('orders.index')->with('status', 'email-verified');
    }

    public function send(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        if ($customer->hasVerifiedEmail()) {
            return redirect()->route('orders.index');
        }

        $customer->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
