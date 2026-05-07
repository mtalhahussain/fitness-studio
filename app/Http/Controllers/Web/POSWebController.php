<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\User;
use App\Services\POSService;
use Illuminate\Http\Request;

class POSWebController extends Controller
{
    public function __construct(private POSService $service) {}

    public function index(Request $request)
    {
        $gymId    = auth()->user()->gym_id;
        $filters  = $request->only(['search', 'status', 'per_page']);

        $summary  = $this->service->getRevenueSummary($gymId);
        $invoices = $this->service->getInvoices($gymId, $filters);
        $products = Product::forGym($gymId)->active()->orderBy('name')->get(['id', 'name', 'price']);
        $plans    = MembershipPlan::active()->forGym($gymId)->get(['id', 'name', 'price', 'type']);
        $members  = User::members()->forGym($gymId)->where('status', 'active')->get(['id', 'name', 'email']);

        if ($request->wantsJson()) {
            return response()->json([
                'invoices' => $invoices->items(),
                'summary'  => $summary,
            ]);
        }

        return view('pos.index', compact('summary', 'invoices', 'products', 'plans', 'members'));
    }

    public function createInvoice(Request $request)
    {
        $data = $request->validate([
            'user_id'            => ['required', 'integer', 'exists:users,id'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.name'       => ['required', 'string', 'max:255'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.item_type'  => ['nullable', 'in:product,plan,custom'],
            'items.*.item_id'    => ['nullable', 'integer'],
            'discount_amount'    => ['nullable', 'numeric', 'min:0'],
            'tax_amount'         => ['nullable', 'numeric', 'min:0'],
            'notes'              => ['nullable', 'string'],
            'due_date'           => ['nullable', 'date'],
            'pay_now'            => ['nullable', 'boolean'],
            'payment_method'     => ['nullable', 'in:cash,card,bank_transfer,wallet,other'],
        ]);

        $invoice = $this->service->createInvoice($data, auth()->user()->gym_id);

        return response()->json(['message' => 'Invoice created successfully.', 'invoice' => $invoice], 201);
    }

    public function showInvoice(Invoice $invoice)
    {
        return response()->json(['invoice' => $this->service->getInvoice($invoice)]);
    }

    public function markPaid(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'method'           => ['nullable', 'in:cash,card,bank_transfer,wallet,other'],
            'reference_number' => ['nullable', 'string'],
        ]);

        try {
            $this->service->markPaid($invoice, $data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Invoice marked as paid.']);
    }

    public function markUnpaid(Invoice $invoice)
    {
        try {
            $this->service->markUnpaid($invoice);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Invoice reverted to unpaid.']);
    }

    public function addPayment(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'method'           => ['nullable', 'in:cash,card,bank_transfer,wallet,other'],
            'reference_number' => ['nullable', 'string'],
            'notes'            => ['nullable', 'string'],
        ]);

        try {
            $this->service->addPayment($invoice, $data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Payment recorded successfully.']);
    }

    public function cancel(Invoice $invoice)
    {
        try {
            $this->service->cancelInvoice($invoice);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Invoice cancelled.']);
    }

    public function createProduct(Request $request)
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

    public function updateProduct(Request $request, Product $product)
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

    public function deleteProduct(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }

    public function products(Request $request)
    {
        $gymId    = auth()->user()->gym_id;
        $products = $this->service->getProducts($gymId, $request->only(['search', 'per_page']));

        return response()->json(['products' => $products->items()]);
    }

    public function revenue()
    {
        $summary = $this->service->getRevenueSummary(auth()->user()->gym_id);

        return response()->json(['summary' => $summary]);
    }
}
