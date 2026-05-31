import { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { CheckCircle2, Loader2, XCircle } from "lucide-react";
import { verifyEmail } from "../api/auth.ts";
import { ApiError } from "../api/client.ts";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

type State =
  | { kind: "idle" }
  | { kind: "verifying" }
  | { kind: "ok" }
  | { kind: "error"; message: string };

export default function VerifyEmailPage() {
  const [params] = useSearchParams();
  const token = params.get("token") ?? "";
  const [state, setState] = useState<State>({ kind: "idle" });

  useEffect(() => {
    if (token === "") {
      setState({ kind: "error", message: "Missing token in URL." });
      return;
    }
    setState({ kind: "verifying" });
    verifyEmail(token).then(
      () => setState({ kind: "ok" }),
      (err: unknown) => setState({ kind: "error", message: humanise(err) }),
    );
  }, [token]);

  return (
    <div className="mx-auto max-w-md">
      <Card>
        {state.kind === "verifying" || state.kind === "idle" ? (
          <CardHeader className="items-center text-center">
            <Loader2 className="size-8 animate-spin text-muted-foreground" />
            <CardTitle>Verifying your email…</CardTitle>
            <CardDescription>This should only take a moment.</CardDescription>
          </CardHeader>
        ) : state.kind === "ok" ? (
          <>
            <CardHeader className="items-center text-center">
              <CheckCircle2 className="size-10 text-emerald-500" />
              <CardTitle>Email verified</CardTitle>
              <CardDescription>
                Your account is active. You can now sign in.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Button asChild className="w-full">
                <Link to="/login">Continue to sign in</Link>
              </Button>
            </CardContent>
          </>
        ) : (
          <>
            <CardHeader className="items-center text-center">
              <XCircle className="size-10 text-destructive" />
              <CardTitle>Verification failed</CardTitle>
              <CardDescription>{state.message}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
              <Button asChild variant="outline" className="w-full">
                <Link to="/register">Register again</Link>
              </Button>
              <Button asChild variant="ghost" className="w-full">
                <Link to="/login">Back to sign in</Link>
              </Button>
            </CardContent>
          </>
        )}
      </Card>
    </div>
  );
}

function humanise(err: unknown): string {
  if (err instanceof ApiError) {
    const code =
      typeof err.body === "object" && err.body !== null && "code" in err.body
        ? String((err.body as { code: unknown }).code)
        : null;
    if (code === "token_unknown_or_expired") {
      return "The verification link is invalid or has already been used.";
    }
    return err.message;
  }
  return err instanceof Error ? err.message : "Unexpected error";
}
