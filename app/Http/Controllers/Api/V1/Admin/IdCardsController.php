<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\doctor;
use App\Models\employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile mirror of the hospital ID-card grid. Returns the display fields the
 * app needs to render a card; the endpoint resolves the tenant itself.
 */
class IdCardsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! hms_idcards_enabled()) {
            abort(404);
        }

        if (! hms_can('staff', $request->user())) {
            abort(403);
        }

        $ticked = hms_tenant_required_roles($request->user());
        $hospital = hms_tenant_brand()['name'];

        $users = User::where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->with('role:id,name,slug,level')
            ->when($ticked !== [], fn ($q) => $q->whereHas(
                'role',
                fn ($r) => $r->whereIn('slug', $ticked)
            ))
            ->orderBy('name')
            ->get();

        $data = $users->map(function (User $user) use ($hospital) {
            $employee = $this->employeeFor($user);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'role' => hms_role_label($user),
                'designation' => $user->designation,
                'department' => $user->department,
                'staff_code' => $this->staffCode($user, $employee),
                'phone' => $user->phone,
                'email' => $user->email,
                'photo_url' => storage_url($employee?->image, 'employee-placeholder.jpg'),
                'hospital_name' => $hospital,
            ];
        })->all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function employeeFor(User $user): ?employee
    {
        $profile = doctor::where('user_id', $user->id)->first();

        if ($profile && $profile->employ) {
            return $profile->employ;
        }

        return employee::where('email', $user->email)->first();
    }

    private function staffCode(User $user, ?employee $employee): string
    {
        $code = $employee?->getAttribute('code');

        return filled($code) ? (string) $code : 'STR-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);
    }
}
