<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentDocumentController extends Controller
{
    /** Completar después el N° de voucher/boleta de un pago ya registrado. */
    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'voucher_number' => ['nullable', 'string', 'max:30'],
            'receipt_number' => ['nullable', 'string', 'max:30'],
        ]);
        $validated = array_map(fn ($v) => $v === null || trim($v) === '' ? null : trim($v), $validated);

        $old = $payment->only(['voucher_number', 'receipt_number']);
        $payment->update($validated);
        AuditLog::record(auth()->id(), 'pago.documento', 'Payment', $payment->id, $old, $payment->only(['voucher_number', 'receipt_number']));

        return back()->with('status', 'Documento del pago actualizado.');
    }
}
