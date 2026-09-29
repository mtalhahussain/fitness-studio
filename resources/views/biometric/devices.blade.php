@extends('layouts.app')
@section('title', 'Biometric Devices')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">Biometric Devices</div>
        <div class="page-sub">Attendance machines connected to this gym</div>
    </div>
    <button class="btn btn-primary" onclick="openAdd()">+ Add Device</button>
</div>

@if($devices->isEmpty())
<div class="card">
    <div class="empty-state">
        <div class="icon">📡</div>
        <p>No devices registered. Add a machine to enable biometric check-in.</p>
    </div>
</div>
@else
<div class="card" style="padding:0">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Device</th>
                    <th>Serial / Model</th>
                    <th>Location</th>
                    <th>Key / URL</th>
                    <th>Connection</th>
                    <th style="text-align:center">Status</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($devices as $device)
                <tr id="row-{{ $device->id }}">
                    <td>
                        <div class="cell-main">{{ $device->name }}</div>
                        <span class="badge badge-purple" style="font-size:10px">{{ $brands[$device->brand] ?? $device->brand }}</span>
                    </td>
                    <td>
                        <div class="cell-main" style="font-family:monospace;font-size:12px">{{ $device->serial_number ?: '—' }}</div>
                        <div class="cell-sub">{{ $device->model ?? '—' }}</div>
                    </td>
                    <td>{{ $device->location ?? '—' }}</td>
                    <td>
                        @if($device->brand === 'zkteco')
                        <div style="display:flex;align-items:center;gap:6px">
                            <code id="key-{{ $device->id }}" style="font-size:11px;background:var(--bg-alt);padding:3px 7px;border-radius:4px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block">{{ $device->api_key }}</code>
                            <button class="btn btn-outline btn-sm" title="Copy" onclick="copyKey('{{ $device->api_key }}')">⎘</button>
                            <button class="btn btn-outline btn-sm" title="Regenerate" onclick="regenerateKey({{ $device->id }})">↺</button>
                        </div>
                        @else
                        <span class="cell-sub">Uses its own URL — see Setup / Test</span>
                        @endif
                    </td>
                    <td>
                        @php
                            [$connClass, $connLabel] = [
                                'online'   => ['badge-green',  '🟢 Online'],
                                'offline'  => ['badge-red',    '🔴 Offline'],
                                'never'    => ['badge-gray',   '⚪ Never connected'],
                                'disabled' => ['badge-yellow', '🟡 Connecting, but disabled'],
                            ][$device->connectionState()];
                        @endphp
                        <span class="badge {{ $connClass }}" style="font-size:10px;white-space:nowrap">{{ $connLabel }}</span>
                        <div class="cell-sub" style="margin-top:3px">
                            @if($device->last_seen_at)
                                <span title="{{ $device->last_seen_at->format('d-M-Y H:i:s') }}">{{ $device->last_seen_at->diffForHumans() }}</span>
                            @else
                                Waiting for first contact
                            @endif
                        </div>
                    </td>
                    <td style="text-align:center">
                        <span id="status-{{ $device->id }}" class="badge {{ $device->is_active ? 'badge-green' : 'badge-red' }}">
                            {{ $device->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td style="text-align:right">
                        <button class="btn btn-primary btn-sm" onclick="openSetup({{ $device->id }})">Setup / Test</button>
                        <button class="btn btn-outline btn-sm" onclick="openEdit({{ \Illuminate\Support\Js::from($device->only(['id', 'name', 'model', 'location', 'brand', 'settings'])) }})">Edit</button>
                        <button class="btn btn-outline btn-sm" onclick="toggleDevice({{ $device->id }})">Toggle</button>
                        <button class="btn btn-outline btn-sm" style="color:var(--danger)" onclick="deleteDevice({{ $device->id }})">Delete</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="padding:10px 16px;font-size:12px;color:var(--text-muted);border-top:1px solid var(--border)">
        Online = the machine contacted this gym in the last {{ \App\Models\BiometricDevice::ONLINE_MINUTES }} minutes. Machines check in every few seconds on their own, so a newly configured machine should turn Online within a minute. Refresh the page to update.
    </div>
</div>
@endif

@if(!empty($unknownDevices))
<div class="card" style="margin-top:20px;border-color:var(--warning)">
    <div class="card-header">
        <div>
            <div class="card-title">⚠ Unregistered machines trying to connect</div>
            <div class="card-subtitle">These serial numbers are not registered in any gym, so their punches are rejected. Add the device (with this exact serial number) inside the correct gym. Only super admins see this.</div>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Serial Number</th><th>IP Address</th><th>Last Try</th><th>Attempts</th></tr>
            </thead>
            <tbody>
                @foreach($unknownDevices as $u)
                <tr>
                    <td style="font-family:monospace;font-size:12px">{{ $u['serial_number'] }}</td>
                    <td class="cell-sub">{{ $u['ip'] ?? '—' }}</td>
                    <td class="cell-sub">{{ \Carbon\Carbon::parse($u['last_seen_at'])->diffForHumans() }}</td>
                    <td class="cell-sub">{{ $u['hits'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Setup Instructions --}}
<div class="card" style="margin-top:20px">
    <div class="card-header">
        <div class="card-title">Machine Setup Guide</div>
    </div>
    <div style="padding:0 20px 20px;font-size:13px;line-height:1.8;color:var(--text-muted)">
        <p>Each device's exact steps are under <strong>Setup / Test</strong> in its row. Quick reference for ZKTeco / eSSL / Realtime:</p>
        <table style="border-collapse:collapse;width:100%;max-width:500px;margin-top:8px">
            <tr>
                <td style="padding:4px 12px 4px 0;font-weight:600;color:var(--text)">Server Address</td>
                <td><code style="background:var(--bg-alt);padding:2px 8px;border-radius:4px">{{ request()->getSchemeAndHttpHost() }}</code></td>
            </tr>
            <tr>
                <td style="padding:4px 12px 4px 0;font-weight:600;color:var(--text)">URL Path</td>
                <td><code style="background:var(--bg-alt);padding:2px 8px;border-radius:4px">/api/biometric/push</code></td>
            </tr>
            <tr>
                <td style="padding:4px 12px 4px 0;font-weight:600;color:var(--text)">Port</td>
                <td><code style="background:var(--bg-alt);padding:2px 8px;border-radius:4px">{{ request()->getPort() }}</code></td>
            </tr>
            <tr>
                <td style="padding:4px 12px 4px 0;font-weight:600;color:var(--text)">API Key</td>
                <td>Copy from device row above and paste into machine's <em>Password</em> / <em>API Key</em> field</td>
            </tr>
            <tr>
                <td style="padding:4px 12px 4px 0;font-weight:600;color:var(--text)">Employee Number</td>
                <td>Enroll each member with their <strong>User ID</strong> from this system as Employee Number</td>
            </tr>
        </table>
    </div>
</div>

{{-- Add Modal --}}
<div id="addModal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <div class="modal-title">Register Device</div>
            <button class="modal-close" onclick="closeModals()">✕</button>
        </div>
        <form onsubmit="submitAdd(event)">
            <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
                <div class="form-group">
                    <label class="form-label">Brand *</label>
                    <select class="form-select" name="brand" id="addBrand" onchange="syncBrand('add')" required>
                        @foreach($brands as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Device Name *</label>
                    <input class="form-input" name="name" placeholder="e.g. Main Entrance" required>
                </div>
                <div class="form-group" data-brand-show="zkteco,hikvision">
                    <label class="form-label">Serial Number <span data-brand-show="zkteco">*</span></label>
                    <input class="form-input" name="serial_number" id="addSerial" placeholder="From machine info screen">
                </div>
                <div class="form-group">
                    <label class="form-label">Model</label>
                    <input class="form-input" name="model" placeholder="e.g. F18, K40, DS-K1T671">
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input class="form-input" name="location" placeholder="e.g. Front door, Gym floor">
                </div>
                @include('biometric._settings-fields', ['prefix' => 'add'])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn btn-primary">Register Device</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="editModal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <div class="modal-title">Edit Device</div>
            <button class="modal-close" onclick="closeModals()">✕</button>
        </div>
        <form onsubmit="submitEdit(event)">
            <input type="hidden" id="editId">
            <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
                <input type="hidden" id="editBrand">
                <div class="form-group">
                    <label class="form-label">Device Name *</label>
                    <input class="form-input" id="editName" name="name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Model</label>
                    <input class="form-input" id="editModel" name="model">
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input class="form-input" id="editLocation" name="location">
                </div>
                @include('biometric._settings-fields', ['prefix' => 'edit'])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- Setup / Test Modal --}}
<div id="setupModal" class="modal-overlay" style="display:none">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">Setup / Test</div>
            <button class="modal-close" onclick="closeSetup()">✕</button>
        </div>
        <div class="modal-body" style="display:flex;flex-direction:column;gap:16px;font-size:13px">
            <div id="setupUrlBox" style="display:none">
                <div class="form-label">This device's URL</div>
                <div style="display:flex;gap:8px;align-items:center">
                    <code id="setupUrl" style="flex:1;background:var(--bg-alt);padding:8px 12px;border-radius:6px;word-break:break-all"></code>
                    <button class="btn btn-primary btn-sm" onclick="copyKey(document.getElementById('setupUrl').textContent)">Copy</button>
                    <button class="btn btn-outline btn-sm" id="regenTokenBtn">↺ New URL</button>
                </div>
                <div id="regenConfirm" class="cell-sub" style="display:none;margin-top:6px">
                    The old URL will stop working and the machine must be updated.
                    <button class="btn btn-danger btn-sm" id="regenTokenYes">Yes, make new URL</button>
                </div>
            </div>
            <div>
                <div class="form-label">Steps</div>
                <ol id="setupSteps" style="padding-left:18px;line-height:1.8;color:var(--text)"></ol>
            </div>
            <div>
                <div class="form-label">Last received data <span id="setupPayloadAt" class="cell-sub"></span></div>
                <div id="setupPreview" style="margin-bottom:6px"></div>
                <pre id="setupPayload" style="background:var(--bg-alt);padding:10px;border-radius:6px;max-height:260px;overflow:auto;white-space:pre-wrap;font-size:11px"></pre>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="openSetup(currentSetupId)">↻ Refresh</button>
            <button class="btn btn-primary" onclick="closeSetup()">Close</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
    /* Inline display styles would otherwise beat the hidden attribute. */
    [data-brand-show][hidden] { display: none !important; }
</style>
<script>
const PRESETS = @json($presets);
let currentSetupId = null;
let reloadOnSetupClose = false;

function syncBrand(prefix) {
    const brand = document.getElementById(prefix + 'Brand').value;
    const modal = document.getElementById(prefix + 'Modal');
    modal.querySelectorAll('[data-brand-show]').forEach(el => {
        el.hidden = !el.dataset.brandShow.split(',').includes(brand);
    });
    if (prefix === 'add') document.getElementById('addSerial').required = (brand === 'zkteco');
}

function applyPreset(prefix, key) {
    const p = PRESETS[key]; if (!p) return;
    const modal = document.getElementById(prefix + 'Modal');
    Object.entries(p).forEach(([field, value]) => {
        const input = modal.querySelector(`[name="settings[${field}]"]`);
        if (input) input.value = value;
    });
}

// Hidden brand fields are still in the form; the server keeps only the keys the brand declares.
function formBody(form) {
    const body = { settings: {} };
    for (const [k, v] of new FormData(form)) {
        const m = k.match(/^settings\[(.+)\]$/);
        if (m) { if (v !== '') body.settings[m[1]] = v; } else body[k] = v;
    }
    return body;
}

function openAdd() {
    document.getElementById('addModal').style.display = 'flex';
    syncBrand('add');
}

function openEdit(d) {
    document.getElementById('editId').value       = d.id;
    document.getElementById('editBrand').value    = d.brand;
    document.getElementById('editName').value     = d.name;
    document.getElementById('editModel').value    = d.model || '';
    document.getElementById('editLocation').value = d.location || '';
    const modal = document.getElementById('editModal');
    modal.querySelectorAll('[name^="settings["]').forEach(i => i.value = i.tagName === 'SELECT' ? i.options[0].value : '');
    Object.entries(d.settings || {}).forEach(([field, value]) => {
        const input = modal.querySelector(`[name="settings[${field}]"]`);
        if (input) input.value = value;
    });
    modal.style.display = 'flex';
    syncBrand('edit');
}

function closeModals() {
    ['addModal','editModal','setupModal'].forEach(id => document.getElementById(id).style.display = 'none');
}

function closeSetup() {
    closeModals();
    if (reloadOnSetupClose) window.location.reload();
}

async function submitAdd(e) {
    e.preventDefault();
    const form = e.target;
    try {
        const res = await post('{{ route('biometric.devices.store') }}', formBody(form));
        form.reset();
        document.getElementById('addModal').style.display = 'none';
        toast('Device registered', 'success');
        openSetup(res.device.id, true);
    } catch(err) { toast(err.message, 'error'); }
}

async function submitEdit(e) {
    e.preventDefault();
    const id = document.getElementById('editId').value;
    try {
        await put(`/biometric/devices/${id}`, formBody(e.target));
        toast('Device updated', 'success');
        closeModals();
        setTimeout(() => window.location.reload(), 800);
    } catch(err) { toast(err.message, 'error'); }
}

function badge(cls, text) {
    const s = document.createElement('span');
    s.className = 'badge ' + cls;
    s.textContent = text;
    return s;
}

// Payload content comes from the machine, so everything below is set via textContent.
function renderPreview(d) {
    const prev = document.getElementById('setupPreview');
    prev.replaceChildren();
    if (!d.last_payload) return;
    if (d.preview.error)          return prev.append(badge('badge-red', 'Could not read: ' + d.preview.error));
    if (d.preview.count === 0)    return prev.append(badge('badge-yellow', 'Data received but no punches extracted — check mapping'));

    prev.append(badge('badge-green', `✓ ${d.preview.count} punch(es) read`), ' ');
    const list = document.createElement('span');
    list.className = 'cell-sub';
    list.textContent = d.preview.punches.map(p => `#${p.employee_id} @ ${p.time}${p.type ? ' (' + p.type + ')' : ''}`).join(' · ');
    prev.append(list);
}

async function openSetup(id, reloadOnClose = false) {
    currentSetupId = id;
    if (reloadOnClose) reloadOnSetupClose = true;
    try {
        const d = await get(`/biometric/devices/${id}/setup`);
        document.getElementById('setupUrlBox').style.display = d.webhook_url ? 'block' : 'none';
        document.getElementById('setupUrl').textContent = d.webhook_url || '';
        document.getElementById('regenConfirm').style.display = 'none';

        const steps = document.getElementById('setupSteps');
        steps.replaceChildren(...d.steps.map(s => {
            const li = document.createElement('li'); li.textContent = s; return li;
        }));

        document.getElementById('setupPayloadAt').textContent = d.last_payload_at ? `(${d.last_payload_at})` : '';
        let pretty = d.last_payload || 'Nothing received yet. Make a test punch on the machine, then press Refresh.';
        try { if (d.last_payload) pretty = JSON.stringify(JSON.parse(d.last_payload), null, 2); } catch (_) {}
        document.getElementById('setupPayload').textContent = pretty;
        renderPreview(d);

        document.getElementById('setupModal').style.display = 'flex';
    } catch(err) { toast(err.message, 'error'); }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('regenTokenBtn').onclick = () => document.getElementById('regenConfirm').style.display = 'block';
    document.getElementById('regenTokenYes').onclick = async () => {
        try {
            const res = await post(`/biometric/devices/${currentSetupId}/regenerate-token`);
            document.getElementById('setupUrl').textContent = res.webhook_url;
            document.getElementById('regenConfirm').style.display = 'none';
            toast('New URL created — update it on the machine', 'info');
        } catch(err) { toast(err.message, 'error'); }
    };
});

async function toggleDevice(id) {
    try {
        const res = await post(`/biometric/devices/${id}/toggle`);
        const badge = document.getElementById(`status-${id}`);
        badge.textContent = res.is_active ? 'Active' : 'Inactive';
        badge.className   = `badge ${res.is_active ? 'badge-green' : 'badge-red'}`;
    } catch(err) { toast(err.message, 'error'); }
}

async function deleteDevice(id) {
    if (!confirm('Delete this device? The machine will no longer be able to push attendance.')) return;
    try {
        await del(`/biometric/devices/${id}`);
        document.getElementById(`row-${id}`).remove();
        toast('Device removed', 'success');
    } catch(err) { toast(err.message, 'error'); }
}

async function regenerateKey(id) {
    if (!confirm('Regenerate API key? You will need to update the key in the machine settings.')) return;
    try {
        const res = await post(`/biometric/devices/${id}/regenerate-key`);
        document.getElementById(`key-${id}`).textContent = res.api_key;
        toast('API key regenerated — update machine settings', 'info');
    } catch(err) { toast(err.message, 'error'); }
}

function copyKey(key) {
    navigator.clipboard.writeText(key.trim()).then(() => toast('Copied', 'success'));
}
</script>
@endpush
