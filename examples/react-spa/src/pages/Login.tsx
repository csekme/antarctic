import { useState, type FormEvent } from "react";
import { Navigate, useLocation, useNavigate } from "react-router-dom";
import { verifyTwoFactor } from "../api/auth.ts";
import { ApiError } from "../api/client.ts";
import { useAuth } from "../auth/AuthContext.tsx";

type LocationState = { from?: string };

export default function LoginPage() {
  const { status, signIn, refreshUser } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const target = (location.state as LocationState | null)?.from ?? "/profile";

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [challenge, setChallenge] = useState<string | null>(null);
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  if (status === "authenticated") {
    return <Navigate to={target} replace />;
  }

  async function onSubmitCredentials(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const result = await signIn(email, password);
      if (result.status === "2fa") {
        setChallenge(result.challengeToken);
      } else {
        navigate(target, { replace: true });
      }
    } catch (err) {
      setError(humanise(err));
    } finally {
      setSubmitting(false);
    }
  }

  async function onSubmit2fa(event: FormEvent) {
    event.preventDefault();
    if (!challenge) return;
    setError(null);
    setSubmitting(true);
    try {
      await verifyTwoFactor(challenge, code);
      await refreshUser();
      navigate(target, { replace: true });
    } catch (err) {
      setError(humanise(err));
    } finally {
      setSubmitting(false);
    }
  }

  if (challenge !== null) {
    return (
      <form onSubmit={onSubmit2fa}>
        <h1>Two-factor verification</h1>
        <p>Enter the 6-digit code from your authenticator app.</p>
        <label>
          Code
          <input
            type="text"
            inputMode="numeric"
            autoComplete="one-time-code"
            value={code}
            onChange={(e) => setCode(e.target.value)}
            required
          />
        </label>
        {error && <p style={{ color: "crimson" }}>{error}</p>}
        <button type="submit" disabled={submitting}>
          {submitting ? "Verifying…" : "Verify"}
        </button>
      </form>
    );
  }

  return (
    <form onSubmit={onSubmitCredentials}>
      <h1>Sign in</h1>
      <label>
        Email
        <input
          type="email"
          autoComplete="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          required
        />
      </label>
      <label>
        Password
        <input
          type="password"
          autoComplete="current-password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          required
        />
      </label>
      {error && <p style={{ color: "crimson" }}>{error}</p>}
      <button type="submit" disabled={submitting}>
        {submitting ? "Signing in…" : "Sign in"}
      </button>
    </form>
  );
}

function humanise(err: unknown): string {
  if (err instanceof ApiError) {
    return err.message;
  }
  return err instanceof Error ? err.message : "Unexpected error";
}
