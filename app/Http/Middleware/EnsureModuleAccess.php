<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()->getName() ?? '';
        $permission = str_starts_with($name, 'requests.') ? 'requests' : $name;
        if (array_key_exists($permission, User::permissionOptions())) {
            abort_unless($request->user()->canAccess($permission), 403, 'Bạn chưa được cấp quyền truy cập chức năng này.');
        }
        if ($name === 'attachments.download') {
            abort_unless($request->user()->canAccess('requests') || $request->user()->canAccess('library'), 403);
        }

        return $next($request);
    }
}
