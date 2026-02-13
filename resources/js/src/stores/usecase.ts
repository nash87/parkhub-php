import { create } from "zustand";
import { persist } from "zustand/middleware";

export type UseCaseType = "corporate" | "residential" | "family" | "rental" | "public";

interface UseCaseState {
  useCase: UseCaseType;
  setUseCase: (uc: UseCaseType) => void;
}

export const useUseCaseStore = create<UseCaseState>()(
  persist(
    (set) => ({
      useCase: "corporate",
      setUseCase: (uc) => set({ useCase: uc }),
    }),
    { name: "parkhub-usecase" }
  )
);
