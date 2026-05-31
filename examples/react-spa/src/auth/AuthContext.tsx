import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { ApiError } from "../api/client.ts";
import {
  isTwoFactorChallenge,
  login as apiLogin,
  logout as apiLogout,
  me,
  register as apiRegister,
  type RegisterPayload,
  type RegisterResponse,
  type User,
} from "../api/auth.ts";

type Status = "loading" | "authenticated" | "anonymous";

export type SignInResult =
  | { status: "ok" }
  | { status: "2fa"; challengeToken: string; methods: string[] };

interface AuthContextValue {
  status: Status;
  user: User | null;
  signIn: (email: string, password: string) => Promise<SignInResult>;
  signUp: (payload: RegisterPayload) => Promise<RegisterResponse>;
  signOut: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<Status>("loading");
  const [user, setUser] = useState<User | null>(null);

  const refreshUser = useCallback(async () => {
    try {
      const u = await me();
      setUser(u);
      setStatus("authenticated");
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) {
        setUser(null);
        setStatus("anonymous");
        return;
      }
      throw err;
    }
  }, []);

  // On mount: try /me. The fetch client will silently exchange the refresh
  // cookie for a fresh access token (single 401 → /refresh → retry), so a
  // hard reload with a valid refresh cookie still lands authenticated.
  useEffect(() => {
    void refreshUser();
  }, [refreshUser]);

  const signIn = useCallback<AuthContextValue["signIn"]>(
    async (email, password) => {
      const response = await apiLogin(email, password);
      if (isTwoFactorChallenge(response)) {
        return {
          status: "2fa",
          challengeToken: response.challenge_token,
          methods: response.methods,
        };
      }
      setUser(response.user);
      setStatus("authenticated");
      return { status: "ok" };
    },
    [],
  );

  const signOut = useCallback(async () => {
    await apiLogout();
    setUser(null);
    setStatus("anonymous");
  }, []);

  // Registration never returns a session — the new user must verify their
  // email first. We hand the raw RegisterResponse back to the caller so the
  // page can surface the dev-mode verification link.
  const signUp = useCallback<AuthContextValue["signUp"]>(async (payload) => {
    return apiRegister(payload);
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({ status, user, signIn, signUp, signOut, refreshUser }),
    [status, user, signIn, signUp, signOut, refreshUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error("useAuth must be used inside an <AuthProvider>");
  }
  return ctx;
}
