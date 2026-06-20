@extends('layouts.app')
@section('title', 'WhatsApp Settings — ' . $gym->name)

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">WhatsApp Configuration</div>
        <div class="page-sub">{{ $gym->name }} — Setup Meta WhatsApp Cloud API credentials</div>
    </div>
    <a href="{{ route('gyms.modules', $gym) }}" class="btn btn-outline btn-sm">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg>
        Back to Modules
    </a>
</div>

<div style="max-width:720px">
    <div class="card" x-data="whatsAppSettings()" x-init="init()">
        <div class="card-header">
            <div>
                <div class="card-title">WhatsApp Cloud API Setup</div>
                <div class="card-subtitle">Configure credentials for automatic payment reminders via WhatsApp</div>
            </div>
            <button class="btn btn-primary" @click="save()" :disabled="saving">
                <span x-show="saving" class="spinner" style="width:13px;height:13px;border-width:2px"></span>
                <span x-text="saving ? 'Saving...' : 'Save Configuration'"></span>
            </button>
        </div>

        <form @submit.prevent="save()" style="display:flex;flex-direction:column;gap:20px;padding:24px">

            {{-- Enable/Disable Toggle --}}
            <div style="padding:16px;background:var(--bg-secondary);border-radius:10px;border:1px solid var(--border)">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:16px">
                    <div>
                        <div style="font-weight:600;color:var(--text);margin-bottom:4px">Enable WhatsApp Reminders</div>
                        <div style="font-size:13px;color:var(--text-muted)">Turn on to send automatic payment due reminders</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;flex-shrink:0">
                        <input type="checkbox" x-model="form.whatsapp_enabled" style="width:20px;height:20px;cursor:pointer">
                    </div>
                </div>
            </div>

            {{-- Credentials Section (shown only if enabled) --}}
            <div x-show="form.whatsapp_enabled" x-transition>
                <div style="padding:16px;background:var(--warning-dim);border-radius:10px;border:1px solid rgba(255,193,7,0.2);margin-bottom:20px;display:flex;gap:12px">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="var(--warning)" stroke-width="2" style="flex-shrink:0;margin-top:2px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div style="font-size:12px;color:var(--text-muted)">
                        Get credentials from <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" style="color:var(--primary);text-decoration:underline">Meta WhatsApp Business Platform</a>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">WhatsApp Business Phone Number ID *</label>
                    <input type="text" class="form-input" placeholder="123456789012345" x-model="form.whatsapp_phone_number_id" required>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Found in WhatsApp Business Platform settings</div>
                </div>

                <div class="form-group">
                    <label class="form-label">WhatsApp Business Account ID *</label>
                    <input type="text" class="form-input" placeholder="987654321098765" x-model="form.whatsapp_business_account_id" required>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Found in Meta Business Manager under WhatsApp Accounts</div>
                </div>

                <div class="form-group">
                    <label class="form-label">WhatsApp API Token *</label>
                    <input type="password" class="form-input" placeholder="EAABa..." x-model="form.whatsapp_token" required>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Bearer token for Meta Cloud API authentication</div>
                </div>

                <div style="padding:14px;background:var(--primary-dim);border-radius:8px;font-size:12px;color:var(--primary);line-height:1.5">
                    <strong>⚠️ Security Note:</strong> Store this token securely. It grants full access to your WhatsApp messaging capabilities.
                </div>
            </div>

            {{-- Status Badge --}}
            <div style="padding:12px;background:var(--text-muted);opacity:0.1;border-radius:8px;text-align:center;font-size:12px;color:var(--text-muted)" x-show="!form.whatsapp_enabled">
                WhatsApp reminders are currently <strong>disabled</strong> for this gym.
            </div>

        </form>
    </div>

</div>
@endsection

@push('scripts')
<script>
function whatsAppSettings() {
    return {
        form: {
            whatsapp_enabled: @json((bool) $gym->whatsapp_enabled),
            whatsapp_token: '{{ $gym->whatsapp_token ? '••••••••' : '' }}',
            whatsapp_phone_number_id: @json($gym->whatsapp_phone_number_id ?? ''),
            whatsapp_business_account_id: @json($gym->whatsapp_business_account_id ?? ''),
        },
        saving: false,

        init() {},

        async save() {
            this.saving = true;
            try {
                const payload = {
                    whatsapp_enabled: this.form.whatsapp_enabled,
                };
                if (this.form.whatsapp_enabled) {
                    payload.whatsapp_token = this.form.whatsapp_token;
                    payload.whatsapp_phone_number_id = this.form.whatsapp_phone_number_id;
                    payload.whatsapp_business_account_id = this.form.whatsapp_business_account_id;
                }
                await post(`/gyms/{{ $gym->id }}/whatsapp`, payload);
                toast('WhatsApp configuration saved successfully.');
                window.location.reload();
            } catch (e) {
                toast(e.message, 'error');
            }
            this.saving = false;
        }
    };
}
</script>
@endpush
