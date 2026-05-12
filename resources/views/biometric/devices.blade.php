@extends('layouts.app')
@section('title', 'Biometric Devices')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">Biometric Devices</div>
        <div class="page-sub">ZKTeco machines connected to this gym</div>
    </div>
    <button class="btn btn-primary" onclick="openAdd()">+ Add Device</button>
</div>

@if($devices->isEmpty())
<div class="card">
    <div class="empty-state">
        <div class="icon">📡</div>
        <p>No devices registered. Add a ZKTeco machine to enable biometric check-in.</p>
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
                    <th>API Key</th>
                    <th>Last Seen</th>
                    <th style="text-align:center">Status</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($devices as $device)
                <tr id="row-{{ $device->id }}">
                    <td>
                        <div class="cell-main">{{ $device->name }}</div>
                    </td>
                    <td>
                        <div class="cell-main" style="font-family:monospace;font-size:12px">{{ $device->serial_number }}</div>
                        <div class="cell-sub">{{ $device->model ?? '—' }}</div>
                    </td>
                    <td>{{ $device->location ?? '—' }}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px">
                            <code id="key-{{ $device->id }}" style="font-size:11px;background:var(--bg-alt);padding:3px 7px;border-radius:4px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block">{{ $device->api_key }}</code>
                            <button class="btn btn-outline btn-sm" title="Copy" onclick="copyKey('{{ $device->api_key }}')">⎘</button>
                            <button class="btn btn-outline btn-sm" title="Regenerate" onclick="regenerateKey({{ $device->id }})">↺</button>
                        </div>
                    </td>
                    <td>
                        @if($device->last_seen_at)
                            <span title="{{ $device->last_seen_at->format('d-M-Y H:i:s') }}">{{ $device->last_seen_at->diffForHumans() }}</span>
                        @else
                            <span style="color:var(--text-muted)">Never</span>
                        @endif
                    </td>
                    <td style="text-align:center">
                        <span id="status-{{ $device->id }}" class="badge {{ $device->is_active ? 'badge-green' : 'badge-red' }}">
                            {{ $device->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td style="text-align:right">
                        <button class="btn btn-outline btn-sm" onclick="openEdit({{ $device->id }}, '{{ addslashes($device->name) }}', '{{ addslashes($device->model ?? '') }}', '{{ addslashes($device->location ?? '') }}')">Edit</button>
                        <button class="btn btn-outline btn-sm" onclick="toggleDevice({{ $device->id }})">Toggle</button>
                        <button class="btn btn-outline btn-sm" style="color:var(--danger)" onclick="deleteDevice({{ $device->id }})">Delete</button>
                    </td>
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
        <p>Configure your ZKTeco machine in its web panel or LCD menu:</p>
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
                    <label class="form-label">Device Name *</label>
                    <input class="form-input" name="name" placeholder="e.g. Main Entrance" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Serial Number *</label>
                    <input class="form-input" name="serial_number" placeholder="From machine info screen" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Model</label>
                    <input class="form-input" name="model" placeholder="e.g. F18, K40, MA300, SpeedFace">
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input class="form-input" name="location" placeholder="e.g. Front door, Gym floor">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn btn-primary">Register & Get API Key</button>
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
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- API Key Reveal Modal --}}
<div id="keyModal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <div class="modal-title">Device Registered</div>
            <button class="modal-close" onclick="closeModals();window.location.reload()">✕</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-muted);margin-bottom:12px;font-size:13px">Copy this API key and enter it in your ZKTeco machine settings. You can always view it later from the devices list.</p>
            <div style="display:flex;align-items:center;gap:8px">
                <code id="newApiKey" style="flex:1;background:var(--bg-alt);padding:10px 14px;border-radius:6px;font-size:13px;word-break:break-all"></code>
                <button class="btn btn-primary btn-sm" onclick="copyKey(document.getElementById('newApiKey').textContent)">Copy</button>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="closeModals();window.location.reload()">Done</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openAdd()  { document.getElementById('addModal').style.display  = 'flex'; }
function openEdit(id, name, model, location) {
    document.getElementById('editId').value       = id;
    document.getElementById('editName').value     = name;
    document.getElementById('editModel').value    = model;
    document.getElementById('editLocation').value = location;
    document.getElementById('editModal').style.display = 'flex';
}
function closeModals() {
    ['addModal','editModal','keyModal'].forEach(id => document.getElementById(id).style.display = 'none');
}

async function submitAdd(e) {
    e.preventDefault();
    const form = e.target;
    const body = Object.fromEntries(new FormData(form));
    try {
        const res = await post('{{ route('biometric.devices.store') }}', body);
        document.getElementById('newApiKey').textContent = res.device.api_key;
        document.getElementById('addModal').style.display = 'none';
        document.getElementById('keyModal').style.display = 'flex';
        form.reset();
    } catch(err) { toast(err.message, 'error'); }
}

async function submitEdit(e) {
    e.preventDefault();
    const id   = document.getElementById('editId').value;
    const form = e.target;
    const body = Object.fromEntries(new FormData(form));
    try {
        await put(`/biometric/devices/${id}`, body);
        toast('Device updated', 'success');
        closeModals();
        setTimeout(() => window.location.reload(), 800);
    } catch(err) { toast(err.message, 'error'); }
}

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
    navigator.clipboard.writeText(key.trim()).then(() => toast('API key copied', 'success'));
}
</script>
@endpush
