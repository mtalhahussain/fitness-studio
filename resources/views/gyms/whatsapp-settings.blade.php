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

                <div class="form-group">
                    <label class="form-label">Payment Reminder Template Name</label>
                    <input type="text" class="form-input" placeholder="payment_due" x-model="form.whatsapp_template_name">
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Name of your Meta-approved message template (leave blank to use the platform default)</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Template Language</label>
                    <select class="form-input" x-model="form.whatsapp_template_language">
                        <option value="en_US">English (US)</option>
                        <option value="en">English</option>
                        <option value="ur">Urdu</option>
                    </select>
                </div>

                <div x-data="{ open: false }" style="border:1px solid var(--border);border-radius:8px;overflow:hidden">
                    <button type="button" @click="open = !open" style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:var(--bg-secondary);border:none;cursor:pointer;font-size:13px;font-weight:600;color:var(--text)">
                        <span>How to create &amp; get your template approved on Meta</span>
                        <span x-text="open ? '−' : '+'"></span>
                    </button>
                    <div x-show="open" x-transition style="padding:14px;font-size:12px;color:var(--text-muted);line-height:1.7">
                        <ol style="margin:0;padding-left:18px">
                            <li>Log into <a href="https://business.facebook.com" target="_blank" style="color:var(--primary);text-decoration:underline">Meta Business Manager</a>.</li>
                            <li>Open <strong>WhatsApp Manager</strong> for this business, then go to <strong>Message Templates</strong>.</li>
                            <li>Click <strong>Create Template</strong> — pick a category (e.g. Utility for payment reminders), choose a language, and write the body text with variables (e.g. <code>@{{1}}</code>, <code>@{{2}}</code>) for name, invoice number, amount, due date.</li>
                            <li>Submit the template for review.</li>
                            <li>Wait for Meta's approval (usually within minutes to a few hours).</li>
                            <li>Once approved, copy the exact template <strong>name</strong> and <strong>language</strong> and paste them into the fields above.</li>
                        </ol>
                    </div>
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
            whatsapp_template_name: @json($gym->whatsapp_template_name ?? ''),
            whatsapp_template_language: @json($gym->whatsapp_template_language ?? 'en_US'),
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
                payload.whatsapp_template_name = this.form.whatsapp_template_name;
                payload.whatsapp_template_language = this.form.whatsapp_template_language;
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
