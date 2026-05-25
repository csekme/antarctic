import type { ReactNode } from "react";
import { Navigate, useLocation } from "react-router-dom";
import { useAuth } from "./AuthContext.tsx";

/**
 * Gate around a subtree that requires an authenticated user. While the
 * initial `/me` round-trip is in flight we render a placeholder rather
 * than redirect — a forced redirect to /login here would race the
 * refresh-cookie-based session rehydration and force the user to log in
 * even though their refresh cookie is valid.
 */
export function ProtectedRoute({ children }: { children: ReactNode }) {
  const { status } = useAuth();
  const location = useLocation();

  if (status === "loading") {
    return <p>Checking session…</p>;
  }
  if (status === "anonymous") {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }
  return <>{children}</>;
}
