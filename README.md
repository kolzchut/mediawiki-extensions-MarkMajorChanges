MarkMajorChanges extension for MediaWiki
========================================

NOTE: this is a custom extension for Kol-Zchut (kolz.org.il).
      It was not designed with public use in mind.

The extension adds a shortcut to the action of adding a
"major change" or "arabic" revision tag to the latest
revision of an article, including logging it.

## Technical
ChangeTags and SpecialEditTags aren't modular enough, so
I was forced to rip parts of each to use here (such as
the logging action).
This extension shows an action form (FormAction) that
applies the appropriate tag (majorchange/arabic) and
then logs it.


## Todo
- Better design for the form
- Show already applied tags on the action screen
- AJAX form?
- See about changing MediaWiki core so that a user
  can apply extension-set tags.


## Changelog

### 0.0.2 [2016-02-17]
Fix permission issues

### 0.0.1 [2016-02-10]
initial version
