import { api, setSession } from "./client.ts";

export interface User {
  id: number;
  email: string | null;
  username: string | null;
  roles: string[];
  /** Optional: present on /me payloads, absent on the SessionPayload.user echo. */
  two_factor?: { methods: string[] };
}

interface SessionPayload {
  access_token: string;
  csrf_token: string;
  expires_in: number;
  user: User;
}

interface TwoFactorChallenge {
  requires: "2fa";
  challenge_token: string;
  methods: string[];
  expires_in: number;
}

type LoginResponse = SessionPayload | TwoFactorChallenge;

export function isTwoFactorChallenge(r: LoginResponse): r is TwoFactorChallenge {
  return "requires" in r && r.requires === "2fa";
}

export async function login(email: string, password: string): Promise<LoginResponse> {
  const response = await api<LoginResponse>("/api/v1/auth/login", {
    method: "POST",
    json: { email, password },
  });
  if (!isTwoFactorChallenge(response)) {
    setSession(response.access_token, response.csrf_token);
  }
  return response;
}

export async function verifyTwoFactor(
  challengeToken: string,
  code: string,
): Promise<SessionPayload> {
  const response = await api<SessionPayload>("/api/v1/auth/2fa/verify", {
    method: "POST",
    json: { challenge_token: challengeToken, code },
  });
  setSession(response.access_token, response.csrf_token);
  return response;
}

export async function logout(): Promise<void> {
  try {
    await api<{ ok: true }>("/api/v1/auth/logout", { method: "POST" });
  } finally {
    setSession(null, null);
  }
}

export async function me(): Promise<User> {
  return api<User>("/api/v1/auth/me", { method: "GET" });
}

export interface RegisterPayload {
  email: string;
  username: string;
  password: string;
  password_confirm: string;
  firstname?: string;
  lastname?: string;
}

export interface RegisterResponse {
  user: { id: number; email: string; username: string };
  requires_verification: true;
  /** Dev-only: only set when APP_EXPOSE_VERIFICATION_LINK=1 on the backend. */
  verification_link?: string;
}

export async function register(payload: RegisterPayload): Promise<RegisterResponse> {
  return api<RegisterResponse>("/api/v1/auth/register", {
    method: "POST",
    json: payload,
  });
}

export async function verifyEmail(token: string): Promise<{ verified: true }> {
  return api<{ verified: true }>("/api/v1/auth/verify-email", {
    method: "POST",
    json: { token },
  });
}

export interface TotpEnrollment {
  secret: string;
  otpauth_uri: string;
  qr_data_uri: string;
}

export async function enrollTotp(): Promise<TotpEnrollment> {
  return api<TotpEnrollment>("/api/v1/auth/2fa/enroll", { method: "POST" });
}

export async function confirmTotp(code: string): Promise<{ enabled: true; method: "app" }> {
  return api<{ enabled: true; method: "app" }>("/api/v1/auth/2fa/enroll/confirm", {
    method: "POST",
    json: { code },
  });
}

export async function disableTotp(password: string): Promise<{ enabled: false }> {
  return api<{ enabled: false }>("/api/v1/auth/2fa/disable", {
    method: "POST",
    json: { password },
  });
}
