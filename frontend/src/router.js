import { useEffect, useState } from 'react';

export function currentPath() {
  return window.location.pathname;
}

export function navigate(path, { replace = false } = {}) {
  if (replace) {
    window.history.replaceState({}, '', path);
  } else {
    window.history.pushState({}, '', path);
  }

  window.dispatchEvent(new PopStateEvent('popstate'));
}

export function usePathname() {
  const [path, setPath] = useState(currentPath);

  useEffect(() => {
    const updatePath = () => setPath(currentPath());
    window.addEventListener('popstate', updatePath);

    return () => window.removeEventListener('popstate', updatePath);
  }, []);

  return path;
}

export function Redirect({ to, replace = true }) {
  useEffect(() => {
    navigate(to, { replace });
  }, [replace, to]);

  return null;
}
