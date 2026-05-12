@extends('layouts.app')
@section('title', 'Members')

@section('content')
<div x-data="membersPage()" x-init="init()" style="height:100%">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <div class="page-title">Members</div>
            <div class="page-sub" x-text="`${totalCount} members registered`"></div>
        </div>
        <button class="btn btn-primary" @click="openAdd()">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Member
        </button>
    </div>

    {{-- Toolbar --}}
    <div class="toolbar">
        <div class="search-wrap" style="flex:1;max-width:320px">
            <svg class="search-icon" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="form-input search-input" placeholder="Search name, email, phone..." x-model="search" @input.debounce.400ms="load()">
        </div>
        <select class="form-select" style="width:140px" x-model="statusFilter" @change="load()" x-select2>
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="suspended">Suspended</option>
        </select>
    </div>

    {{-- Table --}}
    <div class="card" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Membership</th>
                        <th>Joined</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)"><span class="spinner"></span> Loading...</td></tr>
                    </template>
                    <template x-if="!loading && members.length === 0">
                        <tr><td colspan="6"><div class="empty-state"><div class="icon">👥</div><p>No members found</p></div></td></tr>
                    </template>
                    <template x-for="m in members" :key="m.id">
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div class="avatar" :style="`background:${avatarBg(m.name)}`" x-text="initials(m.name)"></div>
                                    <div>
                                        <div class="cell-main" x-text="m.name"></div>
                                        <div class="cell-sub" x-text="m.email"></div>
                                    </div>
                                </div>
                            </td>
                            <td x-text="m.phone || '—'"></td>
                            <td>
                                <span class="badge" :class="m.status==='active'?'badge-green':m.status==='suspended'?'badge-red':'badge-gray'" x-text="m.status"></span>
                            </td>
                            <td>
                                <template x-if="m.active_membership">
                                    <div>
                                        <span class="badge badge-purple" x-text="m.active_membership.plan?.type"></span>
                                        <div style="font-size:11px;color:var(--text-muted);margin-top:3px" x-text="`Exp: ${fmtDate(m.active_membership.end_date)}`"></div>
                                        <template x-if="memberBalance(m) > 0">
                                            <div style="display:flex;align-items:center;gap:6px;margin-top:4px">
                                                <span style="font-size:11px;color:#ef4444;font-weight:600" x-text="`Due: PKR ${memberBalance(m).toLocaleString()}`"></span>
                                                <button class="btn btn-sm" style="padding:1px 7px;font-size:10px;background:#ef444420;color:#ef4444;border:1px solid #ef444440" @click="openPayBalance(m)">Pay</button>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!m.active_membership">
                                    <button class="btn btn-outline btn-sm" @click="openPlan(m)">Assign Plan</button>
                                </template>
                            </td>
                            <td x-text="fmtDate(m.created_at)"></td>
                            <td>
                                <div style="display:flex;gap:6px;justify-content:flex-end">
                                    <button class="btn btn-outline btn-sm" @click="openTraining(m)" title="Training">🏋️</button>
                                    <button class="btn btn-outline btn-sm" @click="openEdit(m)">Edit</button>
                                    <button class="btn btn-danger btn-sm" @click="deleteMember(m)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        {{-- Pagination --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid var(--border)" x-show="lastPage > 1">
            <span style="font-size:12px;color:var(--text-muted)" x-text="`Page ${currentPage} of ${lastPage}`"></span>
            <div style="display:flex;gap:6px">
                <button class="btn btn-outline btn-sm" :disabled="currentPage<=1" @click="currentPage--;load()">← Prev</button>
                <button class="btn btn-outline btn-sm" :disabled="currentPage>=lastPage" @click="currentPage++;load()">Next →</button>
            </div>
        </div>
    </div>

    {{-- Add/Edit Member Modal --}}
    <div class="modal-overlay" x-show="modal.show" x-transition @click.self="modal.show=false" style="display:none">
        <div class="modal" @click.stop>
            <div class="modal-header">
                <div class="modal-title" x-text="modal.editing ? 'Edit Member' : 'Add New Member'"></div>
                <button class="modal-close" @click="modal.show=false">×</button>
            </div>
            <form @submit.prevent="saveMember()">
                <div class="form-grid" style="gap:14px">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input class="form-input" placeholder="John Doe" x-model="modal.form.name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input class="form-input" type="email" placeholder="john@example.com" x-model="modal.form.email" :disabled="modal.editing" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input class="form-input" placeholder="+92 300 0000000" x-model="modal.form.phone">
                    </div>
                    <template x-if="!modal.editing">
                        <div class="form-group">
                            <label class="form-label">Password *</label>
                            <input class="form-input" type="password" placeholder="Min 6 characters" x-model="modal.form.password" required>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Member will use this to login.</div>
                        </div>
                    </template>
                    <template x-if="modal.editing">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" x-model="modal.form.status" x-select2>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </template>
                    <template x-if="!modal.editing">
                        <div class="form-group">
                            <label class="form-label">Membership Plan</label>
                            <select class="form-select" x-model="modal.form.plan_id" @change="onPlanChange()" x-select2>
                                <option value="">No plan yet</option>
                                @foreach($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }} ({{ ucfirst($plan->type) }} — PKR {{ number_format($plan->price, 0) }})</option>
                                @endforeach
                            </select>
                        </div>
                    </template>
                    <template x-if="!modal.editing && modal.form.plan_id">
                        <div class="form-group">
                            <label class="form-label">Amount Paid (PKR)</label>
                            <input type="number" step="0.01" min="0" class="form-input" x-model="modal.form.amount_paid" placeholder="0.00">
                            <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
                                Plan price: <strong x-text="'PKR ' + (planPrice(modal.form.plan_id) || 0).toLocaleString()"></strong>
                                — leave 0 or enter partial for balance due
                            </div>
                        </div>
                    </template>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" @click="modal.show=false">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="modal.loading">
                        <span x-show="modal.loading" class="spinner"></span>
                        <span x-text="modal.editing ? 'Save Changes' : 'Add Member'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Pay Balance Modal --}}
    <div class="modal-overlay" x-show="balanceModal.show" x-transition @click.self="balanceModal.show=false" style="display:none">
        <div class="modal" @click.stop style="max-width:420px">
            <div class="modal-header">
                <div>
                    <div class="modal-title">Pay Balance</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px" x-text="balanceModal.member?.name + ' — Due: PKR ' + (memberBalance(balanceModal.member || {}) || 0).toLocaleString()"></div>
                </div>
                <button class="modal-close" @click="balanceModal.show=false">×</button>
            </div>
            <form @submit.prevent="savePayBalance()">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div class="form-group">
                        <label class="form-label">Amount (PKR) *</label>
                        <input type="number" step="0.01" min="0.01" class="form-input" x-model="balanceModal.form.amount" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Method *</label>
                        <select class="form-select" x-model="balanceModal.form.method" required x-select2>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="wallet">Wallet</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" @click="balanceModal.show=false">Cancel</button>
                    <button type="submit" class="btn btn-success" :disabled="balanceModal.loading">
                        <span x-show="balanceModal.loading" class="spinner"></span>
                        Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Training Period Modal --}}
    <div class="modal-overlay" x-show="trainingModal.show" x-transition @click.self="trainingModal.show=false" style="display:none">
        <div class="modal modal-lg" @click.stop>
            <div class="modal-header">
                <div>
                    <div class="modal-title" x-text="(trainingModal.member?.name || '') + ' — Training'"></div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px">Manage training periods and history</div>
                </div>
                <button class="modal-close" @click="trainingModal.show=false">×</button>
            </div>

            {{-- Action buttons --}}
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
                <button class="btn btn-success btn-sm" @click="trainingModal.action='start'; trainingModal.actionForm={trainer_id:'',start_date:'',notes:''}">▶ Start</button>
                <button class="btn btn-outline btn-sm" @click="trainingModal.action='pause'; trainingModal.actionForm={pause_date:'',notes:''}">⏸ Pause</button>
                <button class="btn btn-outline btn-sm" @click="trainingModal.action='resume'; trainingModal.actionForm={trainer_id:'',resume_date:'',notes:''}">▶▶ Resume</button>
                <button class="btn btn-danger btn-sm" @click="trainingModal.action='end'; trainingModal.actionForm={end_date:''}">■ End</button>
            </div>

            {{-- Action form --}}
            <template x-if="trainingModal.action">
                <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:16px">
                    <div style="font-size:13px;font-weight:600;margin-bottom:12px;text-transform:capitalize" x-text="trainingModal.action + ' Training'"></div>

                    <template x-if="trainingModal.action === 'start' || trainingModal.action === 'resume'">
                        <div class="form-group" style="margin-bottom:10px">
                            <label class="form-label">Trainer <span x-show="trainingModal.action === 'start'" style="color:var(--error)">*</span></label>
                            <select class="form-select" x-model="trainingModal.actionForm.trainer_id"
                                :required="trainingModal.action === 'start'">
                                <template x-if="trainingModal.action === 'resume'">
                                    <option value="">Keep same trainer</option>
                                </template>
                                <template x-if="trainingModal.action === 'start'">
                                    <option value="" disabled>Select a trainer...</option>
                                </template>
                                @foreach(\App\Models\User::trainers()->forGym(auth()->user()->gym_id)->get(['id','name']) as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>

                    <div class="form-grid">
                        <template x-if="trainingModal.action === 'start'">
                            <div class="form-group">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-input" x-model="trainingModal.actionForm.start_date">
                            </div>
                        </template>
                        <template x-if="trainingModal.action === 'pause'">
                            <div class="form-group">
                                <label class="form-label">Pause Date</label>
                                <input type="date" class="form-input" x-model="trainingModal.actionForm.pause_date">
                            </div>
                        </template>
                        <template x-if="trainingModal.action === 'resume'">
                            <div class="form-group">
                                <label class="form-label">Resume Date</label>
                                <input type="date" class="form-input" x-model="trainingModal.actionForm.resume_date">
                            </div>
                        </template>
                        <template x-if="trainingModal.action === 'end'">
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-input" x-model="trainingModal.actionForm.end_date">
                            </div>
                        </template>
                    </div>

                    <template x-if="trainingModal.action !== 'end'">
                        <div class="form-group" style="margin-top:10px">
                            <label class="form-label">Notes</label>
                            <input class="form-input" placeholder="Optional" x-model="trainingModal.actionForm.notes">
                        </div>
                    </template>

                    <div style="display:flex;gap:8px;margin-top:12px">
                        <button class="btn btn-primary btn-sm" :disabled="trainingModal.saving" @click="doTrainingAction()">
                            <span x-show="trainingModal.saving" class="spinner"></span>
                            Confirm
                        </button>
                        <button class="btn btn-outline btn-sm" @click="trainingModal.action=null">Cancel</button>
                    </div>
                </div>
            </template>

            {{-- History table --}}
            <div style="font-size:13px;font-weight:600;margin-bottom:10px">History</div>
            <template x-if="trainingModal.loading">
                <div style="text-align:center;padding:30px;color:var(--text-muted)"><span class="spinner"></span></div>
            </template>
            <template x-if="!trainingModal.loading && trainingModal.history.length === 0">
                <div class="empty-state" style="padding:20px"><p>No training periods recorded.</p></div>
            </template>
            <div class="table-wrap" x-show="!trainingModal.loading && trainingModal.history.length > 0">
                <table>
                    <thead>
                        <tr><th>Trainer</th><th>Start</th><th>End/Pause</th><th>Status</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                        <template x-for="p in trainingModal.history" :key="p.id">
                            <tr>
                                <td class="cell-main" x-text="p.trainer?.name || '—'"></td>
                                <td x-text="fmtDate(p.start_date)"></td>
                                <td x-text="p.end_date ? fmtDate(p.end_date) : '—'"></td>
                                <td>
                                    <span class="badge"
                                        :class="p.status==='active'?'badge-green':p.status==='paused'?'badge-yellow':'badge-gray'"
                                        x-text="p.status"></span>
                                </td>
                                <td style="font-size:12px;color:var(--text-muted)" x-text="p.notes || '—'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Assign Plan Modal --}}
    <div class="modal-overlay" x-show="planModal.show" x-transition @click.self="planModal.show=false" style="display:none">
        <div class="modal" @click.stop>
            <div class="modal-header">
                <div>
                    <div class="modal-title">Assign Membership Plan</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px" x-text="'For: ' + (planModal.member?.name || '')"></div>
                </div>
                <button class="modal-close" @click="planModal.show=false">×</button>
            </div>
            <form @submit.prevent="savePlan()">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div class="form-group">
                        <label class="form-label">Select Plan *</label>
                        <select class="form-select" x-model="planModal.form.plan_id" required x-select2>
                            <option value="">Choose a plan</option>
                            @foreach($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }} — {{ ucfirst($plan->type) }} — PKR {{ $plan->price }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-input" x-model="planModal.form.start_date">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount Paid (PKR)</label>
                            <input type="number" step="0.01" class="form-input" placeholder="0.00" x-model="planModal.form.amount_paid">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" @click="planModal.show=false">Cancel</button>
                    <button type="submit" class="btn btn-success" :disabled="planModal.loading">
                        <span x-show="planModal.loading" class="spinner"></span>
                        Assign Plan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function membersPage() {
    return {
        members: @json($members->items()),
        plans: @json($plans->map(fn($p) => ['id' => $p->id, 'price' => (float)$p->price])),
        totalCount: {{ $members->total() }},
        currentPage: {{ $members->currentPage() }},
        lastPage: {{ $members->lastPage() }},
        loading: false,
        search: '',
        statusFilter: '',
        modal: { show: false, editing: false, loading: false, memberId: null, form: { name:'', email:'', phone:'', password:'', status:'active', plan_id:'', amount_paid:'' } },
        planModal: { show: false, loading: false, member: null, form: { plan_id:'', start_date:'', amount_paid:'' } },
        balanceModal: { show: false, loading: false, member: null, form: { amount:'', method:'cash' } },
        trainingModal: { show: false, loading: false, saving: false, member: null, history: [], action: null, actionForm: {} },

        init() {},

        async load() {
            this.loading = true;
            try {
                const params = new URLSearchParams({ page: this.currentPage });
                if (this.search)       params.set('search', this.search);
                if (this.statusFilter) params.set('status', this.statusFilter);
                const res = await get(`/members?${params}`);
                this.members     = res.members;
                this.totalCount  = res.meta.total;
                this.lastPage    = res.meta.last_page;
                this.currentPage = res.meta.current_page;
            } catch(e) { toast('Failed to load members', 'error'); }
            this.loading = false;
        },

        memberBalance(m) {
            if (!m || !m.active_membership) return 0;
            const inv = m.active_membership.invoice;
            if (inv) {
                const total = parseFloat(inv.total_amount || 0);
                const paid  = parseFloat(inv.payments_sum_amount || 0);
                return Math.max(0, Math.round(total - paid));
            }
            if (!m.active_membership.plan) return 0;
            return Math.max(0, Math.round(parseFloat(m.active_membership.plan.price) - parseFloat(m.active_membership.amount_paid || 0)));
        },

        openPayBalance(m) {
            const due = this.memberBalance(m);
            this.balanceModal = { show: true, loading: false, member: m, form: { amount: due, method: 'cash' } };
        },

        async savePayBalance() {
            this.balanceModal.loading = true;
            try {
                await post(`/members/${this.balanceModal.member.id}/pay-balance`, this.balanceModal.form);
                toast('Payment recorded successfully');
                this.balanceModal.show = false;
                this.load();
            } catch(e) { toast(e.message, 'error'); }
            this.balanceModal.loading = false;
        },

        openAdd() {
            this.modal = { show: true, editing: false, loading: false, memberId: null, form: { name:'', email:'', phone:'', password:'', status:'active', plan_id:'', amount_paid:'' } };
        },

        planPrice(planId) {
            const p = this.plans.find(p => p.id == planId);
            return p ? p.price : 0;
        },

        onPlanChange() {
            const price = this.planPrice(this.modal.form.plan_id);
            this.modal.form.amount_paid = price > 0 ? price : '';
        },

        openEdit(m) {
            this.modal = { show: true, editing: true, loading: false, memberId: m.id, form: { name: m.name, email: m.email, phone: m.phone||'', status: m.status } };
        },

        openPlan(m) {
            this.planModal = { show: true, loading: false, member: m, form: { plan_id:'', start_date:'', amount_paid:'' } };
        },

        async saveMember() {
            this.modal.loading = true;
            try {
                if (this.modal.editing) {
                    await put(`/members/${this.modal.memberId}`, this.modal.form);
                    toast('Member updated successfully');
                } else {
                    await post('/members', this.modal.form);
                    toast('Member added successfully');
                }
                this.modal.show = false;
                this.load();
            } catch(e) { toast(e.message, 'error'); }
            this.modal.loading = false;
        },

        async savePlan() {
            this.planModal.loading = true;
            try {
                await post(`/members/${this.planModal.member.id}/membership`, this.planModal.form);
                toast('Membership plan assigned');
                this.planModal.show = false;
                this.load();
            } catch(e) { toast(e.message, 'error'); }
            this.planModal.loading = false;
        },

        async deleteMember(m) {
            if (!confirm(`Delete ${m.name}? This cannot be undone.`)) return;
            try {
                await del(`/members/${m.id}`);
                toast('Member deleted');
                this.load();
            } catch(e) { toast(e.message, 'error'); }
        },

        async openTraining(m) {
            this.trainingModal = { show: true, loading: true, saving: false, member: m, history: [], action: null, actionForm: {} };
            try {
                const res = await get(`/members/${m.id}/training/history`);
                this.trainingModal.history = res.history;
            } catch(e) { toast('Failed to load training history', 'error'); }
            this.trainingModal.loading = false;
        },

        async doTrainingAction() {
            const act = this.trainingModal.action;
            if (act === 'start' && !this.trainingModal.actionForm.trainer_id) {
                toast('Please select a trainer.', 'error');
                return;
            }
            this.trainingModal.saving = true;
            const m   = this.trainingModal.member;
            const url = `/members/${m.id}/training/${act}`;
            try {
                await post(url, this.trainingModal.actionForm);
                toast(`Training ${act}ed successfully.`);
                this.trainingModal.action = null;
                const res = await get(`/members/${m.id}/training/history`);
                this.trainingModal.history = res.history;
            } catch(e) { toast(e.message || 'Action failed', 'error'); }
            this.trainingModal.saving = false;
        },
    };
}
</script>
@endpush
