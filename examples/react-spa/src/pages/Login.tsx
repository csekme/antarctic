import { useState, type FormEvent } from "react";
import { Link, Navigate, useLocation, useNavigate } from "react-router-dom";
import { AlertCircle, Loader2, ShieldCheck } from "lucide-react";
import { verifyTwoFactor } from "../api/auth.ts";
import { ApiError } from "../api/client.ts";
import { useAuth } from "../auth/AuthContext.tsx";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";

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
      <div className="mx-auto max-w-md">
        <Card>
          <CardHeader className="items-center text-center">
            <div className="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
              <ShieldCheck className="size-5" />
            </div>
            <CardTitle>Two-factor verification</CardTitle>
            <CardDescription>
              Enter the 6-digit code from your authenticator app.
            </CardDescription>
          </CardHeader>
          <form onSubmit={onSubmit2fa}>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="totp">Verification code</Label>
                <Input
                  id="totp"
                  type="text"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  pattern="[0-9]{6}"
                  maxLength={6}
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  required
                  autoFocus
                  className="text-center text-lg tracking-[0.5em]"
                />
              </div>
              {error && <ErrorAlert message={error} />}
            </CardContent>
            <CardFooter>
              <Button type="submit" disabled={submitting} className="w-full">
                {submitting && <Loader2 className="animate-spin" />}
                {submitting ? "Verifying…" : "Verify"}
              </Button>
            </CardFooter>
          </form>
        </Card>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-md">
      <Card>
        <CardHeader>
          <CardTitle className="text-2xl">Sign in</CardTitle>
          <CardDescription>
            Welcome back. Use your email and password to continue.
          </CardDescription>
        </CardHeader>
        <form onSubmit={onSubmitCredentials}>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                placeholder="you@example.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">Password</Label>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </div>
            {error && <ErrorAlert message={error} />}
          </CardContent>
          <CardFooter className="flex-col gap-3">
            <Button type="submit" disabled={submitting} className="w-full">
              {submitting && <Loader2 className="animate-spin" />}
              {submitting ? "Signing in…" : "Sign in"}
            </Button>
            <p className="text-center text-sm text-muted-foreground">
              Don't have an account yet?{" "}
              <Link
                to="/register"
                className="font-medium text-primary underline-offset-4 hover:underline"
              >
                Create one
              </Link>
            </p>
          </CardFooter>
        </form>
      </Card>
    </div>
  );
}

function ErrorAlert({ message }: { message: string }) {
  return (
    <Alert variant="destructive">
      <AlertCircle />
      <AlertTitle>Sign-in failed</AlertTitle>
      <AlertDescription>{message}</AlertDescription>
    </Alert>
  );
}

function humanise(err: unknown): string {
  if (err instanceof ApiError) {
    const code =
      typeof err.body === "object" && err.body !== null && "code" in err.body
        ? String((err.body as { code: unknown }).code)
        : null;
    if (code === "email_not_verified") {
      return "Your email is not verified yet. Check your inbox for the verification link before signing in.";
    }
    return err.message;
  }
  return err instanceof Error ? err.message : "Unexpected error";
}
