import { Link, NavLink, Outlet } from "react-router-dom";
import { LogOut, Snowflake } from "lucide-react";
import { useAuth } from "../auth/AuthContext.tsx";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

export default function Layout() {
  const { status, user, signOut } = useAuth();

  return (
    <div className="min-h-screen bg-background text-foreground">
      <header className="sticky top-0 z-30 border-b bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
        <div className="container flex h-14 items-center gap-6">
          <Link to="/" className="flex items-center gap-2 font-semibold">
            <Snowflake className="size-5 text-primary" aria-hidden />
            <span>Antarctic SPA</span>
          </Link>

          <nav className="flex items-center gap-1 text-sm">
            <NavItem to="/" end>
              Public
            </NavItem>
            <NavItem to="/profile">Profile</NavItem>
          </nav>

          <div className="ml-auto flex items-center gap-3">
            {status === "authenticated" && user ? (
              <>
                <Badge variant="secondary" className="hidden sm:inline-flex">
                  {user.email ?? user.username ?? `user #${user.id}`}
                </Badge>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => void signOut()}
                >
                  <LogOut />
                  Sign out
                </Button>
              </>
            ) : status === "anonymous" ? (
              <>
                <Button asChild variant="ghost" size="sm">
                  <Link to="/register">Create account</Link>
                </Button>
                <Button asChild size="sm">
                  <Link to="/login">Sign in</Link>
                </Button>
              </>
            ) : (
              <span className="text-sm text-muted-foreground">…</span>
            )}
          </div>
        </div>
      </header>

      <main className="container py-10">
        <Outlet />
      </main>

      <footer className="container py-8 text-xs text-muted-foreground">
        Antarctic — JWT + refresh cookie demo · React SPA
      </footer>
    </div>
  );
}

function NavItem({
  to,
  end,
  children,
}: {
  to: string;
  end?: boolean;
  children: React.ReactNode;
}) {
  return (
    <NavLink
      to={to}
      end={end}
      className={({ isActive }) =>
        cn(
          "rounded-md px-3 py-1.5 transition-colors hover:bg-accent hover:text-accent-foreground",
          isActive
            ? "bg-accent text-accent-foreground"
            : "text-muted-foreground",
        )
      }
    >
      {children}
    </NavLink>
  );
}
