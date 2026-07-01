import { Page, APIResponse } from '@playwright/test';

/**
 * Shared MediaWiki auth helpers for the extension e2e suites.
 *
 * These let a suite run against **staging** as well as local dev. On staging
 * the `Special:UserLogin` form renders reCAPTCHA, which a headless browser
 * can't solve — so form-based login is a dead end there. We log in through the
 * API instead, and teach the strict page-error guard to ignore the reCAPTCHA
 * widget's own JS errors.
 */

/**
 * Log in through the MediaWiki API (`action=clientlogin`) instead of the
 * `Special:UserLogin` form.
 *
 * Why the API and not the form: on staging the login form embeds reCAPTCHA,
 * unsolvable headless. The API login path is captcha-exempt for a privileged
 * test user and sets the same session cookies. Playwright's `page.request`
 * shares its cookie jar with the browser context, so the cookies set here
 * authenticate every subsequent `page.goto(...)` — no form, no captcha.
 */
export async function mwApiLogin(page: Page, username: string, password: string): Promise<void> {
  const api = apiUrl();

  // 1. Fetch a login token (this also seeds the session cookie in the shared jar).
  const tokenResp = await page.request.get(api, {
    params: { action: 'query', meta: 'tokens', type: 'login', format: 'json' },
  });
  const loginToken: unknown = (await tokenResp.json())?.query?.tokens?.logintoken;
  if (typeof loginToken !== 'string' || loginToken === '') {
    throw new Error(`mwApiLogin: no login token from ${api} — ${await snippet(tokenResp)}`);
  }

  // 2. clientlogin with that token. A privileged user is captcha-exempt, so the
  // call completes in one round trip (status PASS) with no interactive UI step.
  const resp = await page.request.post(api, {
    form: {
      action: 'clientlogin',
      format: 'json',
      username,
      password,
      logintoken: loginToken,
      // clientlogin requires a return URL for the (here unused) redirect flow.
      loginreturnurl: baseUrl() + '/',
    },
  });
  const body = await resp.json();
  const status: unknown = body?.clientlogin?.status;
  if (status !== 'PASS') {
    throw new Error(
      `mwApiLogin: clientlogin did not PASS (status=${String(status)}): ${JSON.stringify(body)}`
    );
  }
}

/**
 * True for uncaught page errors thrown by the third-party reCAPTCHA widget
 * (loaded by ConfirmEdit on some site configs — e.g. staging), not by the code
 * under test. Staging's reCAPTCHA script throws `Missing required parameters:
 * sitekey`; that is an unrelated site-config concern, so a strict `pageerror`
 * guard must not fail an extension spec on it.
 */
export function isIgnorablePageError(message: string): boolean {
  return /recaptcha|grecaptcha|Missing required parameters:\s*sitekey/i.test(message);
}

/** Base URL of the target wiki, without a trailing slash. */
function baseUrl(): string {
  return (process.env.MW_BASE_URL || 'http://localhost:8082').replace(/\/+$/, '');
}

/**
 * The `api.php` endpoint for the target wiki.
 *
 * The Kol-Zchut nginx serves pretty article URLs under `/<lang>/…` but the PHP
 * entry points under `/w/<lang>/…`. `MW_SCRIPT_PATH` is the article prefix
 * (`/he` on dev, `/w/he` on staging), so we take the language from its last
 * path segment and address `api.php` directly.
 */
function apiUrl(): string {
  const scriptPath = process.env.MW_SCRIPT_PATH || '/he';
  const lang = scriptPath.replace(/^\/+|\/+$/g, '').split('/').pop() || 'he';
  return `${baseUrl()}/w/${lang}/api.php`;
}

async function snippet(resp: APIResponse): Promise<string> {
  try {
    return `HTTP ${resp.status()}: ${(await resp.text()).slice(0, 200)}`;
  } catch {
    return `HTTP ${resp.status()}`;
  }
}
