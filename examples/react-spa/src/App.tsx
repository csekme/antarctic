import { Navigate, Route, Routes } from "react-router-dom";
import Layout from "./components/Layout.tsx";
import { ProtectedRoute } from "./auth/ProtectedRoute.tsx";
import LoginPage from "./pages/Login.tsx";
import PublicPage from "./pages/Public.tsx";
import ProfilePage from "./pages/Profile.tsx";
import RegisterPage from "./pages/Register.tsx";
import VerifyEmailPage from "./pages/VerifyEmail.tsx";

export default function App() {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route index element={<PublicPage />} />
        <Route path="login" element={<LoginPage />} />
        <Route path="register" element={<RegisterPage />} />
        <Route path="verify-email" element={<VerifyEmailPage />} />
        <Route
          path="profile"
          element={
            <ProtectedRoute>
              <ProfilePage />
            </ProtectedRoute>
          }
        />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  );
}
