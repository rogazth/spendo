<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentMethodRequest;
use App\Http\Requests\UpdatePaymentMethodRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\TransactionResource;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(): Response
    {
        $paymentMethods = Auth::user()
            ->paymentMethods()
            ->with('linkedAccount')
            ->withCount('transactions')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $accounts = Auth::user()->accounts()->where('is_active', true)->get();

        return Inertia::render('payment-methods/index', [
            'paymentMethods' => PaymentMethodResource::collection($paymentMethods),
            'accounts' => AccountResource::collection($accounts),
        ]);
    }

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $isDefault = $validated['is_default'] ?? false;

        if ($isDefault) {
            Auth::user()->paymentMethods()->update(['is_default' => false]);
        }

        Auth::user()->paymentMethods()->create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'linked_account_id' => $validated['linked_account_id'] ?? null,
            'currency' => $validated['currency'] ?? 'CLP',
            'credit_limit' => $validated['credit_limit'] ?? null,
            'billing_cycle_day' => $validated['billing_cycle_day'] ?? null,
            'payment_due_day' => $validated['payment_due_day'] ?? null,
            'color' => $validated['color'] ?? '#10B981',
            'icon' => $validated['icon'] ?? null,
            'last_four_digits' => $validated['last_four_digits'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_default' => $isDefault,
        ]);

        return redirect()
            ->route('payment-methods.index')
            ->with('success', 'Metodo de pago creado exitosamente.');
    }

    public function show(PaymentMethod $paymentMethod): Response
    {
        $this->authorizePaymentMethod($paymentMethod);

        $paymentMethod->load('linkedAccount');

        $transactions = $paymentMethod->transactions()
            ->with(['category', 'paymentMethod'])
            ->latest('transaction_date')
            ->limit(50)
            ->get();

        return Inertia::render('payment-methods/show', [
            'paymentMethod' => new PaymentMethodResource($paymentMethod),
            'transactions' => TransactionResource::collection($transactions),
        ]);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorizePaymentMethod($paymentMethod);

        $validated = $request->validated();
        $isDefault = $validated['is_default'] ?? $paymentMethod->is_default;

        if ($isDefault && ! $paymentMethod->is_default) {
            Auth::user()->paymentMethods()->where('id', '!=', $paymentMethod->id)->update(['is_default' => false]);
        }

        $paymentMethod->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'linked_account_id' => $validated['linked_account_id'] ?? null,
            'currency' => $validated['currency'] ?? $paymentMethod->currency,
            'credit_limit' => $validated['credit_limit'] ?? null,
            'billing_cycle_day' => $validated['billing_cycle_day'] ?? null,
            'payment_due_day' => $validated['payment_due_day'] ?? null,
            'color' => $validated['color'] ?? $paymentMethod->color,
            'icon' => $validated['icon'] ?? $paymentMethod->icon,
            'last_four_digits' => $validated['last_four_digits'] ?? $paymentMethod->last_four_digits,
            'is_active' => $validated['is_active'] ?? $paymentMethod->is_active,
            'is_default' => $isDefault,
        ]);

        return redirect()
            ->route('payment-methods.index')
            ->with('success', 'Metodo de pago actualizado exitosamente.');
    }

    public function makeDefault(PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorizePaymentMethod($paymentMethod);

        if (! $paymentMethod->is_default) {
            Auth::user()->paymentMethods()->where('id', '!=', $paymentMethod->id)->update(['is_default' => false]);
            $paymentMethod->update(['is_default' => true]);
        }

        return back();
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorizePaymentMethod($paymentMethod);

        $paymentMethod->delete();

        return redirect()
            ->route('payment-methods.index')
            ->with('success', 'Metodo de pago eliminado exitosamente.');
    }

    public function toggleActive(PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorizePaymentMethod($paymentMethod);

        $paymentMethod->update([
            'is_active' => ! $paymentMethod->is_active,
        ]);

        return back();
    }

    private function authorizePaymentMethod(PaymentMethod $paymentMethod): void
    {
        if ($paymentMethod->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
