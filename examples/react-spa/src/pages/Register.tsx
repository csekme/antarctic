import { useState, type FormEvent } from "react";
import { Link, Navigate } from "react-router-dom";
import { AlertCircle, Loader2, MailCheck } from "lucide-react";
import { ApiError } from "../api/client.ts";
import { useAuth } from "../auth/AuthContext.tsx";
import type { RegisterResponse } from "../api/auth.ts";
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
import {
  Alert,
  AlertDescription,
  AlertTitle,
} from "@/components/ui/alert";

export default function RegisterPage() {
  const { status, signUp } = useAuth();

  const [form, setForm] = useState({
    email: "",
    username: "",
    password: "",
    password_confirm: "",
    firstname: "",
    lastname: "",
  });
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<RegisterResponse | null>(null);

  if (status === "authenticated") {
    return <Navigate to="/profile" replace />;
  }

  function update<K extends keyof typeof form>(key: K, value: string): void {
    setForm((prev) => ({ ...prev, [key]: value }));
  }

  async function onSubmit(event: FormEvent): Promise<void> {
    event.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const response = await signUp({
        email: form.email,
        username: form.username,
        password: form.password,
        password_confirm: form.password_confirm,
        firstname: form.firstname || undefined,
        lastname: form.lastname || undefined,
      });
      setSuccess(response);
    } catch (err) {
      setError(humanise(err));
    } finally {
      setSubmitting(false);
    }
  }

  if (success) {
    return (
      <div className="mx-auto max-w-md">
        <Card>
          <CardHeader className="items-center text-center">
            <div className="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
              <MailCheck className="size-5" />
            </div>
            <CardTitle>Check your inbox</CardTitle>
            <CardDescription>
              We sent a verification email to{" "}
              <span className="font-medium text-foreground">
                {success.user.email}
              </span>
              . Click the link there to activate your account.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {success.verification_link && (
              <Alert variant="warning">
                <AlertCircle />
                <AlertTitle>Dev mode</AlertTitle>
                <AlertDescription>
                  The backend exposed the verification link directly.{" "}
                  <a
                    className="font-medium underline underline-offset-4"
                    href={success.verification_link}
                  >
                    Verify now
                  </a>
                  .
                </AlertDescription>
              </Alert>
            )}
            <Button asChild variant="outline" className="w-full">
              <Link to="/login">Back to sign in</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-xl">
      <Card>
        <CardHeader>
          <CardTitle className="text-2xl">Create account</CardTitle>
          <CardDescription>
            Sign up to access your protected profile and try out 2FA.
          </CardDescription>
        </CardHeader>
        <form onSubmit={onSubmit}>
          <CardContent className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Field
                id="email"
                label="Email"
                type="email"
                autoComplete="email"
                value={form.email}
                onChange={(v) => update("email", v)}
                required
              />
              <Field
                id="username"
                label="Username"
                type="text"
                autoComplete="username"
                value={form.username}
                onChange={(v) => update("username", v)}
                required
                minLength={3}
                maxLength={45}
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field
                id="password"
                label="Password"
                type="password"
                autoComplete="new-password"
                value={form.password}
                onChange={(v) => update("password", v)}
                required
                minLength={8}
              />
              <Field
                id="password_confirm"
                label="Confirm password"
                type="password"
                autoComplete="new-password"
                value={form.password_confirm}
                onChange={(v) => update("password_confirm", v)}
                required
                minLength={8}
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field
                id="firstname"
                label="First name"
                optional
                type="text"
                autoComplete="given-name"
                value={form.firstname}
                onChange={(v) => update("firstname", v)}
                maxLength={45}
              />
              <Field
                id="lastname"
                label="Last name"
                optional
                type="text"
                autoComplete="family-name"
                value={form.lastname}
                onChange={(v) => update("lastname", v)}
                maxLength={45}
              />
            </div>
            {error && (
              <Alert variant="destructive">
                <AlertCircle />
                <AlertTitle>Could not create account</AlertTitle>
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}
          </CardContent>
          <CardFooter className="flex-col gap-3">
            <Button type="submit" disabled={submitting} className="w-full">
              {submitting && <Loader2 className="animate-spin" />}
              {submitting ? "Creating account…" : "Create account"}
            </Button>
            <p className="text-center text-sm text-muted-foreground">
              Already have an account?{" "}
              <Link
                to="/login"
                className="font-medium text-primary underline-offset-4 hover:underline"
              >
                Sign in
              </Link>
            </p>
          </CardFooter>
        </form>
      </Card>
    </div>
  );
}

type FieldProps = {
  id: string;
  label: string;
  optional?: boolean;
  value: string;
  onChange: (value: string) => void;
} & Omit<React.ComponentProps<"input">, "onChange" | "value">;

function Field({ id, label, optional, value, onChange, ...rest }: FieldProps) {
  return (
    <div className="space-y-2">
      <Label htmlFor={id} className="flex items-center gap-1.5">
        {label}
        {optional && (
          <span className="text-xs font-normal text-muted-foreground">
            (optional)
          </span>
        )}
      </Label>
      <Input
        id={id}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        {...rest}
      />
    </div>
  );
}

function humanise(err: unknown): string {
  if (err instanceof ApiError) {
    const code =
      typeof err.body === "object" && err.body !== null && "code" in err.body
        ? String((err.body as { code: unknown }).code)
        : null;
    if (code === "email_already_registered")
      return "That email is already registered.";
    if (code === "username_taken") return "That username is already taken.";
    return err.message;
  }
  return err instanceof Error ? err.message : "Unexpected error";
}
