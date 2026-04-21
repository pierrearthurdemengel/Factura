// Simple in-memory SWR cache for API responses
interface CacheEntry {
  data: unknown;
  ts: number;
}

const store = new Map<string, CacheEntry>();
const DEFAULT_TTL = 30_000; // 30 seconds

function makeKey(url: string, params?: Record<string, string>): string {
  const p = params ? '?' + new URLSearchParams(params).toString() : '';
  return `${url}${p}`;
}

export function getCached<T>(url: string, params?: Record<string, string>, ttl = DEFAULT_TTL): T | null {
  const key = makeKey(url, params);
  const entry = store.get(key);
  if (entry && Date.now() - entry.ts < ttl) {
    return entry.data as T;
  }
  return null;
}

export function setCache(url: string, data: unknown, params?: Record<string, string>): void {
  const key = makeKey(url, params);
  store.set(key, { data, ts: Date.now() });
}

// Invalidate all entries matching a URL prefix
export function invalidateCache(prefix?: string): void {
  if (!prefix) {
    store.clear();
    return;
  }
  for (const key of store.keys()) {
    if (key.startsWith(prefix)) store.delete(key);
  }
}
