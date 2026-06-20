@extends('layouts.app')
@section('title', 'WhatsApp Reminders')

@section('content')
<div x-data="waReminderPage()" x-init="init()">
    <div class="page-header">
        <div>
            <div class="page-title">WhatsApp Reminders</div>
            <div class="page-sub">{{ $gym->name }} · Current month due invoices with quick multi-send</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <div>
                <div class="card-title">Reminder Message Format</div>
                <div class="card-subtitle">This same format is used for scheduled job and manual send.</div>
            </div>
            <button class="btn btn-primary" @click="saveTemplate()" :disabled="savingTemplate">
                <span x-show="savingTemplate" class="spinner"></span>
                Save Format
            </button>
        </div>
        <div style="padding:16px">
            <label class="form-label">Template Text</label>
            <textarea class="form-input" rows="4" x-model="templateText"></textarea>
            <div style="font-size:12px;color:var(--text-muted);margin-top:8px">Allowed placeholders: {name}, {invoice_number}, {amount_due}, {due_date}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Due Members</div>
            <div style="display:flex;gap:8px;align-items:center">
                <button class="btn btn-outline btn-sm" @click="toggleAll()" x-text="allChecked ? 'Unselect All' : 'Select All'"></button>
                <button class="btn btn-primary btn-sm" @click="sendSelected()" :disabled="selectedIds.length===0 || sending">
                    <span x-show="sending" class="spinner"></span>
                    Send Reminder (<span x-text="selectedIds.length"></span>)
                </button>
            </div>
        </div>

        <div class="toolbar" style="padding:12px 16px;display:grid;grid-template-columns:160px 140px 1fr auto;gap:10px;align-items:end">
            <div class="form-group" style="margin:0">
                <label class="form-label">Month</label>
                <input type="month" class="form-input" x-model="filters.month">
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">Due Status</label>
                <select class="form-select" x-model="filters.status">
                    <option value="all">All</option>
                    <option value="today">Today</option>
                    <option value="overdue">Overdue</option>
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">Search</label>
                <input type="text" class="form-input" placeholder="Member name / phone / invoice" x-model="filters.search">
            </div>
            <button class="btn btn-outline" @click="applyFilters()">Apply</button>
        </div>

        <div class="table-wrap" style="padding:0 16px 16px">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px"></th>
                        <th>Member</th>
                        <th>Phone</th>
                        <th>Invoice</th>
                        <th>Due Date</th>
                        <th>Amount Due</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr>
                        <td><input type="checkbox" value="{{ $invoice->id }}" x-model="selectedIds"></td>
                        <td>{{ $invoice->user?->name }}</td>
                        <td>{{ $invoice->user?->phone }}</td>
                        <td>{{ $invoice->invoice_number }}</td>
                        <td>{{ optional($invoice->due_date)->format('d-M-Y') }}</td>
                        <td>PKR {{ number_format($invoice->amountDue(), 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--text-muted)">No due members found for selected filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function waReminderPage() {
    const invoiceIds = @json($invoices->pluck('id')->values());

    return {
        selectedIds: [],
        sending: false,
        savingTemplate: false,
        templateText: @json($messageTemplate),
        filters: {
            month: @json($filters['month']),
            status: @json($filters['status']),
            search: @json($filters['search']),
        },

        get allChecked() {
            return invoiceIds.length > 0 && this.selectedIds.length === invoiceIds.length;
        },

        init() {},

        toggleAll() {
            this.selectedIds = this.allChecked ? [] : [...invoiceIds];
        },

        applyFilters() {
            const params = new URLSearchParams(this.filters).toString();
            window.location.href = `{{ route('whatsapp-reminders.index') }}?${params}`;
        },

        async sendSelected() {
            this.sending = true;
            try {
                const res = await post('{{ route('whatsapp-reminders.send') }}', { invoice_ids: this.selectedIds });
                toast(res.message || 'Reminders queued successfully.', 'success');
                this.selectedIds = [];
            } catch (e) {
                toast(e.message || 'Failed to queue reminders.', 'error');
            }
            this.sending = false;
        },

        async saveTemplate() {
            this.savingTemplate = true;
            try {
                await post('{{ route('whatsapp-reminders.template') }}', { whatsapp_message_template: this.templateText });
                toast('Template updated successfully.', 'success');
            } catch (e) {
                toast(e.message || 'Failed to update template.', 'error');
            }
            this.savingTemplate = false;
        },
    }
}
</script>
@endpush
