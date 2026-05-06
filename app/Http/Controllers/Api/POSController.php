<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\POSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class POSController extends Controller
{
    public function __construct(private POSService $service) {}

    // ── Products ──────────────────────────────────────────────────────────────

    public function products(Request $request): JsonResponse
    {
        $gymId    = auth()->user()->gym_id;
        $products = $this->service->getProducts($gymId, $request->only(['search', 'per_page']));

        return response()->json(['products' => $products]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'price'          => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $product = $this->service->createProduct($data, auth()->user()->gym_id);

        return response()->json(['message' => 'Product created.', 'product' => $product], 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'price'          => ['sometimes', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $product = $this->service->updateProduct($product, $data);

        return response()->json(['message' => 'Product updated.', 'product' => $product]);
    }

    public function destroyProduct(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }

    // ── Invoices ──────────────────────────────────────────────────────────────

    public function invoices(Request $request): JsonResponse
    {
        $gymId    = auth()->user()->gym_id;
        $invoices = $this->service->getInvoices($gymId, $request->only(['search', 'status', 'per_page']));

        return response()->json(['invoices' => $invoices]);
    }

    public function storeInvoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'              => ['required', 'integer', 'exists:users,id'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.name'         => ['required', 'string', 'max:255'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'items.*.item_type'    => ['nullable', 'in:product,plan,custom'],
            'items.*.item_id'      => ['nullable', 'integer'],
            'discount_amount'      => ['nullable', 'numeric', 'min:0'],
            'tax_amount'           => ['nullable', 'numeric', 'min:0'],
            'notes'                => ['nullable', 'string'],
            'due_date'             => ['nullable', 'date'],
            'pay_now'              => ['nullable', 'boolean'],
            'payment_method'       => ['nullable', 'in:cash,card,bank_transfer,wallet,other'],
        ]);

        $invoice = $this->service->createInvoice($data, auth()->user()->gym_id);

        return response()->json(['message' => 'Invoice created.', 'invoice' => $invoice], 201);
    }

    public function showInvoice(Invoice $invoice): JsonResponse
    {
        return response()->json(['invoice' => $this->service->getInvoice($invoice)]);
    }

    public function markPaid(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'method'           => ['nullable', 'in:cash,card,bank_transfer,wallet,other'],
            'reference_number' => ['nullable', 'string'],
        ]);

        try {
            $invoice = $this->service->markPaid($invoice, $data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Invoice marked as paid.', 'invoice' => $invoice]);
    }

    public function addPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'method'           => ['nullable', 'in:cash,card,bank_transfer,wallet,other'],
            'reference_number' => ['nullable', 'string'],
            'notes'            => ['nullable', 'string'],
        ]);

        try {
            $payment = $this->service->addPayment($invoice, $data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Payment recorded.', 'payment' => $payment], 201);
    }

    public function markUnpaid(Invoice $invoice): JsonResponse
    {
        try {
            $invoice = $this->service->markUnpaid($invoice);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Invoice reverted to unpaid.', 'invoice' => $invoice]);
    }

    public function cancelInvoice(Invoice $invoice): JsonResponse
    {
        try {
            $invoice = $this->service->cancelInvoice($invoice);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Invoice cancelled.', 'invoice' => $invoice]);
    }

    public function destroyInvoice(Invoice $invoice): JsonResponse
    {
        $invoice->delete();

        return response()->json(['message' => 'Invoice deleted.']);
    }

    // ── Revenue ───────────────────────────────────────────────────────────────

    public function revenue(Request $request): JsonResponse
    {
        $summary = $this->service->getRevenueSummary(auth()->user()->gym_id);

        return response()->json(['summary' => $summary]);
    }
}
