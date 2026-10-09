export interface ClickAction {
  type: 'click';
  selector: string;
}

export interface ReadyCondition {
  selector: string | null;
}

export interface RenderRequest {
  url: string;
  actions: ClickAction[];
  ready: ReadyCondition;
  timeoutMs: number;
  allowLocalFile: boolean;
  allowedHosts: string[];
  repeat: {click: string; items: string; maxClicks: number} | null;
}

export const RENDER_LIMITS = {
  deadlineMs: 60_000,
  maxHtmlBytes: 5_000_000,
  maxTransferBytes: 50_000_000,
  maxRequests: 250,
} as const;

function record(value: unknown): Record<string, unknown> {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    throw new Error('Invalid render request');
  }
  return value as Record<string, unknown>;
}

function selector(value: unknown): string {
  if (typeof value !== 'string' || value.length === 0 || value.length > 1000) {
    throw new Error('Invalid selector');
  }
  return value;
}

export function parseRenderRequest(input: unknown): RenderRequest {
  const value = record(input);
  if (typeof value.url !== 'string' || value.url.length > 2048) throw new Error('Invalid URL');
  let url: URL;
  try { url = new URL(value.url); } catch { throw new Error('Invalid URL'); }
  const allowLocalFile = value.allowLocalFile === true;
  if (url.username || url.password || !(['http:', 'https:'].includes(url.protocol) || (allowLocalFile && url.protocol === 'file:'))) {
    throw new Error('Invalid URL');
  }
  if (!Array.isArray(value.allowedHosts) || value.allowedHosts.length > 20
    || value.allowedHosts.some(host => typeof host !== 'string' || !/^[a-z0-9.-]+$/.test(host))) {
    throw new Error('Invalid allowed hosts');
  }
  const allowedHosts: string[] = value.allowedHosts;
  if (url.protocol !== 'file:' && !allowedHosts.includes(url.hostname)) throw new Error('Host is not allowed');
  const timeoutMs = value.timeoutMs ?? 30_000;
  if (!Number.isInteger(timeoutMs) || (timeoutMs as number) < 1 || (timeoutMs as number) > 30_000) {
    throw new Error('Invalid timeout');
  }
  const actions = value.actions ?? [];
  if (!Array.isArray(actions) || actions.length > 5) throw new Error('Invalid actions');
  const parsedActions = actions.map((action: unknown): ClickAction => {
    const item = record(action);
    if (item.type !== 'click') throw new Error('Unsupported action');
    return { type: 'click', selector: selector(item.selector) };
  });
  const ready = record(value.ready ?? { selector: null });
  let repeat: RenderRequest['repeat'] = null;
  if (value.repeat !== undefined) {
    const spec = record(value.repeat);
    if (!Number.isInteger(spec.maxClicks) || (spec.maxClicks as number) < 1 || (spec.maxClicks as number) > 50) {
      throw new Error('Invalid repeat limit');
    }
    repeat = {click: selector(spec.click), items: selector(spec.items), maxClicks: spec.maxClicks as number};
  }
  return {
    url: value.url,
    actions: parsedActions,
    ready: { selector: ready.selector === null ? null : selector(ready.selector) },
    timeoutMs: timeoutMs as number,
    allowLocalFile,
    allowedHosts,
    repeat,
  };
}
