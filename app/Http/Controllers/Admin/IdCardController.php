<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\doctor;
use App\Models\employee;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Browser-print ID cards for hospital facilities: a single staff member when a
 * user is bound, otherwise the whole active roster honouring the panel's
 * role/search filters. The browser's print dialog is the delivery - no PDFs.
 */
class IdCardController extends Controller
{
    public function print(Request $request, ?User $user = null): View
    {
        if (! hms_idcards_enabled()) {
            abort(404);
        }

        if (! hms_can('staff')) {
            abort(403);
        }

        $tenantId = (int) $request->user()->tenant_id;

        if ($user !== null) {
            // Never print another facility's card through a hand-crafted id.
            abort_if((int) $user->tenant_id !== $tenantId, 404);

            $staff = collect([$user]);
        } else {
            $staff = $this->staffQuery($request, $tenantId)->get();
        }

        return view('admins.prints.id_card', [
            'cards' => $staff->map(fn (User $row) => $this->cardFor($row))->all(),
            'hospital' => hms_tenant_brand()['name'],
        ]);
    }

    /**
     * Active staff of the tenant, narrowed by the same filters the grid uses.
     */
    private function staffQuery(Request $request, int $tenantId): Builder
    {
        $ticked = hms_tenant_required_roles();

        $role = $request->query('role');
        $search = $request->query('search');

        return User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with('role:id,name,slug,level')
            ->when($ticked !== [], fn ($q) => $q->whereHas(
                'role',
                fn ($r) => $r->whereIn('slug', $ticked)
            ))
            ->when(filled($role), fn ($q) => $q->whereHas(
                'role',
                fn ($r) => $r->where('slug', $role)
            ))
            ->when(filled($search), function ($q) use ($search) {
                $term = '%'.mb_strtolower((string) $search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$term]);
                });
            })
            ->orderBy('name');
    }

    /**
     * @return array<string, mixed>
     */
    private function cardFor(User $user): array
    {
        $employee = $this->employeeFor($user);

        return [
            'name' => $user->name,
            'role' => hms_role_label($user),
            'designation' => $user->designation,
            'department' => $user->department,
            'staff_code' => $this->staffCode($user, $employee),
            'phone' => $user->phone,
            'email' => $user->email,
            'photo' => storage_url($employee?->image, 'employee-placeholder.jpg'),
        ];
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
