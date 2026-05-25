/**
 * Tiny fetch wrapper that:
 *
 *   - prefixes the configured API base (empty = same-origin, behind proxy)
 *   - attaches the in-memory access token as `Authorization: Bearer`
 *   - sends `credentials: include` so the `__Host-refresh` and `csrf_token`
 *     cookies travel both ways
 *   - automatically refreshes on a single 401 and retries the original call
 *
 * Storing the access token only in memory (not localStorage) is the trade
 * the JWT + refresh-cookie design pays for: a page reload silently drops it,
 * which is fine — the refresh cookie is enough to mint a new one.
 */

const API_BASE = (import.meta.env.VITE_API_BASE ?? "").replace(/\/$/, "");

let accessToken: string | null = null;
let csrfToken: string | null = null;
let refreshing: Promise<boolean> | null = null;

export function setSession(token: string | null, csrf: string | null): void {
  accessToken = token;
  csrfToken = csrf;
}

export function getAccessToken(): string | null {
  return accessToken;
}

/**
 * Read the CSRF cookie the backend sets on login/refresh. Falls back to the
 * value captured on the last successful auth call — useful right after a
 * cross-origin login where the cookie is opaque to JS until the next
 * round-trip. The double-submit value sent on /refresh must come from one
 * of these two sources; the cookie is the source of truth.
 */
function readCsrfCookie(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]+)/);
  if (match && match[1]) {
    return decodeURIComponent(match[1]);
  }
  return csrfToken;
}

export interface ApiOptions extends Omit<RequestInit, "body"> {
  json?: unknown;
}

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly body: unknown,
  ) {
    super(typeof body === "object" && body !== null && "detail" in body
      ? String((body as { detail: unknown }).detail)
      : `HTTP ${status}`);
    this.name = "ApiError";
  }
}

export async function api<T>(path: string, opts: ApiOptions = {}): Promise<T> {
  const response = await sendRequest(path, opts);
  if (response.status !== 401) {
    return parseOrThrow<T>(response);
  }

  // Single coalesced refresh attempt — N concurrent 401s only fire one POST.
  const ok = await refreshAccessToken();
  if (!ok) {
    throw await asApiError(response);
  }
  const retry = await sendRequest(path, opts);
  return parseOrThrow<T>(retry);
}

async function sendRequest(path: string, opts: ApiOptions): Promise<Response> {
  const headers = new Headers(opts.headers);
  headers.set("Accept", "application/json");
  if (opts.json !== undefined) {
    headers.set("Content-Type", "application/json");
  }
  if (accessToken) {
    headers.set("Authorization", `Bearer ${accessToken}`);
  }

  return fetch(`${API_BASE}${path}`, {
    ...opts,
    headers,
    credentials: "include",
    body: opts.json !== undefined ? JSON.stringify(opts.json) : undefined,
  });
}

async function parseOrThrow<T>(response: Response): Promise<T> {
  if (!response.ok) {
    throw await asApiError(response);
  }
  if (response.status === 204) {
    return undefined as T;
  }
  return (await response.json()) as T;
}

async function asApiError(response: Response): Promise<ApiError> {
  let body: unknown = null;
  try {
    body = await response.json();
  } catch {
    /* problem+json may be empty */
  }
  return new ApiError(response.status, body);
}

async function refreshAccessToken(): Promise<boolean> {
  if (!refreshing) {
    refreshing = (async () => {
      try {
        const csrf = readCsrfCookie();
        if (!csrf) {
          return false;
        }
        const response = await fetch(`${API_BASE}/api/v1/auth/refresh`, {
          method: "POST",
          credentials: "include",
          headers: {
            Accept: "application/json",
            "X-CSRF-Token": csrf,
          },
        });
        if (!response.ok) {
          accessToken = null;
          return false;
        }
        const body = (await response.json()) as {
          access_token: string;
          csrf_token: string;
        };
        accessToken = body.access_token;
        csrfToken = body.csrf_token;
        return true;
      } finally {
        refreshing = null;
      }
    })();
  }
  return refreshing;
}
