<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function form()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $dados = $request->validate(['email' => 'required|email', 'password' => 'required']);

        if (! Auth::attempt($dados + ['ativo' => true], $request->boolean('lembrar'))) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }
        $request->session()->regenerate();
        return redirect()->intended(route('painel'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
