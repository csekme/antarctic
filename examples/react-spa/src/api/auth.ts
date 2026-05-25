import { api, setSession } from "./client.ts";

export interface User {
  id: number;
  email: string | null;
  username: string | null;
  roles: string[];
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
