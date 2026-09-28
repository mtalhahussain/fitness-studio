{{--
    "Add Session" modal for trainers.
    Needs: $formMembers (id, name), $trainingTypes. Optional: $presetMemberId.
    Open from anywhere: $dispatch('open-session', { memberId: 12 })
--}}
@php
    $reopen = $errors->any() || session('error');
    $defaultWhen = now()->addHour()->startOfHour()->format('Y-m-d\TH:i');
@endphp
<div x-data="{
        open: {{ $reopen ? 'true' : 'false' }},
        memberId: @js((string) old('member_id', $presetMemberId ?? '')),
        type: @js(old('session_type', 'personal')),
        title: @js(old('title', '')),
    }"
     @open-session.window="open = true; if ($event.detail && $event.detail.memberId) memberId = String($event.detail.memberId)"
     @keydown.escape.window="open = false">
    <div class="modal-overlay" x-show="open" x-transition @click.self="open = false" style="display:none">
        <div class="modal" @click.stop>
            <div class="modal-header">
                <div class="modal-title">Add Session / Training</div>
                <button type="button" class="modal-close" @click="open = false">×</button>
            </div>

            <form method="POST" action="{{ route('my.sessions.store') }}">
                @csrf

                @if($errors->any())
                    <div style="background:var(--error-dim);color:var(--error);border-radius:10px;padding:10px 12px;font-size:13px;margin-bottom:14px">
                        @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                    </div>
                @endif

                {{-- 1. Who --}}
                <div class="form-group">
                    <label class="form-label">Session type</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <button type="button" class="btn btn-sm" :class="type === 'personal' ? 'btn-primary' : 'btn-outline'" @click="type = 'personal'">👤 One member</button>
                        <button type="button" class="btn btn-sm" :class="type === 'group' ? 'btn-primary' : 'btn-outline'" @click="type = 'group'">👥 Group class</button>
                    </div>
                    <input type="hidden" name="session_type" :value="type">
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Member <span x-show="type === 'personal'">*</span>
                        <span x-show="type === 'group'" style="color:var(--text-muted);font-weight:400">(optional)</span>
                    </label>
                    @if($formMembers->isEmpty())
                        <div style="font-size:13px;color:var(--text-muted)">No members assigned to you yet. Ask the gym owner to assign members.</div>
                        <input type="hidden" name="member_id" value="">
                    @else
                        <select name="member_id" class="form-select" x-model="memberId" :required="type === 'personal'">
                            <option value="">— Select member —</option>
                            @foreach($formMembers as $m)
                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                {{-- 2. What --}}
                <div class="form-group">
                    <label class="form-label">Training *</label>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px">
                        @foreach($trainingTypes as $t)
                            <button type="button" class="btn btn-sm" :class="title === @js($t) ? 'btn-primary' : 'btn-outline'" @click="title = @js($t)">{{ $t }}</button>
                        @endforeach
                    </div>
                    <input name="title" class="form-input" x-model="title" placeholder="Pick above or type your own, e.g. Leg Day" maxlength="255" required>
                </div>

                {{-- 3. When --}}
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Date &amp; time *</label>
                        <input type="datetime-local" name="scheduled_at" class="form-input" value="{{ old('scheduled_at', $defaultWhen) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Duration</label>
                        <select name="duration_mins" class="form-select">
                            @foreach([30 => '30 min', 45 => '45 min', 60 => '1 hour', 90 => '1.5 hours', 120 => '2 hours'] as $v => $l)
                                <option value="{{ $v }}" @selected((int) old('duration_mins', 60) === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Repeat</label>
                    <select name="repeat_weeks" class="form-select">
                        <option value="1">Just once</option>
                        @foreach([2, 4, 8, 12] as $w)
                            <option value="{{ $w }}" @selected((int) old('repeat_weeks') === $w)>Same time every week, for {{ $w }} weeks</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes <span style="color:var(--text-muted);font-weight:400">(exercises, diet tips, goals…)</span></label>
                    <textarea name="notes" class="form-input" rows="3" maxlength="2000" placeholder="e.g. 3×12 squats, 20 min treadmill">{{ old('notes') }}</textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" @click="open = false">Cancel</button>
                    <button type="submit" class="btn btn-primary" @disabled($formMembers->isEmpty())>Save Session</button>
                </div>
            </form>
        </div>
    </div>
</div>
