/**
 * Passkeys (WebAuthn) with the browser's built-in API, talking to Fortify's passkey routes.
 * Options and credentials travel as JSON with base64url-encoded binary fields.
 */

export class PasswordConfirmationRequired extends Error {}

export function passkeysSupported() {
    return typeof window !== 'undefined' && Boolean(window.PublicKeyCredential && navigator.credentials);
}

const toBytes = (value) => {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(value.length / 4) * 4, '=');

    return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
};

const toBase64Url = (buffer) =>
    btoa(String.fromCharCode(...new Uint8Array(buffer)))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');

function creationOptions(options) {
    if (PublicKeyCredential.parseCreationOptionsFromJSON) return PublicKeyCredential.parseCreationOptionsFromJSON(options);

    return {
        ...options,
        challenge: toBytes(options.challenge),
        user: { ...options.user, id: toBytes(options.user.id) },
        excludeCredentials: (options.excludeCredentials ?? []).map((credential) => ({ ...credential, id: toBytes(credential.id) })),
    };
}

function requestOptions(options) {
    if (PublicKeyCredential.parseRequestOptionsFromJSON) return PublicKeyCredential.parseRequestOptionsFromJSON(options);

    return {
        ...options,
        challenge: toBytes(options.challenge),
        allowCredentials: (options.allowCredentials ?? []).map((credential) => ({ ...credential, id: toBytes(credential.id) })),
    };
}

function credentialJson(credential) {
    if (credential.toJSON) return credential.toJSON();

    const { response } = credential;
    const encoded = {};

    ['clientDataJSON', 'attestationObject', 'authenticatorData', 'signature', 'userHandle'].forEach((key) => {
        if (response[key]) encoded[key] = toBase64Url(response[key]);
    });

    if (response.getTransports) encoded.transports = response.getTransports();

    return {
        id: credential.id,
        rawId: toBase64Url(credential.rawId),
        type: credential.type,
        response: encoded,
        clientExtensionResults: credential.getClientExtensionResults?.() ?? {},
        authenticatorAttachment: credential.authenticatorAttachment ?? undefined,
    };
}

async function guarded(request) {
    try {
        return await request();
    } catch (error) {
        if (error.response?.status === 423) throw new PasswordConfirmationRequired();
        throw error;
    }
}

/** Confirms the password so passkeys can be managed for the next few hours. */
export function confirmPassword(password) {
    return window.axios.post(route('password.confirm.store'), { password });
}

/** Registers a passkey on this device for the signed-in user. */
export async function registerPasskey(name) {
    const { data } = await guarded(() => window.axios.get(route('passkey.registration-options')));
    const credential = await navigator.credentials.create({ publicKey: creationOptions(data.options) });

    await guarded(() => window.axios.post(route('passkey.store'), { name, credential: credentialJson(credential) }));
}

export function deletePasskey(id) {
    return guarded(() => window.axios.delete(route('passkey.destroy', id)));
}

/** Signs in with a passkey and returns where to go next. */
export async function signInWithPasskey() {
    const { data } = await window.axios.get(route('passkey.login-options'));
    const credential = await navigator.credentials.get({ publicKey: requestOptions(data.options) });
    const response = await window.axios.post(route('passkey.login'), { credential: credentialJson(credential) });

    return response.data?.redirect ?? route('dashboard');
}

/** A readable message for a failed passkey ceremony. */
export function passkeyError(error) {
    if (error?.name === 'NotAllowedError') return 'The passkey prompt was closed or timed out. Try again when you are ready.';
    if (error?.name === 'InvalidStateError') return 'This device already has a passkey for your account.';
    if (error?.name === 'SecurityError') return 'Passkeys only work on the address set in APP_URL (for example http://localhost:8000).';

    return error?.response?.data?.message ?? 'Something went wrong with the passkey. Please try again.';
}
