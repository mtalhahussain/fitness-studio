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
            'phone'   => ['nullable', 'string', 'max:20'],
            'city'    => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
        ]);

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
}
