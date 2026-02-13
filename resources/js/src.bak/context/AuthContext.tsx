import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { api } from '../api/client';

interface User {
  id: string;
  username: string;
  email: string;
  name: string;
  role: string;
  preferences: Record<string, any>;
  department?: string;
  picture?: string;
}

interface AuthContextType {
  user: User | null;
  token: string | null;
  login: (username: string, password: string) => Promise<void>;
  register: (data: { username: string; email: string; password: string; name: string }) => Promise<void>;
  logout: () => void;
  updateUser: (data: Partial<User>) => void;
  isAdmin: boolean;
}

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(() => {
    const stored = localStorage.getItem('user');
    return stored ? JSON.parse(stored) : null;
  });
  const [token, setToken] = useState<string | null>(() => localStorage.getItem('token'));

  const login = async (username: string, password: string) => {
    const res = await api.post<any>('/login', { username, password });
    setUser(res.user);
    setToken(res.tokens.access_token);
    localStorage.setItem('user', JSON.stringify(res.user));
    localStorage.setItem('token', res.tokens.access_token);
  };

  const register = async (data: { username: string; email: string; password: string; name: string }) => {
    const res = await api.post<any>('/register', data);
    setUser(res.user);
    setToken(res.tokens.access_token);
    localStorage.setItem('user', JSON.stringify(res.user));
    localStorage.setItem('token', res.tokens.access_token);
  };

  const logout = () => {
    setUser(null);
    setToken(null);
    localStorage.removeItem('user');
    localStorage.removeItem('token');
  };

  const updateUser = (data: Partial<User>) => {
    const updated = { ...user!, ...data };
    setUser(updated);
    localStorage.setItem('user', JSON.stringify(updated));
  };

  return (
    <AuthContext.Provider value={{ user, token, login, register, logout, updateUser, isAdmin: user?.role === 'admin' || user?.role === 'superadmin' }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
};
