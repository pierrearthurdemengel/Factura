import { createContext, useContext, useState, useCallback, useEffect, type ReactNode } from 'react';
import {
  login as apiLogin,
  logout as apiLogout,
  getMe,
  type UserProfile,
} from '../api/factura';

interface AuthContextType {
  isAuthenticated: boolean;
  token: string | null;
  user: UserProfile | null;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | null>(null);

// Fournisseur du contexte d'authentification.
// Le JWT est stocke en memoire (sessionStorage), le refresh token est en cookie httpOnly.
export function AuthProvider({ children }: Readonly<{ children: ReactNode }>) {
  const [token, setToken] = useState<string | null>(
    sessionStorage.getItem('jwt_token'),
  );
  const [user, setUser] = useState<UserProfile | null>(null);

  // Charger le profil utilisateur a l'initialisation si un token existe
  useEffect(() => {
    if (token && !user) {
      getMe()
        .then((res) => setUser(res.data))
        .catch(() => {
          // Token invalide ou expire — le refresh automatique dans l'intercepteur
          // va tenter un renouvellement. Si ca echoue, l'utilisateur sera redirige.
        });
    }
  }, [token, user]);

  const login = useCallback(async (email: string, password: string) => {
    const jwt = await apiLogin(email, password);
    setToken(jwt);

    // Charger le profil utilisateur apres connexion
    const res = await getMe();
    setUser(res.data);
  }, []);

  const logout = useCallback(async () => {
    await apiLogout();
    setToken(null);
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider
      value={{
        isAuthenticated: token !== null,
        token,
        user,
        login,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

// eslint-disable-next-line react-refresh/only-export-components -- hook partage avec le provider
export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth doit etre utilise dans un AuthProvider');
  }
  return context;
}
