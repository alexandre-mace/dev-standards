export const label = (option?: { label: string }, fallback?: string) =>
  option?.label ??
  fallback;
