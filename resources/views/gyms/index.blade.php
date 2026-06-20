@extends('layouts.app')
@section('title', 'Gyms')

@section('content')
<div x-data="gymsPage()" x-init="init()">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <div class="page-title">Gyms</div>
            <div class="page-sub">{{ $gyms->count() }} gyms registered on platform</div>
        </div>
        <button class="btn btn-primary" @click="openAdd()">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Gym
        </button>
    </div>

    {{-- Context Banner --}}
    <div x-show="activeGymId" style="margin-bottom:16px;padding:10px 16px;background:rgba(108,99,255,0.12);border:1px solid rgba(108,99,255,0.3);border-radius:10px;display:flex;align-items:center;justify-content:space-between">
        <span style="font-size:13px;color:var(--primary);font-weight:500">
            👁 Viewing as: <strong x-text="activeGymName"></strong>
        </span>
        <button class="btn btn-outline btn-sm" @click="clearContext()">Back to All Gyms</button>
    </div>

    {{-- Gyms Grid --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px">
        @forelse($gyms as $gym)
        <div class="card" style="padding:20px" x-data="{ gymId: {{ $gym->id }}, gymName: '{{ addslashes($gym->name) }}' }">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px">
                <div style="display:flex;align-items:center;gap:12px">
                    <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">🏋️</div>
                    <div>
                        <div style="font-size:15px;font-weight:700;color:var(--text)">{{ $gym->name }}</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px">{{ $gym->city }}{{ $gym->city && $gym->country ? ', ' : '' }}{{ $gym->country }}</div>
                    </div>
                </div>
                <span class="badge {{ $gym->status === 'active' ? 'badge-green' : 'badge-red' }}">
                    {{ ucfirst($gym->status) }}
                </span>
            </div>

            {{-- Owner --}}
            <div style="padding:10px 12px;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px;margin-bottom:12px">
                <div style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px">Owner</div>
                @if($gym->owner)
                <div style="font-size:13px;font-weight:500;color:var(--text)">{{ $gym->owner->name }}</div>
                <div style="font-size:12px;color:var(--text-muted)">{{ $gym->owner->email }}</div>
                @else
                <div style="font-size:12px;color:var(--text-muted)">No owner assigned</div>
                @endif
            </div>

            {{-- Stats --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px">
                <div style="text-align:center;padding:8px;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px">
                    <div style="font-size:20px;font-weight:700;color:var(--text)">{{ $gym->members_count }}</div>
                    <div style="font-size:11px;color:var(--text-muted)">Members</div>
                </div>
                <div style="text-align:center;padding:8px;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px">
                    <div style="font-size:20px;font-weight:700;color:var(--text)">{{ $gym->trainers_count }}</div>
                    <div style="font-size:11px;color:var(--text-muted)">Trainers</div>
                </div>
            </div>

            {{-- Contact --}}
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:14px">
                <div>📧 {{ $gym->email }}</div>
                @if($gym->phone)<div style="margin-top:3px">📞 {{ $gym->phone }}</div>@endif
                @if($gym->subdomain)<div style="margin-top:3px">🌐 {{ $gym->subdomain }}.{{ config('tenancy.base_domain') ?: 'your-domain.com' }}</div>@endif
                @if($gym->domain)<div style="margin-top:3px">🔗 {{ $gym->domain }}</div>@endif
            </div>

            {{-- Actions --}}
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <button class="btn btn-primary btn-sm" @click="switchContext(gymId, gymName)"
                    :style="activeGymId == gymId ? 'opacity:.5;cursor:default' : ''"
                    :disabled="activeGymId == gymId">
                    {{ $activeGymId == $gym->id ? '✓ Active Context' : 'Switch Context' }}
                </button>
                <a href="{{ route('gyms.modules', $gym) }}" class="btn btn-outline btn-sm" title="Manage Modules">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    Modules
                </a>
                <button class="btn btn-outline btn-sm" @click="openEdit({{ $gym->id }}, {{ json_encode(['name'=>$gym->name,'email'=>$gym->email,'phone'=>$gym->phone,'city'=>$gym->city,'country'=>$gym->country,'domain'=>$gym->domain,'subdomain'=>$gym->subdomain]) }})">Edit</button>
                <button class="btn btn-outline btn-sm" @click="toggleStatus({{ $gym->id }}, '{{ $gym->status }}')">
                    {{ $gym->status === 'active' ? 'Suspend' : 'Activate' }}
                </button>
                <button class="btn btn-danger btn-sm" @click="deleteGym({{ $gym->id }}, '{{ addslashes($gym->name) }}')">Delete</button>
            </div>
        </div>
        @empty
        <div class="empty-state" style="grid-column:1/-1">
            <div class="icon">🏋️</div>
            <p>No gyms yet. Create your first gym.</p>
        </div>
        @endforelse
    </div>

    {{-- Add Gym Modal --}}
    <div class="modal-overlay" x-show="modal.show" x-transition @click.self="modal.show=false" style="display:none">
        <div class="modal modal-lg" @click.stop>
            <div class="modal-header">
                <div class="modal-title" x-text="modal.editing ? 'Edit Gym' : 'Create New Gym'"></div>
                <button class="modal-close" @click="modal.show=false">×</button>
            </div>
            <form @submit.prevent="saveGym()">
                <div style="font-size:13px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px">Gym Details</div>
                <div class="form-grid" style="gap:14px;margin-bottom:16px">
                    <div class="form-group">
                        <label class="form-label">Gym Name *</label>
                        <input class="form-input" placeholder="Elite Fitness Studio" x-model="modal.form.name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Email *</label>
                        <input class="form-input" type="email" placeholder="gym@example.com" x-model="modal.form.email" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input class="form-input" placeholder="+92 300 0000000" x-model="modal.form.phone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subdomain</label>
                        <input class="form-input" placeholder="elitefitness" x-model="modal.form.subdomain">
                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Final URL: subdomain.{{ config('tenancy.base_domain') ?: 'your-domain.com' }}</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Custom Domain</label>
                        <input class="form-input" placeholder="gym.example.com" x-model="modal.form.domain">
                    </div>
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input class="form-input" placeholder="Karachi" x-model="modal.form.city">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input class="form-input" placeholder="Pakistan" x-model="modal.form.country">
                    </div>
                </div>

                <template x-if="!modal.editing">
                    <div>
                        <div class="divider"></div>
                        <div style="font-size:13px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px">Owner Account</div>
                        <div class="form-grid" style="gap:14px">
                            <div class="form-group">
                                <label class="form-label">Owner Name *</label>
                                <input class="form-input" placeholder="Ahmed Khan" x-model="modal.form.owner_name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Owner Email *</label>
                                <input class="form-input" type="email" placeholder="owner@gym.com" x-model="modal.form.owner_email" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password *</label>
                                <input class="form-input" type="password" placeholder="Min 6 characters" x-model="modal.form.owner_password" required>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Owner will use this to login.</div>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" @click="modal.show=false">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="modal.loading">
                        <span x-show="modal.loading" class="spinner"></span>
                        <span x-text="modal.editing ? 'Save Changes' : 'Create Gym'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function gymsPage() {
    return {
        activeGymId: {{ $activeGymId ?? 'null' }},
        activeGymName: '{{ optional($gyms->firstWhere("id", $activeGymId))->name ?? "" }}',
        modal: { show: false, editing: false, loading: false, gymId: null,
                 form: { name:'', email:'', phone:'', city:'', country:'', domain:'', subdomain:'', owner_name:'', owner_email:'', owner_password:'' } },

        init() {
            // sync context badge name from local data
            @if($activeGymId)
            this.activeGymName = @json(optional($gyms->firstWhere('id', $activeGymId))->name ?? '');
            @endif
        },

        openAdd() {
            this.modal = { show: true, editing: false, loading: false, gymId: null,
                           form: { name:'', email:'', phone:'', city:'', country:'', domain:'', subdomain:'', owner_name:'', owner_email:'', owner_password:'' } };
        },

        openEdit(id, data) {
            this.modal = { show: true, editing: true, loading: false, gymId: id,
                           form: { name: data.name, email: data.email, phone: data.phone||'', city: data.city||'', country: data.country||'', domain: data.domain||'', subdomain: data.subdomain||'' } };
        },

        async saveGym() {
            this.modal.loading = true;
            try {
                if (this.modal.editing) {
                    await put(`/gyms/${this.modal.gymId}`, this.modal.form);
                    toast('Gym updated successfully');
                } else {
                    await post('/gyms', this.modal.form);
                    toast('Gym created successfully');
                }
                this.modal.show = false;
                window.location.reload();
            } catch(e) { toast(e.message, 'error'); }
            this.modal.loading = false;
        },

        async toggleStatus(id, currentStatus) {
            const action = currentStatus === 'active' ? 'suspend' : 'activate';
            if (!confirm(`Are you sure you want to ${action} this gym?`)) return;
            try {
                await post(`/gyms/${id}/toggle-status`);
                toast(`Gym ${action}d successfully`);
                window.location.reload();
            } catch(e) { toast(e.message, 'error'); }
        },

        async deleteGym(id, name) {
            if (!confirm(`Delete "${name}"? All gym data will be removed. This cannot be undone.`)) return;
            try {
                await del(`/gyms/${id}`);
                toast('Gym deleted');
                window.location.reload();
            } catch(e) { toast(e.message, 'error'); }
        },

        async switchContext(gymId, gymName) {
            try {
                await post(`/admin/switch-gym/${gymId}`);
                this.activeGymId = gymId;
                this.activeGymName = gymName;
                toast(`Switched to ${gymName}`, 'info');
                window.location.reload();
            } catch(e) { toast(e.message, 'error'); }
        },

        async clearContext() {
            try {
                await post('/admin/clear-gym');
                this.activeGymId = null;
                this.activeGymName = '';
                toast('Viewing all gyms', 'info');
                window.location.reload();
            } catch(e) { toast(e.message, 'error'); }
        },
    };
}
</script>
@endpush
