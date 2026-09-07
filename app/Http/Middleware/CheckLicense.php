<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // Super admin lisans kontrolünden muaf
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Customer portal kullanıcıları lisans kontrolünden muaf
        if ($user->isCustomerPortal()) {
            return $next($request);
        }

        $company = $user->company;

        if (!$company) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Firma kaydınız bulunamadı.',
                    'error' => 'company_not_found',
                ], 403);
            }
            abort(403, 'Firma kaydınız bulunamadı. Lütfen yöneticinizle iletişime geçin.');
        }

        if (!$company->is_active) {
            // API istekleri session kullanmaz — sadece JSON yanıt dön
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Firmanız devre dışı bırakılmıştır.',
                    'error' => 'company_inactive',
                ], 403);
            }

            auth()->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Firmanız devre dışı bırakılmıştır. Lütfen platform yöneticisiyle iletişime geçin.',
            ]);
        }

        if (!$company->isLicenseActive()) {
            // API Talebi ise JSON dön
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Firmanızın kullanım süresi dolmuştur. Lütfen ödeme yapınız.',
                    'error' => 'payment_required',
                    'billing_url' => route('billing.index'),
                ], 402);
            }

            // Lisans süresi dolmuşsa özel bir sayfaya yönlendir
            if (!$request->routeIs('license.expired') && !$request->routeIs('logout') && !$request->routeIs('profile.*') && !$request->routeIs('billing.*')) {
                return redirect()->route('license.expired');
            }
        }

        return $next($request);
    }
}
