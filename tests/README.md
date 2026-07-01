# MarkMajorChanges e2e tests

Playwright end-to-end tests for the MarkMajorChanges extension. They lock in the
MW 1.43 modernization: the "mark major change" toolbar action is registered on
the surviving `SkinTemplateNavigation::Universal` hook (the legacy hook silently
stopped firing on 1.43, which had removed the button), and `Special:MajorChangesLog`
still renders.

The action link is gated on the `changetags` + `markmajorchange` rights, so the
suite asserts it is **visible to a staff user** and **absent for anonymous
visitors**. Assertions are by href (`?action=markmajorchange`), independent of the
wiki content language.

## Setup

```sh
cd tests
npm install
npx playwright install chromium
```

## Run

The anonymous check runs with no credentials. The staff-only checks **skip**
(they never silently pass) unless credentials are supplied.

```sh
# against local dev (main site, he)
MW_USERNAME=Dockerstaff MW_PASSWORD=<dev-password> \
  npx playwright test

# against another target
MW_BASE_URL=https://staging-test.wikirights.org.il MW_SCRIPT_PATH=/w/he \
MW_USERNAME=Dockerstaff MW_PASSWORD=<vault-password> \
  npx playwright test
```

## Environment variables

| Variable          | Default                 | Purpose                                     |
|-------------------|-------------------------|---------------------------------------------|
| `MW_BASE_URL`     | `http://localhost:8082` | Wiki base URL                               |
| `MW_SCRIPT_PATH`  | `/he`                   | Language/path prefix to the wiki            |
| `MW_ARTICLE_PATH` | the wiki root           | Content page to check the action on         |
| `MW_USERNAME`     | — (skips if unset)      | A `staff`-group user (e.g. Dockerstaff)     |
| `MW_PASSWORD`     | — (skips if unset)      | That user's password                        |
