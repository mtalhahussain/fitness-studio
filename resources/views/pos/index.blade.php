@extends('layouts.app')
@section('title', 'Point of Sale')

@section('content')
<div x-data="posPage()" x-init="init()">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <div class="page-title">Point of Sale</div>
            <div class="page-sub">Invoices, payments & product catalog</div>
        </div>
        <button class="btn btn-primary" @click="openNewInvoice()">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Invoice
        </button>
    </div>

    {{-- Revenue Stats --}}
    <div class="stat-grid" style="margin-bottom:24px;grid-template-columns:repeat(4,1fr)">
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--success-dim)">💰</div>
            <div class="stat-content">
                <div class="label">Total Revenue</div>
                <div class="value" x-text="currency(summary.total_revenue)" style="color:var(--success)"></div>
                <div class="change up" x-text="summary.paid_invoices + ' invoices paid'"></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--info-dim)">📅</div>
            <div class="stat-content">
                <div class="label">Today</div>
                <div class="value" x-text="currency(summary.today_revenue)"></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--primary-dim)">📆</div>
            <div class="stat-content">
                <div class="label">This Month</div>
                <div class="value" x-text="currency(summary.month_revenue)"></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--warning-dim)">⏳</div>
            <div class="stat-content">
                <div class="label">Unpaid</div>
                <div class="value" x-text="currency(summary.unpaid_total)" style="color:var(--warning)"></div>
                <div class="change warn" x-text="summary.unpaid_invoices + ' pending'"></div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div style="display:flex;gap:4px;margin-bottom:16px;background:rgba(255,255,255,0.03);border-radius:10px;padding:4px;width:fit-content">
        <button type="button" class="btn btn-sm" @click="tab='invoices'"
            :style="tab==='invoices' ? 'background:var(--primary);color:#fff' : 'background:transparent;color:var(--text-muted)'">
            🧾 Invoices
        </button>
        <button type="button" class="btn btn-sm" @click="tab='products'; loadProducts()"
            :style="tab==='products' ? 'background:var(--primary);color:#fff' : 'background:transparent;color:var(--text-muted)'">
            📦 Products
        </button>
    </div>

    {{-- ═══ INVOICES TAB ═══ --}}
    <div x-show="tab === 'invoices'">
        <div class="toolbar">
            <div class="search-wrap" style="flex:1;max-width:300px">
                <svg class="search-icon" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="form-input search-input" placeholder="Search invoice or member..." x-model="search" @input.debounce.400ms="load()">
            </div>
            <select class="form-select" style="width:150px" x-model="statusFilter" @change="load()">
                <option value="">All Statuses</option>
                <option value="unpaid">Unpaid</option>
                <option value="partially_paid">Partial</option>
                <option value="paid">Paid</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <button class="btn btn-outline" @click="load()">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            </button>
        </div>

        <div class="card" style="padding:0">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Member</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="loading">
                            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)"><span class="spinner"></span></td></tr>
                        </template>
                        <template x-if="!loading && invoices.length === 0">
                            <tr><td colspan="8"><div class="empty-state"><div class="icon">🧾</div><p>No invoices yet</p></div></td></tr>
                        </template>
                        <template x-for="inv in invoices" :key="inv.id">
                            <tr>
                                <td>
                                    <span class="cell-main" style="font-family:monospace;font-size:12px;color:var(--primary);cursor:pointer" @click="openView(inv)" x-text="inv.invoice_number"></span>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <div class="avatar" :style="`background:${avatarBg(inv.user?.name||'?')}`" x-text="initials(inv.user?.name||'?')" style="width:28px;height:28px;font-size:10px"></div>
                                        <div>
                                            <div class="cell-main" x-text="inv.user?.name || '—'"></div>
                                            <div class="cell-sub" x-text="inv.user?.email || ''"></div>
                                        </div>
                                    </div>
                                </td>
                                <td x-text="fmtDate(inv.created_at)" style="white-space:nowrap"></td>
                                <td>
                                    <span class="badge badge-gray" x-text="inv.items_count + (inv.items_count === 1 ? ' item' : ' items')"></span>
                                </td>
                                <td>
                                    <span class="cell-main" x-text="currency(inv.total_amount)"></span>
                                </td>
                                <td>
                                    <span x-text="currency(inv.payments_sum_amount || 0)" style="color:var(--success);font-size:13px;font-weight:500"></span>
                                </td>
                                <td>
                                    <span class="badge" :class="statusClass(inv.status)" x-text="statusLabel(inv.status)"></span>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:flex;gap:6px;justify-content:flex-end">
                                        <button class="btn btn-outline btn-sm" @click="openView(inv)" title="View">
                                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                        <button x-show="inv.status === 'unpaid' || inv.status === 'partially_paid'"
                                            class="btn btn-success btn-sm" @click="quickMarkPaid(inv)">Pay</button>
                                        <button x-show="inv.status !== 'cancelled' && inv.status !== 'paid'"
                                            class="btn btn-danger btn-sm" @click="cancelInvoice(inv)">Cancel</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ PRODUCTS TAB ═══ --}}
    <div x-show="tab === 'products'">
        <div class="toolbar">
            <div class="search-wrap" style="flex:1;max-width:300px">
                <svg class="search-icon" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="form-input search-input" placeholder="Search products..." x-model="productSearch" @input.debounce.400ms="loadProducts()">
            </div>
            <button class="btn btn-primary" @click="openNewProduct()">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Product
            </button>
        </div>

        <div class="card" style="padding:0">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="products.length === 0">
                            <tr><td colspan="6"><div class="empty-state"><div class="icon">📦</div><p>No products yet</p></div></td></tr>
                        </template>
                        <template x-for="p in products" :key="p.id">
                            <tr>
                                <td><div class="cell-main" x-text="p.name"></div></td>
                                <td><div class="cell-sub" x-text="p.description || '—'" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></div></td>
                                <td><span class="cell-main" x-text="currency(p.price)"></span></td>
                                <td x-text="p.stock_quantity !== null ? p.stock_quantity : 'Unlimited'"></td>
                                <td>
                                    <span class="badge" :class="p.is_active ? 'badge-green' : 'badge-gray'" x-text="p.is_active ? 'Active' : 'Inactive'"></span>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:flex;gap:6px;justify-content:flex-end">
                                        <button class="btn btn-outline btn-sm" @click="openEditProduct(p)">Edit</button>
                                        <button class="btn btn-danger btn-sm" @click="deleteProduct(p)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════ MODALS ══════════════════════════════════════════ --}}

    {{-- NEW INVOICE MODAL --}}
    <div class="modal-overlay" x-show="newInvoiceModal" x-transition @click.self="newInvoiceModal=false" style="display:none">
        <div class="modal modal-lg" @click.stop style="max-width:760px">
            <div class="modal-header">
                <div class="modal-title">New Invoice</div>
                <button class="modal-close" @click="newInvoiceModal=false">×</button>
            </div>

            {{-- Customer --}}
            <div class="form-group" style="margin-bottom:18px">
                <label class="form-label">Customer / Member *</label>
                <select class="form-select" x-model="invoiceForm.user_id">
                    <option value="">Select a member...</option>
                    @foreach($members as $m)
                    <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->email }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Quick Add from Catalog --}}
            <div style="margin-bottom:14px">
                <label class="form-label" style="margin-bottom:6px;display:block">Quick Add from Catalog</label>
                <select class="form-select" @change="quickAdd($event)">
                    <option value="">+ Add product or plan...</option>
                    @if($products->count())
                    <optgroup label="Products">
                        @foreach($products as $p)
                        <option value="{{ json_encode(['type'=>'product','id'=>$p->id,'name'=>$p->name,'price'=>$p->price]) }}">{{ $p->name }} — PKR {{ number_format($p->price, 2) }}</option>
                        @endforeach
                    </optgroup>
                    @endif
                    @if($plans->count())
                    <optgroup label="Membership Plans">
                        @foreach($plans as $p)
                        <option value="{{ json_encode(['type'=>'plan','id'=>$p->id,'name'=>$p->name.' ('.ucfirst($p->type).')','price'=>$p->price]) }}">{{ $p->name }} ({{ ucfirst($p->type) }}) — PKR {{ number_format($p->price, 2) }}</option>
                        @endforeach
                    </optgroup>
                    @endif
                </select>
            </div>

            {{-- Items Table --}}
            <div style="margin-bottom:16px">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                    <label class="form-label">Line Items *</label>
                    <button type="button" class="btn btn-outline btn-sm" @click="addItem()">+ Add Row</button>
                </div>

                <template x-if="invoiceForm.items.length === 0">
                    <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px;background:rgba(255,255,255,0.02);border-radius:8px;border:1px dashed var(--border)">
                        Use catalog dropdown above or click "Add Row" to add items
                    </div>
                </template>

                <template x-if="invoiceForm.items.length > 0">
                    <div>
                        {{-- Header --}}
                        <div style="display:grid;grid-template-columns:1fr 70px 100px 80px 28px;gap:6px;padding:0 4px;margin-bottom:4px">
                            <div class="form-label">Description</div>
                            <div class="form-label" style="text-align:center">Qty</div>
                            <div class="form-label" style="text-align:right">Unit Price</div>
                            <div class="form-label" style="text-align:right">Subtotal</div>
                            <div></div>
                        </div>
                        {{-- Rows --}}
                        <template x-for="(item, idx) in invoiceForm.items" :key="idx">
                            <div style="display:grid;grid-template-columns:1fr 70px 100px 80px 28px;gap:6px;margin-bottom:6px;align-items:center">
                                <input class="form-input" x-model="item.name" placeholder="Item description..." style="font-size:12px;padding:7px 10px">
                                <input class="form-input" type="number" min="1" x-model="item.quantity" style="text-align:center;font-size:12px;padding:7px 6px">
                                <input class="form-input" type="number" min="0" step="0.01" x-model="item.unit_price" placeholder="0.00" style="text-align:right;font-size:12px;padding:7px 10px">
                                <div style="text-align:right;font-size:13px;font-weight:600;color:var(--text-dim);padding:7px 4px" x-text="currency(parseFloat(item.unit_price||0) * parseInt(item.quantity||1))"></div>
                                <button type="button" @click="removeItem(idx)" style="width:24px;height:24px;border-radius:6px;border:none;background:rgba(239,68,68,0.1);color:var(--error);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0">×</button>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Totals + Options row --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:16px">
                <div style="display:flex;flex-direction:column;gap:12px">
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea class="form-textarea" x-model="invoiceForm.notes" rows="2" placeholder="Optional notes..." style="resize:none"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Date</label>
                        <input class="form-input" type="date" x-model="invoiceForm.due_date">
                    </div>
                </div>

                <div style="background:rgba(255,255,255,0.03);border-radius:10px;padding:14px;border:1px solid var(--border)">
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px">
                        <span style="color:var(--text-muted)">Subtotal</span>
                        <span x-text="currency(invoiceSubtotal)" style="color:var(--text-dim)"></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:13px">
                        <span style="color:var(--text-muted)">Discount</span>
                        <div style="display:flex;align-items:center;gap:6px">
                            <span style="color:var(--text-muted);font-size:11px;white-space:nowrap">PKR</span>
                            <input type="number" min="0" step="0.01" x-model="invoiceForm.discount_amount" class="form-input" style="width:80px;padding:4px 8px;font-size:12px;text-align:right">
                        </div>
                    </div>
                    <div style="border-top:1px solid var(--border);padding-top:10px;margin-top:4px">
                        <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700">
                            <span>Total</span>
                            <span x-text="currency(invoiceTotal)" style="color:var(--primary)"></span>
                        </div>
                    </div>
                    <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
                            <input type="checkbox" x-model="invoiceForm.pay_now" style="accent-color:var(--success);width:15px;height:15px">
                            <span style="color:var(--text-dim);font-weight:500">Mark as paid now</span>
                        </label>
                        <template x-if="invoiceForm.pay_now">
                            <select class="form-select" x-model="invoiceForm.payment_method" style="margin-top:8px;font-size:12px">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="wallet">Wallet</option>
                                <option value="other">Other</option>
                            </select>
                        </template>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" @click="newInvoiceModal=false">Cancel</button>
                <button type="button" class="btn btn-primary" :disabled="saving || !invoiceForm.user_id || invoiceForm.items.length===0" @click="saveInvoice()">
                    <span x-show="saving" class="spinner"></span>
                    <span x-text="invoiceForm.pay_now ? 'Create & Mark Paid' : 'Create Invoice'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- VIEW INVOICE MODAL --}}
    <div class="modal-overlay" x-show="viewModal" x-transition @click.self="viewModal=false" style="display:none">
        <div class="modal modal-lg" @click.stop style="max-width:680px">
            <template x-if="activeInvoice">
                <div>
                    <div class="modal-header" style="margin-bottom:14px">
                        <div>
                            <div class="modal-title" x-text="activeInvoice.invoice_number" style="font-family:monospace"></div>
                            <div style="font-size:12px;color:var(--text-muted);margin-top:3px" x-text="fmtDate(activeInvoice.created_at)"></div>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px">
                            <span class="badge" :class="statusClass(activeInvoice.status)" x-text="statusLabel(activeInvoice.status)"></span>
                            <button class="modal-close" @click="viewModal=false">×</button>
                        </div>
                    </div>

                    {{-- Customer row --}}
                    <div style="background:rgba(255,255,255,0.03);border-radius:8px;padding:12px 14px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
                        <div class="avatar" :style="`background:${avatarBg(activeInvoice.user?.name||'?')}`" x-text="initials(activeInvoice.user?.name||'?')"></div>
                        <div>
                            <div style="font-size:13px;font-weight:600" x-text="activeInvoice.user?.name"></div>
                            <div style="font-size:11px;color:var(--text-muted)" x-text="activeInvoice.user?.email"></div>
                        </div>
                        <div style="margin-left:auto;text-align:right" x-show="activeInvoice.due_date">
                            <div style="font-size:11px;color:var(--text-muted)">Due Date</div>
                            <div style="font-size:12px;font-weight:600" x-text="fmtDate(activeInvoice.due_date)"></div>
                        </div>
                    </div>

                    {{-- Items --}}
                    <table style="width:100%;border-collapse:collapse;margin-bottom:14px">
                        <thead>
                            <tr style="border-bottom:1px solid var(--border)">
                                <th style="text-align:left;padding:6px 0;font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px">Item</th>
                                <th style="text-align:center;padding:6px 0;font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px">Qty</th>
                                <th style="text-align:right;padding:6px 0;font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px">Price</th>
                                <th style="text-align:right;padding:6px 0;font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="item in activeInvoice.items" :key="item.id">
                                <tr style="border-bottom:1px solid var(--border)">
                                    <td style="padding:10px 0">
                                        <div style="font-size:13px;font-weight:500" x-text="item.name"></div>
                                        <div style="font-size:11px;color:var(--text-muted)" x-text="item.item_type !== 'custom' ? item.item_type : ''"></div>
                                    </td>
                                    <td style="text-align:center;padding:10px 0;color:var(--text-dim)" x-text="item.quantity"></td>
                                    <td style="text-align:right;padding:10px 0;color:var(--text-dim)" x-text="currency(item.unit_price)"></td>
                                    <td style="text-align:right;padding:10px 0;font-weight:600" x-text="currency(item.subtotal)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    {{-- Totals --}}
                    <div style="margin-left:auto;width:240px;margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
                            <span style="color:var(--text-muted)">Subtotal</span>
                            <span x-text="currency(activeInvoice.subtotal)"></span>
                        </div>
                        <template x-if="parseFloat(activeInvoice.discount_amount) > 0">
                            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
                                <span style="color:var(--text-muted)">Discount</span>
                                <span style="color:var(--error)" x-text="'−' + currency(activeInvoice.discount_amount)"></span>
                            </div>
                        </template>
                        <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700;padding-top:8px;border-top:1px solid var(--border)">
                            <span>Total</span>
                            <span x-text="currency(activeInvoice.total_amount)" style="color:var(--text)"></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:8px">
                            <span style="color:var(--success)">Paid</span>
                            <span style="color:var(--success)" x-text="currency(activeInvoice.amount_paid)"></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:700;margin-top:4px">
                            <span style="color:var(--warning)">Due</span>
                            <span style="color:var(--warning)" x-text="currency(activeInvoice.amount_due)"></span>
                        </div>
                    </div>

                    {{-- Payment History --}}
                    <template x-if="activeInvoice.payments && activeInvoice.payments.length > 0">
                        <div style="margin-bottom:16px">
                            <div style="font-size:11px;font-weight:700;letter-spacing:.08em;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px">Payment History</div>
                            <template x-for="pmt in activeInvoice.payments" :key="pmt.id">
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:rgba(34,197,94,0.05);border-radius:8px;border:1px solid rgba(34,197,94,0.15);margin-bottom:6px">
                                    <div>
                                        <span class="badge badge-gray" x-text="methodLabel(pmt.method)" style="font-size:10px"></span>
                                        <span style="font-size:11px;color:var(--text-muted);margin-left:8px" x-text="fmtDate(pmt.paid_at)"></span>
                                    </div>
                                    <span style="font-size:14px;font-weight:700;color:var(--success)" x-text="currency(pmt.amount)"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Notes --}}
                    <template x-if="activeInvoice.notes">
                        <div style="background:rgba(255,255,255,0.03);border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:12px;color:var(--text-muted)">
                            <span style="font-weight:600;color:var(--text-dim)">Note: </span>
                            <span x-text="activeInvoice.notes"></span>
                        </div>
                    </template>

                    {{-- Modal Footer Actions --}}
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" @click="viewModal=false">Close</button>
                        <template x-if="activeInvoice.status === 'paid'">
                            <button type="button" class="btn btn-outline" @click="doMarkUnpaid(activeInvoice)">Mark Unpaid</button>
                        </template>
                        <template x-if="activeInvoice.status !== 'cancelled' && activeInvoice.status !== 'paid'">
                            <button type="button" class="btn btn-outline" @click="openPaymentModal(activeInvoice)">Add Payment</button>
                        </template>
                        <template x-if="activeInvoice.status === 'unpaid' || activeInvoice.status === 'partially_paid'">
                            <button type="button" class="btn btn-success" @click="quickMarkPaid(activeInvoice); viewModal=false">Mark Paid</button>
                        </template>
                        <template x-if="activeInvoice.status !== 'cancelled' && activeInvoice.status !== 'paid'">
                            <button type="button" class="btn btn-danger" @click="cancelInvoice(activeInvoice); viewModal=false">Cancel Invoice</button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ADD PAYMENT MODAL --}}
    <div class="modal-overlay" x-show="paymentModal" x-transition @click.self="paymentModal=false" style="display:none">
        <div class="modal" @click.stop style="max-width:420px">
            <div class="modal-header">
                <div class="modal-title">Record Payment</div>
                <button class="modal-close" @click="paymentModal=false">×</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:14px">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Amount *</label>
                        <input class="form-input" type="number" min="0.01" step="0.01" x-model="paymentForm.amount" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Method</label>
                        <select class="form-select" x-model="paymentForm.method">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="wallet">Wallet</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Reference # <span style="color:var(--text-muted)">(optional)</span></label>
                    <input class="form-input" type="text" x-model="paymentForm.reference_number" placeholder="e.g. TXN-12345">
                </div>
                <div class="form-group">
                    <label class="form-label">Notes <span style="color:var(--text-muted)">(optional)</span></label>
                    <input class="form-input" type="text" x-model="paymentForm.notes" placeholder="Any notes...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" @click="paymentModal=false">Cancel</button>
                <button type="button" class="btn btn-success" :disabled="saving || !paymentForm.amount" @click="recordPayment()">
                    <span x-show="saving" class="spinner"></span>
                    Record Payment
                </button>
            </div>
        </div>
    </div>

    {{-- PRODUCT MODAL --}}
    <div class="modal-overlay" x-show="productModal" x-transition @click.self="productModal=false" style="display:none">
        <div class="modal" @click.stop style="max-width:480px">
            <div class="modal-header">
                <div class="modal-title" x-text="productForm.id ? 'Edit Product' : 'New Product'"></div>
                <button class="modal-close" @click="productModal=false">×</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:14px">
                <div class="form-group">
                    <label class="form-label">Name *</label>
                    <input class="form-input" x-model="productForm.name" placeholder="Product name">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-textarea" x-model="productForm.description" rows="2" placeholder="Optional description" style="resize:none"></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Price *</label>
                        <input class="form-input" type="number" min="0" step="0.01" x-model="productForm.price" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock <span style="color:var(--text-muted)">(blank = unlimited)</span></label>
                        <input class="form-input" type="number" min="0" x-model="productForm.stock_quantity" placeholder="Leave blank for unlimited">
                    </div>
                </div>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" x-model="productForm.is_active" style="accent-color:var(--primary);width:15px;height:15px">
                    <span style="font-size:13px;color:var(--text-dim);font-weight:500">Active (visible in catalog)</span>
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" @click="productModal=false">Cancel</button>
                <button type="button" class="btn btn-primary" :disabled="saving || !productForm.name || !productForm.price" @click="saveProduct()">
                    <span x-show="saving" class="spinner"></span>
                    <span x-text="productForm.id ? 'Save Changes' : 'Create Product'"></span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function posPage() {
    return {
        tab: 'invoices',
        invoices: [],
        products: [],
        summary: {},
        loading: false,
        saving: false,

        // Filters
        search: '',
        statusFilter: '',
        productSearch: '',

        // Modals
        newInvoiceModal: false,
        viewModal: false,
        paymentModal: false,
        productModal: false,
        activeInvoice: null,

        // Forms
        invoiceForm: { user_id: '', notes: '', due_date: '', discount_amount: 0, pay_now: false, payment_method: 'cash', items: [] },
        paymentForm: { amount: '', method: 'cash', reference_number: '', notes: '' },
        productForm: { id: null, name: '', description: '', price: '', stock_quantity: '', is_active: true },

        // Computed
        get invoiceSubtotal() {
            return this.invoiceForm.items.reduce((s, i) => s + (parseFloat(i.unit_price) || 0) * (parseInt(i.quantity) || 1), 0);
        },
        get invoiceTotal() {
            return Math.max(0, this.invoiceSubtotal - (parseFloat(this.invoiceForm.discount_amount) || 0));
        },

        init() {
            this.invoices = @json($invoices->items());
            this.summary  = @json($summary);
        },

        // ── Load ──────────────────────────────────────────────────────────────

        async load() {
            this.loading = true;
            try {
                const p = new URLSearchParams();
                if (this.search)       p.set('search', this.search);
                if (this.statusFilter) p.set('status', this.statusFilter);
                const res = await get(`/pos?${p}`);
                this.invoices = res.invoices;
                this.summary  = res.summary;
            } catch(e) { toast('Refresh failed', 'error'); }
            this.loading = false;
        },

        async loadProducts() {
            try {
                const p = new URLSearchParams();
                if (this.productSearch) p.set('search', this.productSearch);
                const res = await get(`/pos/products?${p}`);
                this.products = res.products;
            } catch(e) { toast('Failed to load products', 'error'); }
        },

        // ── Invoice ───────────────────────────────────────────────────────────

        openNewInvoice() {
            this.invoiceForm = { user_id: '', notes: '', due_date: '', discount_amount: 0, pay_now: false, payment_method: 'cash', items: [] };
            this.newInvoiceModal = true;
        },

        quickAdd(event) {
            const val = event.target.value;
            if (!val) return;
            try {
                const item = JSON.parse(val);
                this.invoiceForm.items.push({ name: item.name, quantity: 1, unit_price: item.price, item_type: item.type, item_id: item.id });
            } catch(e) {}
            event.target.value = '';
        },

        addItem() {
            this.invoiceForm.items.push({ name: '', quantity: 1, unit_price: '', item_type: 'custom', item_id: null });
        },

        removeItem(idx) {
            this.invoiceForm.items.splice(idx, 1);
        },

        async saveInvoice() {
            if (!this.invoiceForm.user_id || this.invoiceForm.items.length === 0) {
                toast('Select a member and add at least one item.', 'warning');
                return;
            }
            this.saving = true;
            try {
                const payload = {
                    ...this.invoiceForm,
                    items: this.invoiceForm.items.map(i => ({
                        ...i,
                        unit_price: parseFloat(i.unit_price) || 0,
                        quantity:   parseInt(i.quantity) || 1,
                    })),
                };
                const res = await post('/pos/invoices', payload);
                toast(res.message, 'success');
                this.newInvoiceModal = false;
                await this.load();
            } catch(e) { toast(e.message || 'Failed to create invoice', 'error'); }
            this.saving = false;
        },

        async openView(inv) {
            try {
                const res = await get(`/pos/invoices/${inv.id}`);
                this.activeInvoice = res.invoice;
                this.viewModal = true;
            } catch(e) { toast('Failed to load invoice', 'error'); }
        },

        async quickMarkPaid(inv) {
            try {
                const res = await post(`/pos/invoices/${inv.id}/pay`, { method: 'cash' });
                toast(res.message, 'success');
                await this.load();
            } catch(e) { toast(e.message, 'error'); }
        },

        async doMarkUnpaid(inv) {
            try {
                const res = await post(`/pos/invoices/${inv.id}/unpay`, {});
                toast(res.message, 'success');
                this.viewModal = false;
                await this.load();
            } catch(e) { toast(e.message, 'error'); }
        },

        openPaymentModal(inv) {
            this.activeInvoice = inv;
            const paid = parseFloat(inv.payments_sum_amount || inv.amount_paid || 0);
            const due  = Math.max(0, parseFloat(inv.total_amount) - paid);
            this.paymentForm = { amount: due.toFixed(2), method: 'cash', reference_number: '', notes: '' };
            this.paymentModal = true;
        },

        async recordPayment() {
            if (!this.paymentForm.amount) { toast('Enter payment amount', 'warning'); return; }
            this.saving = true;
            try {
                const res = await post(`/pos/invoices/${this.activeInvoice.id}/payment`, this.paymentForm);
                toast(res.message, 'success');
                this.paymentModal = false;
                await this.load();
            } catch(e) { toast(e.message, 'error'); }
            this.saving = false;
        },

        async cancelInvoice(inv) {
            try {
                const res = await post(`/pos/invoices/${inv.id}/cancel`, {});
                toast(res.message, 'success');
                await this.load();
            } catch(e) { toast(e.message, 'error'); }
        },

        // ── Products ──────────────────────────────────────────────────────────

        openNewProduct() {
            this.productForm = { id: null, name: '', description: '', price: '', stock_quantity: '', is_active: true };
            this.productModal = true;
        },

        openEditProduct(p) {
            this.productForm = { id: p.id, name: p.name, description: p.description || '', price: p.price, stock_quantity: p.stock_quantity !== null ? p.stock_quantity : '', is_active: p.is_active };
            this.productModal = true;
        },

        async saveProduct() {
            if (!this.productForm.name || !this.productForm.price) { toast('Name and price are required.', 'warning'); return; }
            this.saving = true;
            try {
                if (this.productForm.id) {
                    await put(`/pos/products/${this.productForm.id}`, this.productForm);
                    toast('Product updated.', 'success');
                } else {
                    await post('/pos/products', this.productForm);
                    toast('Product created.', 'success');
                }
                this.productModal = false;
                await this.loadProducts();
            } catch(e) { toast(e.message, 'error'); }
            this.saving = false;
        },

        async deleteProduct(p) {
            try {
                await del(`/pos/products/${p.id}`);
                toast('Product deleted.', 'success');
                await this.loadProducts();
            } catch(e) { toast(e.message, 'error'); }
        },

        // ── Helpers ───────────────────────────────────────────────────────────

        statusClass(s) {
            return { paid: 'badge-green', unpaid: 'badge-yellow', partially_paid: 'badge-blue', cancelled: 'badge-gray' }[s] || 'badge-gray';
        },
        statusLabel(s) {
            return { paid: 'Paid', unpaid: 'Unpaid', partially_paid: 'Partial', cancelled: 'Cancelled' }[s] || s;
        },
        methodLabel(m) {
            return { cash: 'Cash', card: 'Card', bank_transfer: 'Bank', wallet: 'Wallet', other: 'Other' }[m] || m;
        },
        fmt(n) { return parseFloat(n || 0).toFixed(2); },
        fmtDate(d) {
            if (!d) return '—';
            return new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        },
    };
}
</script>
@endpush
