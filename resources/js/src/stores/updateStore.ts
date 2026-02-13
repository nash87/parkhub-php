import { create } from 'zustand';

interface UpdateStore {
  updateAvailable: boolean;
  latestVersion: string;
  releaseUrl: string;
  checkForUpdates: (token: string) => Promise<void>;
}

export const useUpdateStore = create<UpdateStore>((set) => ({
  updateAvailable: false,
  latestVersion: '',
  releaseUrl: '',
  checkForUpdates: async (token: string) => {
    try {
      const res = await fetch('/api/v1/admin/updates/check', {
        headers: { Authorization: `Bearer ${token}` }
      });
      const data = await res.json();
      if (data.update_available) {
        set({
          updateAvailable: true,
          latestVersion: data.latest,
          releaseUrl: data.release_url,
        });
      } else {
        set({ updateAvailable: false, latestVersion: '', releaseUrl: '' });
      }
    } catch {
      // silently fail
    }
  },
}));
