@extends('layouts.app')
@section('title', 'Trainers')

@section('content')
<div x-data="trainersPage()" x-init="init()">

    <div class="page-header">
        <div>
            <div class="page-title">Trainers</div>
            <div class="page-sub" x-text="`${totalCount} trainers in your gym`"></div>
        </div>
        <button class="btn btn-primary" @click="openAdd()">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Trainer
        </button>
    </div>

    {{-- Search --}}
    <div class="toolbar">
        <div class="search-wrap" style="flex:1;max-width:320px">
            <svg class="search-icon" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="form-input search-input" placeholder="Search trainer or specialization..." x-model="search" @input.debounce.400ms="load()">
        </div>
    </div>

    {{-- Trainer Cards Grid --}}
    <template x-if="loading">
        <div style="text-align:center;padding:60px;color:var(--text-muted)"><span class="spinner"></span> Loading...</div>
    </template>

    <template x-if="!loading && trainers.length === 0">
        <div class="empty-state" style="padding:80px"><div class="icon">🏋️</div><p>No trainers yet. Add your first trainer!</p></div>
    </template>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px" x-show="!loading && trainers.length > 0">
        <template x-for="t in trainers" :key="t.id">
            <div class="card" style="display:flex;flex-direction:column;gap:0;padding:0;overflow:hidden">
                {{-- Card top --}}
                <div style="padding:20px;display:flex;align-items:center;gap:14px;border-bottom:1px solid var(--border)">
                    <div class="avatar" style="width:48px;height:48px;border-radius:12px;font-size:16px" :style="`background:${avatarBg(t.name)}`" x-text="initials(t.name)"></div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:15px;font-weight:600;color:var(--text)" x-text="t.name"></div>
                        <div style="font-size:12px;color:var(--primary);font-weight:500;margin-top:2px" x-text="t.trainer_profile?.specialization || '—'"></div>
                    </div>
                    <span class="badge" :class="t.status==='active'?'badge-green':'badge-red'" x-text="t.status"></span>
                </div>

                {{-- Stats row --}}
                <div style="display:flex;border-bottom:1px solid var(--border)">
                    <div style="flex:1;padding:12px 16px;text-align:center;border-right:1px solid var(--border)">
                        <div style="font-size:18px;font-weight:700;color:var(--text)" x-text="t.assigned_members_count || 0"></div>
                        <div style="font-size:11px;color:var(--text-muted)">Members</div>
                    </div>
                    <div style="flex:1;padding:12px 16px;text-align:center;border-right:1px solid var(--border)">
                        <div style="font-size:18px;font-weight:700;color:var(--text)" x-text="t.training_sessions_count || 0"></div>
                        <div style="font-size:11px;color:var(--text-muted)">Sessions</div>
                    </div>
                    <div style="flex:1;padding:12px 16px;text-align:center">
                        <div style="font-size:18px;font-weight:700;color:var(--text)" x-text="t.trainer_profile?.experience_years || 0"></div>
                        <div style="font-size:11px;color:var(--text-muted)">Yrs Exp</div>
                    </div>
                </div>

                {{-- Info --}}
                <div style="padding:14px 16px;flex:1">
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.6" x-text="t.trainer_profile?.bio || 'No bio provided.'"></div>
                        <div style="margin-top:8px;font-size:12px;color:var(--text-dim)">
                            <span style="color:var(--text-muted)">Compensation:</span>
                        <span class="badge" :class="compBadgeClass(t.trainer_profile)" x-text="compLabel(t.trainer_profile)"></span>
                        </div>
                    <template x-if="t.trainer_profile?.hourly_rate">
                        <div style="margin-top:8px;font-size:12px;color:var(--text-dim)">
                            <span style="color:var(--text-muted)">Rate:</span> <span x-text="currency(t.trainer_profile.hourly_rate)"></span>/hr
                        </div>
                    </template>
                </div>

                {{-- Actions --}}
                <div style="padding:12px 16px;border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
                    <button class="btn btn-outline btn-sm" @click="openSchedule(t)">📅 Schedule</button>
                    <button class="btn btn-outline btn-sm" @click="openAssign(t)">+ Assign</button>
                    <a :href="`/trainers/${t.id}/commission`" class="btn btn-outline btn-sm">💰 Commission</a>
                    <button class="btn btn-outline btn-sm" @click="openEdit(t)">Edit</button>
                    <button class="btn btn-danger btn-sm" @click="deleteTrainer(t)">Delete</button>
                </div>
            </div>
        </template>
    </div>

    {{-- Add/Edit Trainer Modal --}}
    <div class="modal-overlay" x-show="modal.show" x-transition @click.self="modal.show=false" style="display:none">
        <div class="modal" @click.stop>
            <div class="modal-header">
                <div class="modal-title" x-text="modal.editing ? 'Edit Trainer' : 'Add New Trainer'"></div>
                <button class="modal-close" @click="modal.show=false">×</button>
            </div>
            <form @submit.prevent="saveTrainer()">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input class="form-input" placeholder="Jane Smith" x-model="modal.form.name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-input" x-model="modal.form.email" :disabled="modal.editing" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input class="form-input" x-model="modal.form.phone">
                        </div>
                        <template x-if="!modal.editing">
                            <div class="form-group">
                                <label class="form-label">Password *</label>
                                <input class="form-input" type="password" placeholder="Min 6 characters" x-model="modal.form.password" required>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Trainer will use this to login.</div>
                            </div>
                        </template>
                        <div class="form-group">
                            <label class="form-label">Specialization *</label>
                            <input class="form-input" placeholder="e.g. CrossFit, Yoga, Boxing" x-model="modal.form.specialization" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Experience (Years)</label>
                            <input type="number" class="form-input" min="0" x-model="modal.form.experience_years">
                        </div>
                        <div class="form-group" x-show="showHourlyRate()" x-transition>
                            <label class="form-label">Hourly Rate (PKR)</label>
                            <input type="number" step="0.01" class="form-input" x-model="modal.form.hourly_rate">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Compensation Mode *</label>
                            <select class="form-select" x-model="modal.form.compensation_mode" @change="syncCompensationMode()">
                                <option value="commission">Commission Only</option>
                                <option value="salary">Salary Only</option>
                                <option value="hourly">Hourly Only</option>
                                <option value="mixed">Salary + Commission</option>
                            </select>
                        </div>
                        <div class="form-group" x-show="showBaseSalary()" x-transition>
                            <label class="form-label">Base Salary (PKR)</label>
                            <input type="number" step="0.01" class="form-input" x-model="modal.form.base_salary">
                        </div>
                    </div>
                    <template x-if="modal.editing">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" x-model="modal.form.status" x-select2>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </template>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" @click="modal.show=false">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="modal.loading">
                        <span x-show="modal.loading" class="spinner"></span>
                        <span x-text="modal.editing ? 'Save Changes' : 'Add Trainer'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Assign Member Modal --}}
    <div class="modal-overlay" x-show="assignModal.show" x-transition @click.self="assignModal.show=false" style="display:none">
        <div class="modal" @click.stop style="max-width:420px">
            <div class="modal-header">
                <div>
                    <div class="modal-title">Assign Member</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px" x-text="'To: ' + (assignModal.trainer?.name || '')"></div>
                </div>
                <button class="modal-close" @click="assignModal.show=false">×</button>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Select Member *</label>
                <select class="form-select" x-model="assignModal.memberId" x-select2>
                    <option value="">Choose a member...</option>
                    @foreach($members as $m)
                    <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->email }})</option>
                    @endforeach
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" @click="assignModal.show=false">Cancel</button>
                <button type="button" class="btn btn-success" :disabled="!assignModal.memberId || assignModal.loading" @click="doAssign()">
                    <span x-show="assignModal.loading" class="spinner"></span>
                    Assign Member
                </button>
            </div>
        </div>
    </div>

    {{-- Schedule Modal --}}
    <div class="modal-overlay" x-show="scheduleModal.show" x-transition @click.self="scheduleModal.show=false" style="display:none">
        <div class="modal modal-lg" @click.stop>
            <div class="modal-header">
                <div>
                    <div class="modal-title" x-text="(scheduleModal.trainer?.name || '') + `'s Schedule`"></div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px">Current week sessions</div>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <button class="btn btn-primary btn-sm" @click="openNewSession()">+ New Session</button>
                    <button class="modal-close" @click="scheduleModal.show=false">×</button>
                </div>
            </div>

            <template x-if="scheduleModal.loading">
                <div style="text-align:center;padding:40px;color:var(--text-muted)"><span class="spinner"></span></div>
            </template>
            <template x-if="!scheduleModal.loading && scheduleModal.sessions.length === 0">
                <div class="empty-state"><div class="icon">📅</div><p>No sessions this week</p></div>
            </template>
            <div style="display:flex;flex-direction:column;gap:10px" x-show="!scheduleModal.loading">
                <template x-for="s in scheduleModal.sessions" :key="s.id">
                    <div style="display:flex;align-items:center;gap:14px;padding:14px;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:10px">
                        <div style="width:48px;text-align:center;flex-shrink:0">
                            <div style="font-size:18px;font-weight:700;color:var(--text)" x-text="new Date(s.scheduled_at).getDate()"></div>
                            <div style="font-size:10px;color:var(--text-muted)" x-text="new Date(s.scheduled_at).toLocaleDateString([],{month:'short'})"></div>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:600;color:var(--text)" x-text="s.title"></div>
                            <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
                                <span x-text="new Date(s.scheduled_at).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'})"></span>
                                · <span x-text="s.duration_mins + ' min'"></span>
                                <template x-if="s.member"><span> · <span x-text="s.member.name"></span></span></template>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            <span class="badge" :class="{'badge-green':s.status==='scheduled','badge-blue':s.status==='completed','badge-red':s.status==='cancelled','badge-yellow':s.status==='no_show'}" x-text="s.status"></span>
                            <span class="badge" :class="s.session_type==='personal'?'badge-purple':'badge-blue'" x-text="s.session_type"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- New Session Modal --}}
    <div class="modal-overlay" x-show="sessionModal.show" x-transition @click.self="sessionModal.show=false" style="display:none">
        <div class="modal" @click.stop>
            <div class="modal-header">
                <div class="modal-title">New Training Session</div>
                <button class="modal-close" @click="sessionModal.show=false">×</button>
            </div>
            <form @submit.prevent="saveSession()">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div class="form-group">
                        <label class="form-label">Session Title *</label>
                        <input class="form-input" placeholder="e.g. Strength Training" x-model="sessionModal.form.title" required>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Date & Time *</label>
                            <input type="datetime-local" class="form-input" x-model="sessionModal.form.scheduled_at" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Duration (mins)</label>
                            <input type="number" class="form-input" value="60" min="15" max="480" x-model="sessionModal.form.duration_mins">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Session Type</label>
                            <select class="form-select" x-model="sessionModal.form.session_type" x-select2>
                                <option value="personal">Personal</option>
                                <option value="group">Group</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Member (optional)</label>
                            <select class="form-select" x-model="sessionModal.form.member_id" x-select2>
                                <option value="">No specific member</option>
                                @foreach($members as $m)
                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <input class="form-input" placeholder="Optional notes..." x-model="sessionModal.form.notes">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" @click="sessionModal.show=false">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="sessionModal.loading">
                        <span x-show="sessionModal.loading" class="spinner"></span>
                        Schedule Session
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function trainersPage() {
    return {
        trainers: @json($trainers->items()),
        totalCount: {{ $trainers->total() }},
        loading: false,
        search: '',
        modal: { show: false, editing: false, loading: false, trainerId: null, form: { name:'', email:'', phone:'', specialization:'', experience_years:0, hourly_rate:'', compensation_mode:'commission', base_salary:'', commission_enabled:true, salary_enabled:false, status:'active' } },
        assignModal: { show: false, loading: false, trainer: null, memberId: '' },
        scheduleModal: { show: false, loading: false, trainer: null, sessions: [] },
        sessionModal: { show: false, loading: false, form: { title:'', scheduled_at:'', duration_mins:60, session_type:'personal', member_id:'', notes:'' } },

        init() {},

        syncCompensationMode() {
            const mode = this.modal.form.compensation_mode;

            if (mode === 'commission') {
                this.modal.form.commission_enabled = true;
                this.modal.form.salary_enabled = false;
            }

            if (mode === 'salary') {
                this.modal.form.salary_enabled = true;
                this.modal.form.commission_enabled = false;
            }

            if (mode === 'hourly') {
                this.modal.form.salary_enabled = false;
                this.modal.form.commission_enabled = false;
            }

            if (mode === 'mixed') {
                this.modal.form.salary_enabled = true;
                this.modal.form.commission_enabled = true;
            }
        },

        showHourlyRate() {
            return ['hourly', 'mixed'].includes(this.modal.form.compensation_mode);
        },

        showBaseSalary() {
            return ['salary', 'mixed'].includes(this.modal.form.compensation_mode);
        },

        async load() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.search) params.set('search', this.search);
                const res = await get(`/trainers?${params}`);
                this.trainers = res.trainers;
            } catch(e) { toast('Failed to load', 'error'); }
            this.loading = false;
        },

        openAdd() {
            this.modal = { show: true, editing: false, loading: false, trainerId: null, form: { name:'', email:'', phone:'', password:'', specialization:'', experience_years:0, hourly_rate:'', compensation_mode:'commission', base_salary:'', commission_enabled:true, salary_enabled:false, status:'active' } };
            this.syncCompensationMode();
        },

        openEdit(t) {
            this.modal = { show: true, editing: true, loading: false, trainerId: t.id, form: {
                name: t.name, email: t.email, phone: t.phone||'',
                specialization: t.trainer_profile?.specialization||'',
                experience_years: t.trainer_profile?.experience_years||0,
                hourly_rate: t.trainer_profile?.hourly_rate||'',
                compensation_mode: t.trainer_profile?.compensation_mode || 'commission',
                base_salary: t.trainer_profile?.base_salary || '',
                commission_enabled: t.trainer_profile?.commission_enabled ?? true,
                salary_enabled: t.trainer_profile?.salary_enabled ?? false,
                status: t.status,
            }};
            this.syncCompensationMode();
        },

        openAssign(t) {
            this.assignModal = { show: true, loading: false, trainer: t, memberId: '' };
        },

        async openSchedule(t) {
            this.scheduleModal = { show: true, loading: true, trainer: t, sessions: [] };
            try {
                const res = await get(`/trainers/${t.id}/schedule`);
                this.scheduleModal.sessions = res.sessions;
            } catch(e) { toast('Failed to load schedule', 'error'); }
            this.scheduleModal.loading = false;
        },

        openNewSession() {
            this.sessionModal = { show: true, loading: false, form: { title:'', scheduled_at:'', duration_mins:60, session_type:'personal', member_id:'', notes:'' } };
        },

        async saveTrainer() {
            this.modal.loading = true;
            try {
                if (this.modal.editing) {
                    await put(`/trainers/${this.modal.trainerId}`, this.modal.form);
                    toast('Trainer updated successfully');
                } else {
                    await post('/trainers', this.modal.form);
                    toast('Trainer added successfully');
                }
                this.modal.show = false;
                this.load();
            } catch(e) { toast(e.message, 'error'); }
            this.modal.loading = false;
        },

        async doAssign() {
            this.assignModal.loading = true;
            try {
                const res = await post(`/trainers/${this.assignModal.trainer.id}/assign`, { member_id: this.assignModal.memberId });
                toast(res.message, 'success');
                this.assignModal.show = false;
                this.load();
            } catch(e) { toast(e.message, 'error'); }
            this.assignModal.loading = false;
        },

        async saveSession() {
            this.sessionModal.loading = true;
            try {
                const res = await post(`/trainers/${this.scheduleModal.trainer.id}/sessions`, this.sessionModal.form);
                toast(res.message, 'success');
                this.sessionModal.show = false;
                // Refresh schedule
                const sr = await get(`/trainers/${this.scheduleModal.trainer.id}/schedule`);
                this.scheduleModal.sessions = sr.sessions;
            } catch(e) { toast(e.message, 'error'); }
            this.sessionModal.loading = false;
        },

        async deleteTrainer(t) {
            if (!confirm(`Delete ${t.name}? This cannot be undone.`)) return;
            try {
                await del(`/trainers/${t.id}`);
                toast('Trainer deleted');
                this.load();
            } catch(e) { toast(e.message, 'error'); }
        },

        compLabel(profile) {
            if (!profile) return 'Commission Only';
            const mode = profile.compensation_mode || 'commission';
            const salary = profile.base_salary ? `${currency(profile.base_salary)}/mo` : '';
            const hourly = profile.hourly_rate ? `${currency(profile.hourly_rate)}/hr` : '';

            if (mode === 'salary') return `Salary${salary ? ' · ' + salary : ''}`;
            if (mode === 'hourly') return `Hourly${hourly ? ' · ' + hourly : ''}`;
            if (mode === 'mixed') return `Mixed${salary ? ' · ' + salary : ''}${hourly ? ' · ' + hourly : ''}`;

            return `Commission${profile.commission_enabled === false ? ' (off)' : ''}`;
        },

        compBadgeClass(profile) {
            const mode = profile?.compensation_mode || 'commission';
            if (mode === 'salary') return 'badge-blue';
            if (mode === 'hourly') return 'badge-purple';
            if (mode === 'mixed') return 'badge-green';
            return 'badge-yellow';
        },
    };
}
</script>
@endpush
