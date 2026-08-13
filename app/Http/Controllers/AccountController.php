<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function edit(Request $request): Response
    {
        $customer = $this->customer($request);

        return Inertia::render('account/Edit', [
            'customer' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'email_verified' => $customer->hasVerifiedEmail(),
                // A guest row claimed after checkout has no password to confirm against yet.
                'has_password' => ! $customer->isGuest(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers')->ignore($customer->id)],
        ]);

        $emailChanged = $data['email'] !== $customer->email;

        $customer->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            // A new address inherits nothing: it has not been proven yet (SHOP-4).
            'email_verified_at' => $emailChanged ? null : $customer->email_verified_at,
        ])->save();

        if ($emailChanged) {
            $customer->sendEmailVerificationNotification();

            return back()->with('success', 'Check your new address for a verification link.');
        }

        return back()->with('success', 'Your details are saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);

        $request->validate([
            'current_password' => [$customer->isGuest() ? 'nullable' : 'required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! $customer->isGuest() && ! Hash::check($request->string('current_password')->toString(), (string) $customer->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The provided password does not match your current password.'),
            ]);
        }

        $customer->forceFill(['password' => $request->string('password')->toString()])->save();

        return back()->with('success', 'Your password is changed.');
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        return $customer;
    }
}
