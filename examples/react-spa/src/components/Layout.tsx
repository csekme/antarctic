import { Link, Outlet } from "react-router-dom";
import { useAuth } from "../auth/AuthContext.tsx";

export default function Layout() {
  const { status, user, signOut } = useAuth();

  return (
    <div style={{ fontFamily: "system-ui, sans-serif", maxWidth: 720, margin: "2rem auto" }}>
      <header style={{ display: "flex", gap: 16, alignItems: "center", borderBottom: "1px solid #ddd", paddingBottom: 12 }}>
        <strong>Antarctic SPA</strong>
        <nav style={{ display: "flex", gap: 12 }}>
          <Link to="/">Public</Link>
          <Link to="/profile">Profile</Link>
        </nav>
        <div style={{ marginLeft: "auto", display: "flex", gap: 12, alignItems: "center" }}>
          {status === "authenticated" && user ? (
            <>
              <span>{user.email ?? user.username ?? `user #${user.id}`}</span>
              <button type="button" onClick={() => void signOut()}>
                Sign out
              </button>
            </>
          ) : status === "anonymous" ? (
            <Link to="/login">Sign in</Link>
          ) : (
            <span>…</span>
          )}
        </div>
      </header>
      <main style={{ padding: "1.5rem 0" }}>
        <Outlet />
      </main>
    </div>
  );
}
