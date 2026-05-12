<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;

class BiometricDeviceWebController extends Controller
{
    public function index(Request $request)
    {
        $gymId  = $this->gymId();
        $devices = BiometricDevice::where('gym_id', $gymId)->latest()->get();

        return view('biometric.devices', compact('devices'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'serial_number' => 'required|string|max:100|unique:biometric_devices,serial_number',
            'name'          => 'required|string|max:100',
            'model'         => 'nullable|string|max:50',
            'location'      => 'nullable|string|max:100',
        ]);

        $data['gym_id']  = $this->gymId();
        $data['api_key'] = BiometricDevice::generateApiKey();

        $device = BiometricDevice::create($data);

        return response()->json(['ok' => true, 'device' => $device]);
    }

    public function update(Request $request, BiometricDevice $device)
    {
        abort_if($device->gym_id !== $this->gymId(), 403);

        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'model'    => 'nullable|string|max:50',
            'location' => 'nullable|string|max:100',
        ]);

        $device->update($data);

        return response()->json(['ok' => true]);
    }

    public function destroy(BiometricDevice $device)
    {
        abort_if($device->gym_id !== $this->gymId(), 403);
        $device->delete();

        return response()->json(['ok' => true]);
    }

    public function toggleStatus(BiometricDevice $device)
    {
        abort_if($device->gym_id !== $this->gymId(), 403);
        $device->update(['is_active' => ! $device->is_active]);

        return response()->json(['ok' => true, 'is_active' => $device->is_active]);
    }

    public function regenerateKey(BiometricDevice $device)
    {
        abort_if($device->gym_id !== $this->gymId(), 403);
        $device->update(['api_key' => BiometricDevice::generateApiKey()]);

        return response()->json(['ok' => true, 'api_key' => $device->api_key]);
    }

    private function gymId(): int
    {
        $user = auth()->user();
        return $user->isAdmin()
            ? (int) session('admin_active_gym_id')
            : $user->gym_id;
    }
}
