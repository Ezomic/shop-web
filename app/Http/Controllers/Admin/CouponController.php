<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/coupons/Index', [
            'coupons' => Coupon::latest()->get()->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'type' => $c->type,
                'amount' => $c->amount,
                'uses_count' => $c->uses_count,
                'max_uses' => $c->max_uses,
                'expires_at' => $c->expires_at?->toDateString(),
                'active' => $c->active,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/coupons/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons'],
            'type' => ['required', 'in:percent,fixed'],
            'amount' => ['required', 'integer', 'min:1'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'active' => ['boolean'],
        ]);

        Coupon::create(array_merge($data, ['code' => strtoupper($data['code'])]));

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created.');
    }

    public function edit(Coupon $coupon): Response
    {
        return Inertia::render('admin/coupons/Edit', ['coupon' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code,'.$coupon->id],
            'type' => ['required', 'in:percent,fixed'],
            'amount' => ['required', 'integer', 'min:1'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'active' => ['boolean'],
        ]);

        $coupon->update(array_merge($data, ['code' => strtoupper($data['code'])]));

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon deleted.');
    }
}
