<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View
    {
        return view('admin.coupons.index', ['coupons' => Coupon::where('auto_apply', false)->whereNull('customer_id')->orderBy('code')->get()]);
    }

    public function create(): View
    {
        return view('admin.coupons.form', ['coupon' => new Coupon]);
    }

    public function edit(Coupon $coupon): View
    {
        abort_if($coupon->auto_apply, 404);

        return view('admin.coupons.form', ['coupon' => $coupon]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new Coupon);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        return $this->save($request, $coupon);
    }

    private function save(Request $request, Coupon $coupon): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'internal_name' => ['required', 'string', 'max:100'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'integer', 'min:0'],
            'min_amount' => ['nullable', 'integer', 'min:0'],
            'max_discount_amount' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'allowed_weekdays' => ['nullable', 'array'],
            'allowed_weekdays.*' => ['integer', 'min:0', 'max:6'],
            'allowed_time_start' => ['nullable'],
            'allowed_time_end' => ['nullable'],
            'max_uses_total' => ['nullable', 'integer', 'min:0'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:0'],
            'is_stackable' => ['sometimes', 'boolean'],
            'requires_verification' => ['sometimes', 'boolean'],
            'verification_note' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['code'] = mb_strtoupper(trim($validated['code']));
        $validated['is_stackable'] = $request->boolean('is_stackable');
        $validated['requires_verification'] = $request->boolean('requires_verification');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['allowed_weekdays'] = $request->filled('allowed_weekdays') ? $validated['allowed_weekdays'] : null;

        $old = $coupon->exists ? $coupon->toArray() : null;
        $coupon->fill($validated)->save();
        AuditLog::record(auth()->id(), $old ? 'cupon.editar' : 'cupon.crear', 'Coupon', $coupon->id, $old, $coupon->toArray());

        return redirect()->route('admin.coupons.index')->with('status', 'Cupón guardado.');
    }
}
