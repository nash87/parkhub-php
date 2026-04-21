export function isActivePath(pathname: string, to: string): boolean {
  if (pathname === to) return true;
  if (to === '/') return false;
  if (pathname.startsWith(to + '/')) return true;
  if (pathname.startsWith(to + '?')) return true;
  return false;
}
