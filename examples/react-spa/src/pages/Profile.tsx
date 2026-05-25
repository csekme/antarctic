import { useAuth } from "../auth/AuthContext.tsx";

export default function ProfilePage() {
  const { user } = useAuth();

  if (!user) {
    return <p>No user loaded.</p>;
  }

  return (
    <article>
      <h1>Profile</h1>
      <dl style={{ display: "grid", gridTemplateColumns: "max-content 1fr", gap: "0.5rem 1rem" }}>
        <dt>ID</dt>
        <dd>{user.id}</dd>
        <dt>Email</dt>
        <dd>{user.email ?? "—"}</dd>
        <dt>Username</dt>
        <dd>{user.username ?? "—"}</dd>
        <dt>Roles</dt>
        <dd>{user.roles.length === 0 ? "—" : user.roles.join(", ")}</dd>
      </dl>
      <p>
        This view is rendered behind a <code>&lt;ProtectedRoute&gt;</code> guard,
        and the data comes from <code>GET /api/v1/auth/me</code>.
      </p>
    </article>
  );
}
