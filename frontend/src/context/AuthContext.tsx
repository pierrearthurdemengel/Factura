import { createContext, useContext, useState, useCallback, useEffect, useMemo, type ReactNode } from 'react';
import {
  login as apiLogin,
  logout as apiLogout,
  getMe,
  type UserProfile,
} from '../api/factura';

interface AuthContextType {
  isAuthenticated: boolean;
  isAuthReady: boolean;
  token: string | null;
  user: UserProfile | null;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | null>(null);

// Cache du profil en sessionStorage pour eviter le getMe bloquant au reload
const USER_CACHE_KEY = 'factura_user_cache';

function getCachedUser(): UserProfile | null {
  try {
    const cached = sessionStorage.getItem(USER_CACHE_KEY);
    return cached ? JSON.parse(cached) : null;
  } catch {
    return null;
  }
}

function setCachedUser(user: UserProfile | null) {
  if (user) {
    sessionStorage.setItem(USER_CACHE_KEY, JSON.stringify(user));
  } else {
    sessionStorage.removeItem(USER_CACHE_KEY);
  }
}

// Fournisseur du contexte d'authentification.
// Le JWT est stocke en memoire (sessionStorage), le refresh token est en cookie httpOnly.
export function AuthProvider({ children }: Readonly<{ children: ReactNode }>) {
  const [token, setToken] = useState<string | null>(
    sessionStorage.getItem('jwt_token'),
  );
  const [user, setUser] = useState<UserProfile | null>(getCachedUser);
  const [isAuthReady, setIsAuthReady] = useState(() => {
    // Si pas de token, on est pret immediatement (pas d'auth)
    // Si token + cache, pret immediatement (on revalidera en background)
    return !sessionStorage.getItem('jwt_token') || getCachedUser() !== null;
  });

  // Revalider le profil en background si un token existe
  useEffect(() => {
    if (!token) return;
    getMe()
      .then((res) => {
        setUser(res.data);
        setCachedUser(res.data);
      })
      .catch(() => {
        // Token invalide — le refresh intercepteur gere la suite
      })
      .finally(() => setIsAuthReady(true));
  }, [token]);

  const login = useCallback(async (email: string, password: string) => {
    // Lancer login et preparer getMe en parallele via Promise
    const jwt = await apiLogin(email, password);
    setToken(jwt);

    // getMe immediatement apres — le token est deja en sessionStorage
    const res = await getMe();
    setUser(res.data);
    setCachedUser(res.data);
  }, []);

  const logout = useCallback(async () => {
    await apiLogout();
    setToken(null);
    setUser(null);
    setCachedUser(null);
  }, []);

  const contextValue = useMemo(() => ({
    isAuthenticated: token !== null,
    isAuthReady,
    token,
    user,
    login,
    logout,
  }), [token, isAuthReady, user, login, logout]);

  return (
    <AuthContext.Provider value={contextValue}>
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
