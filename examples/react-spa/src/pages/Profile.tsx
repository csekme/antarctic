import { useState, type FormEvent } from "react";
import {
  AlertCircle,
  Loader2,
  ShieldAlert,
  ShieldCheck,
  ShieldOff,
} from "lucide-react";
import { ApiError } from "../api/client.ts";
import { useAuth } from "../auth/AuthContext.tsx";
import {
  confirmTotp,
  disableTotp,
  enrollTotp,
  type TotpEnrollment,
} from "../api/auth.ts";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import {
  Alert,
  AlertDescription,
  AlertTitle,
} from "@/components/ui/alert";

export default function ProfilePage() {
  const { user, refreshUser } = useAuth();

  if (!user) {
    return <p className="text-muted-foreground">No user loaded.</p>;
  }

  const twoFactorEnabled = (user.two_factor?.methods ?? []).includes("app");

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-3">
            Profile
            {twoFactorEnabled ? (
              <Badge variant="success">
                <ShieldCheck className="mr-1 size-3" /> 2FA on
              </Badge>
            ) : (
              <Badge variant="secondary">
                <ShieldOff className="mr-1 size-3" /> 2FA off
              </Badge>
            )}
          </CardTitle>
          <CardDescription>
            Account information loaded from <code>/api/v1/auth/me</code>.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <dl className="grid grid-cols-1 gap-y-3 text-sm sm:grid-cols-[max-content_1fr] sm:gap-x-6">
            <Row label="ID" value={user.id} />
            <Row label="Email" value={user.email ?? "—"} />
            <Row label="Username" value={user.username ?? "—"} />
            <Row
              label="Roles"
              value={
                user.roles.length === 0 ? (
                  "—"
                ) : (
                  <div className="flex flex-wrap gap-1">
                    {user.roles.map((role) => (
                      <Badge key={role} variant="outline">
                        {role}
                      </Badge>
                    ))}
                  </div>
                )
              }
            />
            <Row
              label="2FA"
              value={
                twoFactorEnabled
                  ? "Enabled (authenticator app)"
                  : "Not enabled"
              }
            />
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Two-factor authentication</CardTitle>
          <CardDescription>
            Add a TOTP app as a second factor on every sign-in.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {twoFactorEnabled ? (
            <DisablePanel onDone={refreshUser} />
          ) : (
            <EnrollPanel onDone={refreshUser} />
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <>
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="font-medium">{value}</dd>
    </>
  );
}

function EnrollPanel({ onDone }: { onDone: () => Promise<void> }) {
  const [enrollment, setEnrollment] = useState<TotpEnrollment | null>(null);
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function onStart(): Promise<void> {
    setError(null);
    setSubmitting(true);
    try {
      const data = await enrollTotp();
      setEnrollment(data);
    } catch (err) {
      setError(humanise(err));
    } finally {
      setSubmitting(false);
    }
  }

  async function onConfirm(event: FormEvent): Promise<void> {
    event.preventDefault();
    if (!enrollment) return;
    setError(null);
    setSubmitting(true);
    try {
      await confirmTotp(code);
      await onDone();
    } catch (err) {
      setError(humanise(err));
    } finally {
      setSubmitting(false);
    }
  }

  if (!enrollment) {
    return (
      <div className="flex flex-col gap-4">
        <p className="text-sm text-muted-foreground">
          Use Google Authenticator, 1Password, Authy or any other TOTP app.
          You'll need this code on every sign-in.
        </p>
        <Button
          type="button"
          onClick={() => void onStart()}
          disabled={submitting}
          className="self-start"
        >
          {submitting ? (
            <Loader2 className="animate-spin" />
          ) : (
            <ShieldCheck />
          )}
          {submitting ? "Starting…" : "Enable 2FA"}
        </Button>
        {error && (
          <Alert variant="destructive">
            <AlertCircle />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}
      </div>
    );
  }

  return (
    <form onSubmit={onConfirm} className="flex flex-col gap-5">
      <div className="flex flex-col items-start gap-5 sm:flex-row">
        <img
          src={enrollment.qr_data_uri}
          alt="2FA QR code"
          className="size-44 shrink-0 rounded-md border bg-white p-2"
        />
        <div className="flex flex-col gap-2 text-sm">
          <p className="text-muted-foreground">
            Scan the QR code, then enter the 6-digit code from your app.
          </p>
          <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">
              Or paste this secret manually
            </p>
            <code className="mt-1 inline-block break-all rounded bg-muted px-2 py-1 font-mono text-xs">
              {enrollment.secret}
            </code>
          </div>
        </div>
      </div>

      <Separator />

      <div className="space-y-2">
        <Label htmlFor="confirm-code">Verification code</Label>
        <Input
          id="confirm-code"
          type="text"
          inputMode="numeric"
          autoComplete="one-time-code"
          pattern="[0-9]{6}"
          maxLength={6}
          value={code}
          onChange={(e) => setCode(e.target.value)}
          required
          className="max-w-[12rem] text-center text-lg tracking-[0.5em]"
        />
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertCircle />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <Button type="submit" disabled={submitting} className="self-start">
        {submitting && <Loader2 className="animate-spin" />}
        {submitting ? "Confirming…" : "Confirm and enable"}
      </Button>
    </form>
  );
}

function DisablePanel({ onDone }: { onDone: () => Promise<void> }) {
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(event: FormEvent): Promise<void> {
    event.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await disableTotp(password);
      setPassword("");
      await onDone();
    } catch (err) {
      setError(humanise(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4">
      <Alert variant="warning">
        <ShieldAlert />
        <AlertTitle>Disabling 2FA</AlertTitle>
        <AlertDescription>
          Your account will be secured by password only. Re-enter your password
          to confirm.
        </AlertDescription>
      </Alert>
      <div className="space-y-2">
        <Label htmlFor="disable-password">Password</Label>
        <Input
          id="disable-password"
          type="password"
          autoComplete="current-password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          required
        />
      </div>
      {error && (
        <Alert variant="destructive">
          <AlertCircle />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}
      <Button
        type="submit"
        variant="destructive"
        disabled={submitting}
        className="self-start"
      >
        {submitting && <Loader2 className="animate-spin" />}
        {submitting ? "Disabling…" : "Disable 2FA"}
      </Button>
    </form>
  );
}

function humanise(err: unknown): string {
  if (err instanceof ApiError) {
    const code =
      typeof err.body === "object" && err.body !== null && "code" in err.body
        ? String((err.body as { code: unknown }).code)
        : null;
    if (code === "invalid_code")
      return "That code is not valid — try the next rotation.";
    if (code === "password_required") return "Password does not match.";
    if (code === "2fa_already_enabled")
      return "2FA is already active on this account.";
    return err.message;
  }
  return err instanceof Error ? err.message : "Unexpected error";
}
