import { Page, Locator } from '@playwright/test';

/**
 * Page object for the MarkMajorChanges toolbar action and its log special page.
 *
 * The action is a page-navigation link (`?action=markmajorchange`), so it is
 * located by its href rather than its localized label — language-independent.
 */
export class MarkMajorChangesPage {
  readonly page: Page;
  readonly scriptPath: string;
  readonly articlePath: string;

  constructor(page: Page) {
    this.page = page;
    this.scriptPath = process.env.MW_SCRIPT_PATH || '/he';
    // Default to the wiki root (the main page) — always present. Override with
    // MW_ARTICLE_PATH to target a specific content page.
    this.articlePath = process.env.MW_ARTICLE_PATH || `${this.scriptPath}/`;
  }

  /** Form-based login via Special:UserLogin. */
  async login(username: string, password: string): Promise<void> {
    await this.page.goto(`${this.scriptPath}/Special:UserLogin`);
    await this.page.getByRole('textbox').first().fill(username);
    await this.page.locator('input[type="password"]').fill(password);
    await this.page.locator('button[type="submit"], #wpLoginAttempt').first().click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  async gotoArticle(): Promise<void> {
    await this.page.goto(this.articlePath);
    await this.page.waitForLoadState('domcontentloaded');
  }

  async gotoLog(): Promise<void> {
    await this.page.goto(`${this.scriptPath}/Special:MajorChangesLog`);
    await this.page.waitForLoadState('domcontentloaded');
  }

  /** The "mark major change" action link, if present in the page chrome. */
  markMajorChangeAction(): Locator {
    return this.page.locator('a[href*="action=markmajorchange"]');
  }
}
