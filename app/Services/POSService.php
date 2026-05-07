<?php

namespace App\Services;

use App\Events\PaymentReceived;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class POSService extends BaseService
{
    // ── Products ──────────────────────────────────────────────────────────────

    public function getProducts(?int $gymId, array $filters = [])
    {
        $q = Product::forGym($gymId)->orderBy('name');

        if (! empty($filters['search'])) {
            $q->where('name', 'like', "%{$filters['search']}%");
        }

        return $q->paginate($filters['per_page'] ?? 20);
    }

    public function createProduct(array $data, ?int $gymId): Product
    {
        return Product::create([
            'gym_id'         => $gymId,
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'price'          => $data['price'],
            'stock_quantity' => $data['stock_quantity'] ?? null,
            'is_active'      => $data['is_active'] ?? true,
        ]);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $product->update(array_filter(
            array_intersect_key($data, array_flip(['name', 'description', 'price', 'stock_quantity', 'is_active'])),
            fn ($v) => ! is_null($v)
        ));

        return $product->fresh();
    }

    // ── Invoices ──────────────────────────────────────────────────────────────

    public function createInvoice(array $data, ?int $gymId): Invoice
    {
        return DB::transaction(function () use ($data, $gymId) {
            $items    = $data['items'] ?? [];
            $subtotal = collect($items)->sum(fn ($i) => ($i['unit_price'] ?? 0) * ($i['quantity'] ?? 1));
            $discount = (float) ($data['discount_amount'] ?? 0);
            $tax      = (float) ($data['tax_amount'] ?? 0);
            $total    = max(0, $subtotal + $tax - $discount);

            $invoice = Invoice::create([
                'gym_id'          => $gymId,
                'user_id'         => $data['user_id'],
                'invoice_number'  => $this->generateInvoiceNumber($gymId),
                'subtotal'        => $subtotal,
                'tax_amount'      => $tax,
                'discount_amount' => $discount,
                'total_amount'    => $total,
                'status'          => 'unpaid',
                'notes'           => $data['notes'] ?? null,
                'due_date'        => ! empty($data['due_date']) ? $data['due_date'] : null,
            ]);

            foreach ($items as $item) {
                $qty   = max(1, (int) ($item['quantity'] ?? 1));
                $price = max(0, (float) ($item['unit_price'] ?? 0));

                $invoice->items()->create([
                    'item_type'  => $item['item_type'] ?? 'custom',
                    'item_id'    => $item['item_id'] ?? null,
                    'name'       => $item['name'],
                    'unit_price' => $price,
                    'quantity'   => $qty,
                    'subtotal'   => $price * $qty,
                ]);
            }

            if (! empty($data['pay_now']) && $total > 0) {
                $this->recordPayment($invoice, [
                    'amount' => $total,
                    'method' => $data['payment_method'] ?? 'cash',
                ]);
            }

            return $invoice->load(['items', 'user:id,name,email,phone', 'payments']);
        });
    }

    public function getInvoices(?int $gymId, array $filters = [])
    {
        $q = Invoice::forGym($gymId)
            ->with(['user:id,name,email'])
            ->withCount('items')
            ->withSum('payments', 'amount')
            ->latest();

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $q->where(fn ($q) => $q
                ->where('invoice_number', 'like', "%{$s}%")
                ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$s}%"))
            );
        }

        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        return $q->paginate($filters['per_page'] ?? 20);
    }

    public function getInvoice(Invoice $invoice): array
    {
        $invoice->load(['user:id,name,email,phone', 'items', 'payments']);

        return array_merge($invoice->toArray(), [
            'amount_paid' => $invoice->amountPaid(),
            'amount_due'  => $invoice->amountDue(),
        ]);
    }

    public function markPaid(Invoice $invoice, array $data = []): Invoice
    {
        if ($invoice->isCancelled()) {
            throw new \RuntimeException('Cannot mark a cancelled invoice as paid.', 422);
        }

        $remaining = $invoice->amountDue();

        if ($remaining > 0) {
            $this->recordPayment($invoice, [
                'amount'           => $remaining,
                'method'           => $data['method'] ?? 'cash',
                'reference_number' => $data['reference_number'] ?? null,
            ]);
        } else {
            $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        }

        return $invoice->fresh(['items', 'user', 'payments']);
    }

    public function markUnpaid(Invoice $invoice): Invoice
    {
        if ($invoice->isCancelled()) {
            throw new \RuntimeException('Cannot revert a cancelled invoice.', 422);
        }

        $invoice->payments()->delete();
        $invoice->update(['status' => 'unpaid', 'paid_at' => null]);

        return $invoice->fresh(['items', 'user']);
    }

    public function cancelInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->isPaid()) {
            throw new \RuntimeException('Cannot cancel a paid invoice. Mark as unpaid first.', 422);
        }

        $invoice->update(['status' => 'cancelled']);

        return $invoice;
    }

    public function addPayment(Invoice $invoice, array $data): Payment
    {
        if ($invoice->isCancelled()) {
            throw new \RuntimeException('Cannot add payment to a cancelled invoice.', 422);
        }
        if ($invoice->isPaid()) {
            throw new \RuntimeException('Invoice is already fully paid.', 422);
        }

        return $this->recordPayment($invoice, $data);
    }

    public function getRevenueSummary(?int $gymId): array
    {
        $pq = fn () => Payment::when($gymId, fn ($q) => $q->where('gym_id', $gymId));
        $iq = fn () => Invoice::when($gymId, fn ($q) => $q->where('gym_id', $gymId));

        return [
            'total_revenue'   => (float) $pq()->sum('amount'),
            'today_revenue'   => (float) $pq()->whereDate('paid_at', today())->sum('amount'),
            'month_revenue'   => (float) $pq()->whereYear('paid_at', now()->year)->whereMonth('paid_at', now()->month)->sum('amount'),
            'unpaid_total'    => (float) $iq()->whereIn('status', ['unpaid', 'partially_paid'])->sum('total_amount'),
            'total_invoices'  => $iq()->count(),
            'paid_invoices'   => $iq()->where('status', 'paid')->count(),
            'unpaid_invoices' => $iq()->whereIn('status', ['unpaid', 'partially_paid'])->count(),
        ];
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function generateInvoiceNumber(?int $gymId): string
    {
        $prefix = 'INV-' . now()->format('Ym');
        $count  = Invoice::when($gymId, fn ($q) => $q->where('gym_id', $gymId))
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->withTrashed()
            ->count() + 1;

        return $prefix . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    private function recordPayment(Invoice $invoice, array $data): Payment
    {
        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'gym_id'           => $invoice->gym_id,
            'amount'           => $data['amount'],
            'method'           => $data['method'] ?? 'cash',
            'reference_number' => $data['reference_number'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'paid_at'          => $data['paid_at'] ?? now(),
        ]);

        $this->syncInvoiceStatus($invoice);

        PaymentReceived::dispatch($payment, $invoice->fresh());

        return $payment;
    }

    private function syncInvoiceStatus(Invoice $invoice): void
    {
        $totalPaid = (float) $invoice->payments()->sum('amount');
        $total     = (float) $invoice->total_amount;

        if ($totalPaid >= $total) {
            $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        } elseif ($totalPaid > 0) {
            $invoice->update(['status' => 'partially_paid', 'paid_at' => null]);
        } else {
            $invoice->update(['status' => 'unpaid', 'paid_at' => null]);
        }
    }
}
