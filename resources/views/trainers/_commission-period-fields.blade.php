{{-- Commission rate period. Expects Alpine `configModal.form` with effective_from, effective_to, duration. --}}
<div class="form-group">
    <label class="form-label">Duration *</label>
    <select class="form-select" x-model="configModal.form.duration"
            @change="configModal.form.effective_to = commissionPeriodEnd(configModal.form.effective_from, configModal.form.duration, configModal.form.effective_to)">
        <option value="ongoing">Ongoing (no end date)</option>
        <option value="week">1 Week</option>
        <option value="month">1 Month</option>
        <option value="custom">Custom date range</option>
    </select>
    <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
        <span x-show="configModal.form.duration === 'ongoing'">Replaces the current ongoing rate from the start date and continues until you set a new one.</span>
        <span x-show="configModal.form.duration !== 'ongoing'">Temporary rate for this range only — the ongoing rate resumes automatically after it ends.</span>
    </div>
</div>
<div class="form-grid">
    <div class="form-group">
        <label class="form-label">Effective From *</label>
        <input type="date" class="form-input" x-model="configModal.form.effective_from" required
               @change="configModal.form.effective_to = commissionPeriodEnd(configModal.form.effective_from, configModal.form.duration, configModal.form.effective_to)">
    </div>
    <div class="form-group" x-show="configModal.form.duration !== 'ongoing'">
        <label class="form-label">Effective To *</label>
        <input type="date" class="form-input" x-model="configModal.form.effective_to"
               :min="configModal.form.effective_from"
               :readonly="configModal.form.duration !== 'custom'"
               :required="configModal.form.duration !== 'ongoing'">
    </div>
</div>

@once
@push('scripts')
<script>
// End date for a commission period: week/month are inclusive ranges starting on `from`.
function commissionPeriodEnd(from, duration, current) {
    if (duration === 'ongoing') return '';
    if (duration === 'custom' || !from) return current || '';
    const d = new Date(from + 'T00:00:00');
    if (duration === 'week') d.setDate(d.getDate() + 6);
    if (duration === 'month') { d.setMonth(d.getMonth() + 1); d.setDate(d.getDate() - 1); }
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
</script>
@endpush
@endonce
