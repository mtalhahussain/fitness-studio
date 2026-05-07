@extends('layouts.app')
@section('title', 'Membership Plans')

@section('content')
<div x-data="plansPage()" x-init="init()">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <div class="page-title">Membership Plans</div>
            <div class="page-sub" x-text="`${plans.length} plan${plans.length !== 1 ? 's' : ''} configured`"></div>
        </div>
        <button class="btn btn-primary" @click="openAdd()">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Plan
        </button>
    </div>

    {{-- Loading --}}
    <template x-if="loading">
        <div style="text-align:center;padding:60px;color:var(--text-muted)"><span class="spinner"></span> Loading...</div>
    </template>

    {{-- Empty --}}
    <template x-if="!loading && plans.length === 0">
        <div class="card">
            <div class="empty-state">
                <div class="icon">📋</div>
                <p>No plans yet. Add your first membership plan to get started.</p>
            </div>
        </div>
    </template>

    {{-- Plans Grid --}}
    <template x-if="!loading && plans.length > 0">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
            <template x-for="p in plans" :key="p.id">
                <div class="card" style="position:relative;padding:20px">
                    {{-- Active badge --}}
                    <div style="position:absolute;top:16px;right:16px">
                        <span class="badge" :class="p.is_active ? 'badge-green' : 'badge-gray'" x-text="p.is_active ? 'Active' : 'Inactive'"></span>
                    </div>

                    {{-- Type chip --}}
                    <div style="margin-bottom:10px">
                        <span class="badge badge-purple" x-text="typeLabel(p.type)"></span>
                    </div>

                    {{-- Name + price --}}
                    <div style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:4px" x-text="p.name"></div>
                    <div style="font-size:22px;font-weight:800;color:var(--primary);margin-bottom:8px">
                        PKR <span x-text="formatNum(p.price)"></span>
                        <span style="font-size:13px;font-weight:400;color:var(--text-muted)" x-text="'/ ' + typeLabel(p.type).toLowerCase()"></span>
                    </div>

                    {{-- Duration --}}
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px" x-text="`${p.duration_days} days`"></div>

                    {{-- Description --}}
                    <template x-if="p.description">
                        <div style="font-size:13px;color:var(--text-muted);margin-bottom:10px" x-text="p.description"></div>
                    </template>

                    {{-- Features --}}
                    <template x-if="p.features && p.features.length">
                        <ul style="margin:0 0 12px 0;padding-left:18px;font-size:12px;color:var(--text-muted)">
                            <template x-for="f in p.features" :key="f">
                                <li x-text="f"></li>
                            </template>
                        </ul>
                    </template>

                    {{-- Footer --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border);padding-top:12px;margin-top:4px">
                        <span style="font-size:11px;color:var(--text-muted)" x-text="`${p.memberships_count} member${p.memberships_count !== 1 ? 's' : ''}`"></span>
                        <div style="display:flex;gap:6px">
                            <button class="btn btn-outline btn-sm" @click="openEdit(p)">Edit</button>
                            <button class="btn btn-danger btn-sm" @click="deletePlan(p)" :disabled="p.memberships_count > 0" :title="p.memberships_count > 0 ? 'Cannot delete plan with active members' : ''">Delete</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>

    {{-- Add / Edit Modal --}}
    <div class="modal-overlay" x-show="modal.show" x-transition @click.self="modal.show=false" style="display:none">
        <div class="modal" @click.stop style="max-width:520px">
            <div class="modal-header">
                <div class="modal-title" x-text="modal.editing ? 'Edit Plan' : 'Add Membership Plan'"></div>
                <button class="modal-close" @click="modal.show=false">×</button>
            </div>
            <form @submit.prevent="savePlan()">
                <div style="display:flex;flex-direction:column;gap:14px">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Plan Name *</label>
                            <input class="form-input" placeholder="e.g. Gold Monthly" x-model="modal.form.name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Type *</label>
                            <select class="form-select" x-model="modal.form.type" required x-select2>
                                <option value="">Select type</option>
                                <option value="monthly">Monthly (30 days)</option>
                                <option value="quarterly">Quarterly (90 days)</option>
                                <option value="yearly">Yearly (365 days)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Price (PKR) *</label>
                        <input type="number" step="0.01" min="0" class="form-input" placeholder="0.00" x-model="modal.form.price" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-input" rows="2" placeholder="Optional description..." x-model="modal.form.description" style="resize:vertical"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Features <span style="font-size:11px;color:var(--text-muted)">(one per line)</span></label>
                        <textarea class="form-input" rows="3" placeholder="Unlimited gym access&#10;Personal locker&#10;Group classes" x-model="modal.featuresText" style="resize:vertical"></textarea>
                    </div>

                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--text)">
                            <input type="checkbox" x-model="modal.form.is_active" style="width:16px;height:16px;accent-color:var(--primary)">
                            Active (visible when assigning to members)
                        </label>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" @click="modal.show=false">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="modal.loading">
                        <span x-show="modal.loading" class="spinner"></span>
                        <span x-text="modal.editing ? 'Save Changes' : 'Create Plan'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function plansPage() {
    return {
        plans: @json($plans),
        loading: false,
        modal: { show: false, editing: false, loading: false, planId: null, featuresText: '', form: { name:'', type:'', price:'', description:'', features:[], is_active: true } },

        init() {},

        typeLabel(type) {
            return { monthly: 'Monthly', quarterly: 'Quarterly', yearly: 'Yearly' }[type] || type;
        },

        formatNum(n) {
            return parseFloat(n || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 });
        },

        openAdd() {
            this.modal = { show: true, editing: false, loading: false, planId: null, featuresText: '',
                form: { name:'', type:'monthly', price:'', description:'', features:[], is_active: true } };
        },

        openEdit(p) {
            this.modal = {
                show: true, editing: true, loading: false, planId: p.id,
                featuresText: (p.features || []).join('\n'),
                form: { name: p.name, type: p.type, price: p.price, description: p.description || '', features: p.features || [], is_active: p.is_active }
            };
        },

        async savePlan() {
            this.modal.loading = true;
            try {
                const payload = {
                    ...this.modal.form,
                    features: this.modal.featuresText.split('\n').map(s => s.trim()).filter(Boolean),
                };
                if (this.modal.editing) {
                    await put(`/plans/${this.modal.planId}`, payload);
                    toast('Plan updated');
                } else {
                    await post('/plans', payload);
                    toast('Plan created');
                }
                this.modal.show = false;
                await this.load();
            } catch(e) { toast(e.message, 'error'); }
            this.modal.loading = false;
        },

        async deletePlan(p) {
            if (p.memberships_count > 0) return;
            if (!confirm(`Delete plan "${p.name}"?`)) return;
            try {
                await del(`/plans/${p.id}`);
                toast('Plan deleted');
                await this.load();
            } catch(e) { toast(e.message, 'error'); }
        },

        async load() {
            this.loading = true;
            try {
                const res = await get('/plans');
                this.plans = res.plans;
            } catch(e) { toast('Failed to load plans', 'error'); }
            this.loading = false;
        },
    };
}
</script>
@endpush
