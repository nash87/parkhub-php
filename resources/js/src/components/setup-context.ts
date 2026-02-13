import { createContext } from 'react';

export interface SetupContextType {
  setupComplete: boolean | null;
  recheckSetup: () => Promise<void>;
}

export const SetupContext = createContext<SetupContextType>({
  setupComplete: null,
  recheckSetup: async () => {},
});
