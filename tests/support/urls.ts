export const HTTP_PORT = process.env.ORIEL_HTTP_PORT ?? '8788';
export const CACHED_PORT = process.env.ORIEL_HTTP_CACHED_PORT ?? '8789';
export const MAILPIT_PORT = process.env.ORIEL_MAILPIT_PORT ?? '8790';

export const BASE_URL = `http://localhost:${HTTP_PORT}`;
export const CACHED_URL = `http://localhost:${CACHED_PORT}`;
export const MAILPIT_URL = `http://localhost:${MAILPIT_PORT}`;
