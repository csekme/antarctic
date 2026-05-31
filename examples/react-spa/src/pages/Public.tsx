import { Link } from "react-router-dom";
import { ArrowRight, KeyRound, ShieldCheck, UserPlus } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

export default function PublicPage() {
  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-10">
      <section className="flex flex-col gap-4 text-center">
        <h1 className="text-4xl font-semibold tracking-tight sm:text-5xl">
          A reference SPA for the{" "}
          <span className="text-primary">Antarctic</span> backend
        </h1>
        <p className="mx-auto max-w-2xl text-balance text-muted-foreground">
          This page renders without authentication. Sign in to fetch{" "}
          <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
            /api/v1/auth/me
          </code>{" "}
          and exercise the JWT + refresh-cookie flow end to end.
        </p>
        <div className="flex flex-wrap justify-center gap-2">
          <Button asChild size="lg">
            <Link to="/login">
              Sign in <ArrowRight />
            </Link>
          </Button>
          <Button asChild variant="outline" size="lg">
            <Link to="/register">Create an account</Link>
          </Button>
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-3">
        <FeatureCard
          icon={<KeyRound className="size-5" />}
          title="JWT + refresh cookie"
          body="Short-lived access tokens kept in memory, refresh stored as __Host- cookie."
        />
        <FeatureCard
          icon={<ShieldCheck className="size-5" />}
          title="2FA ready"
          body="Optional TOTP enrollment with QR code, challenge token on sign-in."
        />
        <FeatureCard
          icon={<UserPlus className="size-5" />}
          title="Email verification"
          body="Signup sends a verification link; unverified accounts cannot sign in."
        />
      </section>
    </div>
  );
}

function FeatureCard({
  icon,
  title,
  body,
}: {
  icon: React.ReactNode;
  title: string;
  body: string;
}) {
  return (
    <Card>
      <CardHeader>
        <div className="flex size-9 items-center justify-center rounded-md bg-primary/10 text-primary">
          {icon}
        </div>
        <CardTitle className="text-base">{title}</CardTitle>
        <CardDescription>{body}</CardDescription>
      </CardHeader>
      <CardContent />
    </Card>
  );
}
