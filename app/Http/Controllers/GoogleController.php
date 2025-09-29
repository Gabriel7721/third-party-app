<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        // Chuyển hướng người dùng tới Google OAuth
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            // Lấy thông tin user từ Google
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()
                ->route('login')
                ->withErrors(['google' => 'Không thể xác thực với Google.']);
        }

        // Tìm user theo provider_id trước, sau đó theo email
        $user = User::where('provider', 'google')
            ->where('provider_id', $googleUser->getId())
            ->first();

        if (! $user && $googleUser->getEmail()) {
            $user = User::where('email', $googleUser->getEmail())->first();
        }

        if (! $user) {
            $user = User::create([
                'name'              => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User',
                'email'             => $googleUser->getEmail(),
                'password'          => Hash::make(Str::random(32)),
                'provider'          => 'google',
                'provider_id'       => $googleUser->getId(),
                'avatar'            => $googleUser->getAvatar(),
                'email_verified_at' => now(),
            ]);
        } else {
            $user->forceFill([
                'name'        => $user->name ?: ($googleUser->getName() ?: $googleUser->getNickname()),
                'provider'    => 'google',
                'provider_id' => $googleUser->getId(),
                'avatar'      => $googleUser->getAvatar() ?: $user->avatar,
            ])->save();
        }

        Auth::login($user, true);

        // Điều hướng về Inertia page Dashboard
        return redirect()->intended(route('dashboard'))
            ->with('success', 'Đăng nhập Google thành công.');
    }
}
