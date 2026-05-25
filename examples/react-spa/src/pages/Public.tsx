export default function PublicPage() {
  return (
    <article>
      <h1>Welcome</h1>
      <p>
        This page is rendered without any authentication. The backend serves it
        from <code>/api/v1/*</code> only when the route requires data; static
        public content does not hit the API at all.
      </p>
      <p>
        Click <em>Sign in</em> in the top-right to log in, then visit{" "}
        <em>Profile</em> to see a protected view that fetches{" "}
        <code>/api/v1/auth/me</code>.
      </p>
    </article>
  );
}
