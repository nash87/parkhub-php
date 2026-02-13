import { create } from 'zustand';

export type UseCase = 'corporate' | 'residential' | 'family' | 'rental' | 'public';

const COLORS: Record<UseCase, string> = {
  corporate: 'blue',
  residential: 'emerald',
  family: 'amber',
  rental: 'purple',
  public: 'red',
};

interface UseCaseState {
  useCase: UseCase;
  color: string;
  setUseCase: (uc: UseCase) => void;
}

export const useUseCaseStore = create<UseCaseState>((set) => ({
  useCase: (localStorage.getItem('useCase') || 'corporate') as UseCase,
  color: COLORS[(localStorage.getItem('useCase') || 'corporate') as UseCase],
  setUseCase: (uc) => {
    localStorage.setItem('useCase', uc);
    set({ useCase: uc, color: COLORS[uc] });
  },
}));
