<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;

class ResolveCheckoutCustomerAction
{
    /**
     * The customer an order belongs to, for a buyer who is not logged in.
     *
     * If the address already has an account the order is attached to it, because that is where it
     * belongs and splitting one buyer across two rows helps nobody. The guest never gets a session
     * for that account though, so typing somebody else address buys them a script rather than
     * revealing anything about them.
     */
    public function handle(string $email, string $name): Customer
    {
        $customer = Customer::where('email', $email)->first();

        if ($customer) {
            return $customer;
        }

        return Customer::create([
            'name' => $name,
            'email' => $email,
            'password' => null,
        ]);
    }
}
