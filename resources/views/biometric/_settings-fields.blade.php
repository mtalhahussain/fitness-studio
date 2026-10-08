{{-- Brand-specific settings. Needs $prefix ('add' | 'edit') and $presets. Fields use name="settings[...]". --}}
<div data-brand-show="zkteco" class="form-group">
    <label class="form-label">Punch mode</label>
    <select class="form-select" name="settings[punch_mode]">
        <option value="toggle">Alternate in/out on each punch</option>
        <option value="status">Use machine in/out status</option>
    </select>
    <div class="cell-sub" style="margin-top:4px">Alternate: first successful punch checks in, next checks out. Repeated punches within 60 seconds are ignored.</div>
</div>

<div data-brand-show="hikvision,generic" class="form-group">
    <label class="form-label">Machine timezone</label>
    <input class="form-input" name="settings[timezone]" id="{{ $prefix }}Tz" placeholder="{{ config('biometric.timezone') }}">
</div>

<div data-brand-show="generic" style="display:flex;flex-direction:column;gap:12px;border-top:1px solid var(--border);padding-top:12px">
    <div class="form-group">
        <label class="form-label">Start from preset</label>
        <select class="form-select" onchange="applyPreset('{{ $prefix }}', this.value)">
            <option value="">— Custom —</option>
            @foreach($presets as $key => $p)
                <option value="{{ $key }}">{{ $p['label'] }}</option>
            @endforeach
        </select>
        <div class="cell-sub" style="margin-top:4px">Presets are a starting point. Make one test punch, then check "Last received data" in Setup / Test.</div>
    </div>
    <div class="form-grid">
        <div class="form-group"><label class="form-label">Records path</label><input class="form-input" name="settings[records_path]" placeholder="e.g. data.events (empty = whole body)"></div>
        <div class="form-group"><label class="form-label">Employee ID field *</label><input class="form-input" name="settings[employee_field]" placeholder="e.g. user.id"></div>
        <div class="form-group"><label class="form-label">Time field *</label><input class="form-input" name="settings[time_field]" placeholder="e.g. datetime"></div>
        <div class="form-group"><label class="form-label">Time format *</label>
            <select class="form-select" name="settings[time_format]">
                <option value="auto">Auto-detect</option><option value="iso">Date/time text (ISO)</option>
                <option value="unix">Unix seconds</option><option value="unix_ms">Unix milliseconds</option>
            </select>
        </div>
        <div class="form-group"><label class="form-label">In/Out field</label><input class="form-input" name="settings[type_field]" placeholder="optional"></div>
        <div class="form-group"><label class="form-label">In value / Out value</label>
            <div style="display:flex;gap:6px"><input class="form-input" name="settings[type_in_value]" placeholder="in"><input class="form-input" name="settings[type_out_value]" placeholder="out"></div>
        </div>
        <div class="form-group"><label class="form-label">Secret header</label><input class="form-input" name="settings[secret_header]" placeholder="optional, e.g. X-Webhook-Secret"></div>
        <div class="form-group"><label class="form-label">Secret value</label><input class="form-input" name="settings[secret_value]" placeholder="optional" autocomplete="off"></div>
    </div>
</div>
