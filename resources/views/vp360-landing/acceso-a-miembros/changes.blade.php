<style>
    .fade-out {
        animation: fadeOut 0.4s forwards;
    }

    .fade-in {
        animation: fadeIn 0.4s forwards;
    }

    @keyframes fadeOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-20px); }
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
<style>
    .sso-separator {
        display: flex;
        align-items: center;
        text-align: center;
        color: #aaa;
        margin: 25px 0;
    }
    .sso-separator::before,
    .sso-separator::after {
        content: '';
        flex: 1;
        border-bottom: 1px solid #ddd;
    }
    .sso-separator:not(:empty)::before {
        margin-right: .25em;
    }
    .sso-separator:not(:empty)::after {
        margin-left: .25em;
    }

    .google-sso-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #ddd;
        border-radius: 3px;
        background-color: #ffffff;
        color: #3c4043;
        font-family: 'Poppins', Helvetica, Arial, sans-serif !important;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: background-color .3s;
    }
    .google-sso-button:hover {
        background-color: #f5f5f5;
    }
    .google-sso-button img {
        width: 18px;
        height: 18px;
        margin-right: 12px;
    }
</style>

<div id="turnstile-container" style="margin-left: 18px; margin-top: 10px"></div> //debjo de este div
<div class="sso-separator">o</div>

<a href="{{ route('sso.google.redirect') }}" class="google-sso-button">
    <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google logo">
    Iniciar sesión con Google
</a>

<script>
    function switchToMFA(userEmail) {
        const fieldsContainer = document.querySelector('.et_pb_newsletter_fields');
        const loginElements = Array.from(fieldsContainer.children);

        const ssoSeparator = document.querySelector('.sso-separator');
        const googleButton = document.querySelector('.google-sso-button');

        loginElements.forEach(el => {
            el.classList.add('fade-out');
        });

        if (ssoSeparator) ssoSeparator.classList.add('fade-out');
        if (googleButton) googleButton.classList.add('fade-out');

        setTimeout(() => {
            fieldsContainer.innerHTML = '';
            document.querySelector('.et_pb_text_inner h2 span').textContent = 'Verificación requerida';

            const infoMessage = document.createElement('p');
            infoMessage.className = 'mfa-info-message fade-in';
            infoMessage.style.cssText = 'color: #00142d; text-align: center; margin-bottom: 20px; font-size: 16px; padding: 10px; background-color: #f8f9fa; border-radius: 5px; border: 1px solid #dee2e6;';
            infoMessage.innerHTML = `Hemos enviado un código de 6 dígitos a: <strong>${userEmail}</strong>`;

            const codeField = document.createElement('p');
            codeField.className = 'et_pb_newsletter_field fade-in';
            codeField.innerHTML = `<input id="codigo_verificacion" class="input" type="text" placeholder="Ingresa el código" name="codigo_verificacion" maxlength="6" style="text-align: center; font-size: 18px; letter-spacing: 3px; width: 100%;" required />`;

            const verifyButtonWrap = document.createElement('p');
            verifyButtonWrap.className = 'et_pb_newsletter_button_wrap fade-in';
            verifyButtonWrap.innerHTML = `<a class="et_pb_newsletter_button et_pb_button" href="#" id="verify_code"><span class="et_pb_newsletter_button_text">Verificar código</span></a>`;

            const bottomLinksWrap = document.createElement('p');
            bottomLinksWrap.className = 'fade-in';
            bottomLinksWrap.style.cssText = 'text-align: center; margin-top: 20px;';
            bottomLinksWrap.innerHTML = `
                <button type="button" id="resend_code" style="background: none; border: none; color: #6c757d; text-decoration: underline; cursor: pointer; font-size: 14px; padding: 0 10px;">Reenviar código</button>
                <span style="color: #6c757d;">|</span>
                <button type="button" onclick="location.reload()" style="background: none; border: none; color: #6c757d; text-decoration: underline; cursor: pointer; font-size: 14px; padding: 0 10px;">Volver al inicio de sesión</button>
            `;

            fieldsContainer.appendChild(infoMessage);
            fieldsContainer.appendChild(codeField);
            fieldsContainer.appendChild(verifyButtonWrap);
            fieldsContainer.appendChild(bottomLinksWrap);

            document.getElementById('verify_code').onclick = (e) => { e.preventDefault(); verifyMFACode(); };
            document.getElementById('resend_code').onclick = (e) => { e.preventDefault(); resendMFACode(); };

            const codigoInput = document.getElementById('codigo_verificacion');

            codigoInput.focus();

            codigoInput.addEventListener('input', clearMfaMessages);
            addMFAValidation();

        }, 400);

        const turnstileContainer = document.getElementById('turnstile-container');
        if (turnstileContainer) turnstileContainer.style.display = 'none';
    }

    function addMFAValidation() {
        const codigoInput = document.getElementById('codigo_verificacion');
        if (codigoInput) {
            codigoInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length === 6) {
                    setTimeout(() => verifyMFACode(), 300);
                }
            });
        }
    }

    async function handleLoginSubmit(event) {
        event.preventDefault();

        const form = document.getElementById('form_login');
        const formData = new FormData(form);
        const button = document.getElementById('login');
        const buttonText = button.querySelector('.et_pb_newsletter_button_text');

        buttonText.textContent = 'Enviando...';
        button.classList.add('et_pb_button_text_loading');
        buttonText.style.visibility = 'visible'

        try {
            const response = await fetch('{{ route('login') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const data = await response.json();

            if (!response.ok) {
                if (response.status === 422 && data.errors) {
                    const errorMsg = Object.values(data.errors).flat().join(' ');
                    showLoginError(errorMsg || 'Error de validación.');
                } else {
                    showLoginError(data.message || 'Ha ocurrido un error.');
                }
            } else {
                if (data.mfa_required) {
                    switchToMFA(data.email);
                }
            }
        } catch (error) {
            showLoginError('Error de conexión. Inténtalo de nuevo.');
            console.error('Error en la petición de login:', error);
        } finally {
            button.classList.remove('et_pb_button_text_loading');
            buttonText.textContent = 'Iniciar sesión';
            buttonText.style.visibility = '';
        }
    }

    async function resendMFACode() {
        const button = document.getElementById('resend_code');
        const originalText = button.textContent;
        button.textContent = 'Enviando...';
        button.style.pointerEvents = 'none';

        try {
            const response = await fetch('{{ route('login.reenviar-codigo') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                }
            });

            const data = await response.json();

            if (!response.ok) {
                showMFAMessage(data.message || 'Error al reenviar el código.', true);
            } else {
                showMFAMessage(data.message);
                document.getElementById('codigo_verificacion').value = '';
                document.getElementById('codigo_verificacion').focus();
            }

        } catch (error) {
            showMFAMessage('Error de conexión.', true);
        } finally {
            setTimeout(() => {
                button.textContent = originalText;
                button.style.pointerEvents = 'auto';
            }, 1000);
        }
    }

    async function verifyMFACode() {
        const code = document.getElementById('codigo_verificacion').value;
        const button = document.getElementById('verify_code');
        const buttonText = button.querySelector('.et_pb_newsletter_button_text');

        if (code.length !== 6) {
            showMFAMessage('Por favor, ingresa un código de 6 dígitos.', true);
            return;
        }

        buttonText.textContent = 'Verificando...';
        button.style.pointerEvents = 'none';

        try {
            const response = await fetch('{{ route('login.verificar-codigo') }}', {
                method: 'POST',
                cache: 'no-store',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    codigo_verificacion: code
                })
            });

            const data = await response.json();

            if (!response.ok) {
                showMFAMessage(data.message || 'Ha ocurrido un error inesperado.', true);
                buttonText.textContent = 'Verificar código';
                button.style.pointerEvents = 'auto';
            } else {
                showMFAMessage('¡Éxito! Redirigiendo...');
                window.location.href = data.redirect_url;
            }

        } catch (error) {
            showMFAMessage('Error de conexión. Inténtalo de nuevo.', true);
            buttonText.textContent = 'Verificar código';
            button.style.pointerEvents = 'auto';
        }
    }

    function showMFAMessage(message, isError = false) {
        const existingMsg = document.querySelector('.mfa-status-message');
        if (existingMsg) existingMsg.remove();

        const messageDiv = document.createElement('div');
        messageDiv.className = 'mfa-status-message';
        const color = isError ? '#a33636' : '#28a745';
        messageDiv.style.cssText = `color: ${color}; font-size: 16px; text-align: center; margin-bottom: 15px; transition: opacity 0.3s;`;
        messageDiv.textContent = message;

        const fieldsContainer = document.querySelector('.et_pb_newsletter_fields');
        fieldsContainer.parentNode.insertBefore(messageDiv, fieldsContainer);

        setTimeout(() => {
            messageDiv.style.opacity = '0';
            setTimeout(() => messageDiv.remove(), 300);
        }, 3000);
    }

    function showLoginError(message) {
        let errorDiv = document.querySelector('.login-error-message');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'login-error-message';
            errorDiv.style.cssText = 'color: #a33636; font-size: 17px; text-align: center; margin-bottom: 15px;';
            const newsletterForm = document.querySelector('.et_pb_newsletter_form');
            newsletterForm.parentNode.insertBefore(errorDiv, newsletterForm);
        }
        errorDiv.textContent = message;
    }

    function clearMfaMessages() {
        const messageDiv = document.querySelector('.mfa-status-message');
        if (messageDiv) {
            messageDiv.style.opacity = '0';
            setTimeout(() => messageDiv.remove(), 200);
        }
    }

    function clearLoginErrors() {
        const errorDiv = document.querySelector('.login-error-message');
        if (errorDiv) {
            errorDiv.style.opacity = '0';
            setTimeout(() => errorDiv.remove(), 200);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('form_login');
        if(loginForm) {
            const loginButton = document.getElementById('login');
            if(loginButton) {
                loginButton.onclick = handleLoginSubmit;
            }
            loginForm.addEventListener('submit', (e) => e.preventDefault());
        }
    });
</script>
