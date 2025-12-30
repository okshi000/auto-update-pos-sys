<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    /**
     * List all active payment methods.
     */
    public function index(): JsonResponse
    {
        $methods = PaymentMethod::active()
            ->ordered()
            ->get();

        return $this->success(PaymentMethodResource::collection($methods));
    }

    /**
     * Create a new payment method.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:payment_methods,code',
            'type' => 'required|in:cash,card,digital,other',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'requires_reference' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $method = PaymentMethod::create($data);

        return $this->created(
            new PaymentMethodResource($method),
            'Payment method created successfully'
        );
    }

    /**
     * Get payment method details.
     */
    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        return $this->success(new PaymentMethodResource($paymentMethod));
    }

    /**
     * Update a payment method.
     */
    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:payment_methods,code,' . $paymentMethod->id,
            'type' => 'sometimes|in:cash,card,digital,other',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'requires_reference' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $paymentMethod->update($data);

        return $this->success(
            new PaymentMethodResource($paymentMethod),
            'Payment method updated successfully'
        );
    }

    /**
     * Delete a payment method.
     */
    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        // Check if method has payments
        if ($paymentMethod->payments()->exists()) {
            return $this->error('Cannot delete payment method with existing payments', 400);
        }

        $paymentMethod->delete();

        return $this->success(null, 'Payment method deleted successfully');
    }

    /**
     * Get payment method types.
     */
    public function types(): JsonResponse
    {
        return $this->success(PaymentMethod::getTypes());
    }
}
