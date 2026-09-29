<?php

namespace App\Http\Controllers\Web;

use App\Biometric\Contracts\BiometricDriver;
use App\Biometric\DriverRegistry;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Services\UnknownBiometricDevices;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BiometricDeviceWebController extends Controller
{
    public function index(Request $request)
    {
        $gymId  = $this->gymId();
        $devices = BiometricDevice::where('gym_id', $gymId)->latest()->get();

        // Unregistered machines are platform-wide, so only the super admin sees them.
        $unknownDevices = [];
        if (auth()->user()->isAdmin()) {
            // Token-brand devices have no serial; array_flip() can't take nulls.
            $registered     = BiometricDevice::whereNotNull('serial_number')->pluck('serial_number')->flip();
            $unknownDevices = array_values(array_filter(
                app(UnknownBiometricDevices::class)->recent(),
                fn ($d) => ! isset($registered[$d['serial_number']])
            ));
        }

        $brands  = app(DriverRegistry::class)->options();
        $presets = config('biometric.generic_presets');

        return view('biometric.devices', compact('devices', 'unknownDevices', 'brands', 'presets'));
    }

    public function store(Request $request)
    {
        $registry = app(DriverRegistry::class);
        // Same default as the model: requests from before brands existed are ZKTeco.
        $request->mergeIfMissing(['brand' => 'zkteco']);
        $request->validate(['brand' => ['required', Rule::in($registry->keys())]]);
        $driver = $registry->get($request->input('brand'));

        $data = $request->validate(array_merge([
            'brand'         => ['required', Rule::in($registry->keys())],
            'serial_number' => [$driver->identifiesBy() === 'serial' ? 'required' : 'nullable', 'string', 'max:100', 'unique:biometric_devices,serial_number'],
            'name'          => 'required|string|max:100',
            'model'         => 'nullable|string|max:50',
            'location'      => 'nullable|string|max:100',
            'settings'      => 'nullable|array',
        ], $driver->settingsRules()));

        $data['settings'] = $this->cleanSettings($driver, $data['settings'] ?? []);
        $data['gym_id']   = $this->gymId();
        $data['api_key']  = BiometricDevice::generateApiKey();

        $device = BiometricDevice::create($data);
        if ($device->serial_number) {
            app(UnknownBiometricDevices::class)->forget($device->serial_number);
        }

        return response()->json(['ok' => true, 'device' => $device, 'webhook_url' => $this->webhookUrl($device)]);
    }

    public function update(Request $request, BiometricDevice $device)
    {
        abort_if($device->gym_id !== $this->gymId(), 403);

        // Brand is fixed after creation — changing it would invalidate the machine's config.
        $driver = app(DriverRegistry::class)->get($device->brand);

        $data = $request->validate(array_merge([
            'name'     => 'required|string|max:100',
            'model'    => 'nullable|string|max:50',
            'location' => 'nullable|string|max:100',
            'settings' => 'nullable|array',
        ], $driver->settingsRules()));

        if (array_key_exists('settings', $data)) {
            $data['settings'] = $this->cleanSettings($driver, $data['settings'] ?? []);
        }

        $device->update($data);

        return response()->json(['ok' => true]);
    }

    public function regenerateToken(BiometricDevice $device)
    {
        abort_if($device->gym_id !== $this->gymId(), 403);
        $device->regenerateWebhookToken();

        return response()->json(['ok' => true, 'webhook_url' => $this->webhookUrl($device)]);
    }

    /** Data for the Setup / Test panel: steps, URL, last payload, and what we'd extract from it. */
    public function setup(Request $request, BiometricDevice $device)
    {
        abort_if($device->gym_id !== $this->gymId(), 403);

        $driver  = app(DriverRegistry::class)->get($device->brand);
        $preview = ['count' => 0, 'punches' => [], 'error' => null];

        if ($device->last_payload) {
            try {
                // Replay the stored body through the driver. Form/multipart bodies were stored as
                // query strings or JSON, so non-JSON payloads are also offered as parsed fields.
                $replay = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $device->last_payload);
                if (! is_array(json_decode($device->last_payload, true))) {
                    parse_str($device->last_payload, $fields);
                    $replay = Request::create('/', 'POST', $fields, [], [], [], $device->last_payload);
                }

                $logs = $driver->parse($replay, $device);
                $preview['count']   = count($logs);
                $preview['punches'] = array_map(fn ($l) => [
                    'employee_id' => $l->employeeId,
                    'time'        => $l->time->format('Y-m-d H:i:s'),
                    'type'        => $l->type,
                ], array_slice($logs, 0, 10));
            } catch (\Throwable $e) {
                $preview['error'] = $e->getMessage();
            }
        }

        return response()->json([
            'steps'           => $driver->setupSteps($device, $request->getSchemeAndHttpHost()),
            'webhook_url'     => $this->webhookUrl($device),
            'last_payload'    => $device->last_payload,
            'last_payload_at' => $device->last_payload_at?->diffForHumans(),
            'preview'         => $preview,
        ]);
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

    private function webhookUrl(BiometricDevice $device): ?string
    {
        return app(DriverRegistry::class)->get($device->brand)->identifiesBy() === 'token'
            ? route('biometric.hook', $device->webhook_token)
            : null;
    }

    /** Keep only the keys the driver declares, minus blanks. */
    private function cleanSettings(BiometricDriver $driver, array $settings): array
    {
        $allowed = array_map(fn ($rule) => substr($rule, strlen('settings.')), array_keys($driver->settingsRules()));

        return array_filter(
            array_intersect_key($settings, array_flip($allowed)),
            fn ($v) => $v !== null && $v !== ''
        );
    }

    private function gymId(): int
    {
        $user = auth()->user();
        return $user->isAdmin()
            ? (int) session('admin_active_gym_id')
            : $user->gym_id;
    }
}
