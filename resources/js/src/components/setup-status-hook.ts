import { useContext } from 'react';
import { SetupContext } from './setup-context';

export function useSetupStatus() {
  return useContext(SetupContext);
}
