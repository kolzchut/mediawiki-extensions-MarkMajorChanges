import { test, expect } from '@playwright/test';
import { MarkMajorChangesPage } from '../pages/MarkMajorChangesPage';

/**
 * MarkMajorChanges — toolbar action + log special page.
 *
 * Regression cover for the MW 1.43 modernization: the "mark major change"
 * action is registered on the surviving `SkinTemplateNavigation::Universal`
 * hook via an instance-based HookHandler, so it must render again for a staff
 * user (the legacy hook silently stopped firing on 1.43, removing the button).
 *
 * The action link is gated on the `changetags` + `markmajorchange` rights, so:
 *   - a staff user (MW_USERNAME/MW_PASSWORD) sees it,
 *   - an anonymous visitor does not.
 *
 * Special:MajorChangesLog must also render (its pager/DI were reworked).
 */

const USERNAME = process.env.MW_USERNAME ?? '';
const PASSWORD = process.env.MW_PASSWORD ?? '';

test.describe('MarkMajorChanges', () => {
  test('the action is hidden from anonymous visitors', async ({ page }) => {
    const mmc = new MarkMajorChangesPage(page);
    await mmc.gotoArticle();
    // Permission gate: anon lacks markmajorchange.
    await expect(mmc.markMajorChangeAction()).toHaveCount(0);
  });

  test.describe('as a staff user', () => {
    test.skip(
      !USERNAME || !PASSWORD,
      'Set MW_USERNAME / MW_PASSWORD (a staff-group user, e.g. Dockerstaff) to run the authenticated specs.'
    );

    test('the "mark major change" toolbar action renders on an article', async ({ page }) => {
      const mmc = new MarkMajorChangesPage(page);
      await mmc.login(USERNAME, PASSWORD);
      await mmc.gotoArticle();
      // The Universal-hook handler re-adds the action for staff.
      await expect(mmc.markMajorChangeAction().first()).toBeVisible();
    });

    test('Special:MajorChangesLog renders without a fatal', async ({ page }) => {
      const mmc = new MarkMajorChangesPage(page);
      await mmc.login(USERNAME, PASSWORD);
      await mmc.gotoLog();
      await expect(page.locator('body')).not.toContainText('Internal error');
      await expect(page.locator('body')).not.toContainText('Fatal error');
      // The log special page renders an OOUI filter form.
      await expect(page.locator('form')).not.toHaveCount(0);
    });
  });
});
