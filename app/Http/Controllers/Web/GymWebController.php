<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GymWebController extends Controller
{
    public function index()
    {
        $gyms = Gym::withTrashed(false)
            ->withCount(['users as members_count' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'member')),
                         'users as trainers_count' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'trainer'))])
            ->with('owner:id,name,email,gym_id')
            ->latest()
            ->get();

        $activeGymId = session('admin_active_gym_id');

        return view('gyms.index', compact('gyms', 'activeGymId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255'],
            'domain'         => ['nullable', 'string', 'max:255', 'unique:gyms,domain'],
            'subdomain'      => ['nullable', 'alpha_dash', 'max:63', 'unique:gyms,subdomain'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'city'           => ['nullable', 'string', 'max:100'],
            'country'        => ['nullable', 'string', 'max:100'],
            'owner_name'     => ['required', 'string', 'max:255'],
            'owner_email'    => ['required', 'email', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:6'],
        ]);

        $gym = DB::transaction(function () use ($data) {
            $gym = Gym::create([
                'name'    => $data['name'],
                'slug'    => Str::slug($data['name']) . '-' . Str::random(4),
                'domain'  => $this->normalizeDomain($data['domain'] ?? null),
                'subdomain' => $this->normalizeSubdomain($data['subdomain'] ?? null),
                'email'   => $data['email'],
                'phone'   => $data['phone'] ?? null,
                'city'    => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'status'  => 'active',
            ]);

            $owner = User::create([
                'gym_id'   => $gym->id,
                'name'     => $data['owner_name'],
                'email'    => $data['owner_email'],
                'password' => Hash::make($data['owner_password']),
                'status'   => 'active',
            ]);
            $owner->assignRole('owner');

            return $gym;
        });

        return response()->json(['message' => 'Gym created successfully.', 'gym' => $gym->load('owner:id,name,email,gym_id')], 201);
    }

    public function update(Request $request, Gym $gym)
    {
        $data = $request->validate([
            'name'    => ['sometimes', 'string', 'max:255'],
            'email'   => ['sometimes', 'email', 'max:255'],
            'domain'  => ['nullable', 'string', 'max:255', 'unique:gyms,domain,' . $gym->id],
            'subdomain' => ['nullable', 'alpha_dash', 'max:63', 'unique:gyms,subdomain,' . $gym->id],
            'phone'   => ['nullable', 'string', 'max:20'],
            'city'    => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
        ]);

        if (array_key_exists('domain', $data)) {
            $data['domain'] = $this->normalizeDomain($data['domain']);
        }

        if (array_key_exists('subdomain', $data)) {
            $data['subdomain'] = $this->normalizeSubdomain($data['subdomain']);
        }

        $gym->update($data);

        return response()->json(['message' => 'Gym updated successfully.', 'gym' => $gym->fresh()->load('owner:id,name,email,gym_id')]);
    }

    public function destroy(Gym $gym)
    {
        $gym->delete();
        return response()->json(['message' => 'Gym deleted successfully.']);
    }

    public function toggleStatus(Gym $gym)
    {
        $gym->update(['status' => $gym->status === 'active' ? 'suspended' : 'active']);
        return response()->json(['message' => 'Gym status updated.', 'status' => $gym->status]);
    }

    public function switchGym(Gym $gym)
    {
        session(['admin_active_gym_id' => $gym->id]);
        return response()->json(['message' => "Switched to {$gym->name}.", 'gym_id' => $gym->id, 'gym_name' => $gym->name]);
    }

    public function clearGym()
    {
        session()->forget('admin_active_gym_id');
        return response()->json(['message' => 'Viewing all gyms.']);
    }

    public function manageModules(Gym $gym)
    {
        $available = config('modules.available');
        $enabled   = $gym->enabledModules();

        return view('gyms.modules', compact('gym', 'available', 'enabled'));
    }

    public function updateModules(Request $request, Gym $gym)
    {
        $available = array_keys(config('modules.available'));

        $data = $request->validate([
            'modules'   => ['nullable', 'array'],
            'modules.*' => ['string', 'in:' . implode(',', $available)],
        ]);

        $gym->update(['modules' => $data['modules'] ?? []]);

        return response()->json([
            'message' => 'Modules updated successfully.',
            'modules' => $gym->fresh()->enabledModules(),
        ]);
    }

    public function manageWhatsAppSettings(Gym $gym)
    {
        return view('gyms.whatsapp-settings', compact('gym'));
    }

    public function updateWhatsAppSettings(Request $request, Gym $gym)
    {
        $data = $request->validate([
            'whatsapp_enabled'         => ['boolean'],
            'whatsapp_token'           => ['required_if:whatsapp_enabled,true', 'string', 'max:500'],
            'whatsapp_phone_number_id' => ['required_if:whatsapp_enabled,true', 'string', 'max:100'],
            'whatsapp_business_account_id' => ['required_if:whatsapp_enabled,true', 'string', 'max:100'],
        ]);

        $gym->update($data);

        return response()->json([
            'message' => 'WhatsApp settings updated successfully.',
            'gym' => $gym->fresh()->only(['whatsapp_enabled', 'whatsapp_token', 'whatsapp_phone_number_id', 'whatsapp_business_account_id']),
        ]);
    }

    private function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null || trim($domain) === '') {
            return null;
        }

        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);

        return rtrim($domain, '/');
    }

    private function normalizeSubdomain(?string $subdomain): ?string
    {
        if ($subdomain === null || trim($subdomain) === '') {
            return null;
        }

        return strtolower(trim($subdomain));
    }
}
