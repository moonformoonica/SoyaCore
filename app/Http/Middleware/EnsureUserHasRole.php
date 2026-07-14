<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Pakai sebagai 'role:manager' atau 'role:kasir,manager' di route.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! in_array($user->role, $roles, true)) {
            throw new ApiException(
                'tidak_berwenang',
                'Aksi ini hanya boleh dilakukan oleh role: '.implode(', ', $roles).'.',
                403,
            );
        }

        return $next($request);
    }
}
