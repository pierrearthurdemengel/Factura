import { createContext, useContext, useState, useCallback, type ReactNode } from 'react';
import { login as apiLogin } from '../api/factura';

interface AuthContextType {
  isAuthenticated: boolean;
  token: string | null;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
}

const AuthContext = createContext<AuthContextType | null>(null);

// Fournisseur du contexte d'authentification.
// Le JWT est stocke en memoire (sessionStorage), le refresh token sera en cookie httpOnly.
export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(
    sessionStorage.getItem('jwt_token'),
  );

  const login = useCallback(async (email: string, password: string) => {
    const jwt = await apiLogin(email, password);
    setToken(jwt);
  }, []);

  const logout = useCallback(() => {
    sessionStorage.removeItem('jwt_token');
    setToken(null);
  }, []);

  return (
    <AuthContext.Provider
      value={{
        isAuthenticated: token !== null,
        token,
        login,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth doit etre utilise dans un AuthProvider');
  }
  return context;
}
