<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Usuario;
use App\Services\Auth\MfaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class AuthenticatedSessionController extends Controller
{
    protected $mfaService;

    /**
     * Create a new controller instance.
     */
    public function __construct(MfaService $mfaService)
    {
        $this->mfaService = $mfaService;
        $this->middleware('guest')->except(['destroy', 'redirect', 'callback']);
    }

    /**
     * Display the login view.
     *
     * @return View
     */
    public function create(): View
    {
        return view('vp360-landing.acceso-a-miembros.index');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(LoginRequest $request): JsonResponse
    {
        if (!Auth::validate($request->only('email', 'password'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $usuario = Usuario::where('email', $request->email)->first();

        $this->mfaService->generarYEnviarCodigo($usuario, $request);

        $request->session()->put('mfa_email', $usuario->email);

        return response()->json(['mfa_required' => true, 'email' => $usuario->email]);
    }

    /**
     * Reenvia el código de verificación al correo electrónico del usuario.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reenviarCodigo(Request $request): JsonResponse
    {
        if (!$request->session()->has('mfa_email')) {
            return response()->json(['message' => 'Sesión no encontrada.'], 400);
        }

        $usuario = Usuario::where('email', $request->session()->get('mfa_email'))->firstOrFail();

        $this->mfaService->generarYEnviarCodigo($usuario, $request);

        return response()->json(['status' => 'success', 'message' => 'Se ha enviado un nuevo código.']);

    }

    /**
     * Verifica el código de verificación ingresado por el usuario.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verificarCodigo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['codigo_verificacion' => 'required|string|digits:6']);
        if ($validator->fails()) {
            return response()->json(['message' => 'Por favor, ingresa un código de 6 dígitos.'], 422);
        }

        if (!$request->session()->has('mfa_email')) {
            return response()->json(['message' => 'Tu sesión ha expirado.'], 400);
        }

        $usuario = Usuario::where('email', $request->session()->get('mfa_email'))->firstOrFail();

        try {
            $this->mfaService->verificarCodigo($usuario, $request->codigo_verificacion);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        Auth::login($usuario);
        $request->session()->regenerate();
        $request->session()->forget('mfa_email');

        return response()->json(['status' => 'success', 'redirect_url' => route('dashboard')]);
    }

    /**
     * Redirect the user to the Google SSO provider.
     *
     * @return RedirectResponse
     */
    public function redirect(): RedirectResponse
    {
        return $this->mfaService->redireccionarAProveedor();
    }

    /**
     * Obtain the user information from Google SSO and log them in.
     *
     * @return RedirectResponse
     */
    public function callback(): RedirectResponse
    {
        try {
            $user = $this->mfaService->obtenerUsuarioDesdeGoogle();

            Auth::login($user);
            request()->session()->regenerate();
            request()->session()->forget('mfa_email');

            return redirect()->route('dashboard');
        } catch (Exception $e) {
            Log::error('Error en el callback de Google SSO: ' . $e->getMessage());
            return redirect()->route('login')->withErrors(['email' => $e->getMessage()]);
        }
    }

    /**
     * Destroy an authenticated session.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
