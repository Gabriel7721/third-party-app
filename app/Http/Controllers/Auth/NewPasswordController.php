<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\NotOldPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;       // Facade cho reset
use Illuminate\Validation\Rules\Password as PasswordRule;         // Rule validate độ mạnh
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * Show the password reset page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Bước 1: validate tối thiểu để lấy email hợp lệ
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
        ]);

        // Lấy user theo email trong form reset
        $user = User::where('email', $request->input('email'))->firstOrFail();

        // Bước 2: validate mật khẩu (độ mạnh + không trùng mật khẩu hiện tại)
        $request->validate([
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8)->mixedCase()->numbers()->symbols(),
                new NotOldPassword($user), // rule bạn đã tạo
            ],
        ]);

        // Bước 3: gọi broker để reset (dùng Facade alias: PasswordBroker)
        $status = PasswordBroker::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->string('password')),
                ])->save();

                // tùy scaffold của bạn: $user->setRememberToken(Str::random(60));
                // event(new PasswordReset($user));
            }
        );

        return $status === PasswordBroker::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
