import { createContext } from 'react';
import type { User } from '../api/client';

export interface AuthContextType {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (username: string, password: string) => Promise<boolean>;
  logout: () => void;
  register: (data: { username: string; email: string; password: string; name: string }) => Promise<boolean>;
}

export const AuthContext = createContext<AuthContextType | null>(null);
