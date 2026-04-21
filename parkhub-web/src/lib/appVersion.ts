// @ts-ignore — Vite resolves JSON imports at build time
import { version as pkgVersion } from '../../package.json';

export const APP_VERSION: string =
  (typeof import.meta !== 'undefined' && (import.meta as { env?: { VITE_APP_VERSION?: string } }).env?.VITE_APP_VERSION)
  || pkgVersion
  || 'dev';
