MarkMajorChanges extension for MediaWiki
========================================

NOTE: this is a custom extension for Kol-Zchut (kolzchut.org.il).
      It was not designed with public use in mind.

The extension adds a shortcut to the action of adding a
"major change" or "arabic" revision tag to the latest
revision of an article, including logging it.
It also allows to mark those log lines as "taken care of".

## Usage
Adds an action to a page to mark as major change. This action is logged. the log can be viewed at
[[Special:MajorChangesLog]].

Also adds an API query, majorchangeslogevents, used this way:

```api.php?action=query&format=json&prop=info&list=majorchangeslogevents```

It uses the same parameters as ```logevents```, but sets the action as tag/update and adds a URL.

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
- See about changing MediaWiki core so that a user can apply extension-set tags.


## Changelog

### 0.4.0 [2020-28]
- Add "lemode" param to API, to limit by tag (onlyminor, onlymajor, all)
- Add "lecategory" param to API, to limit to a specific category  

### 0.3.0 [2020-10-23]
- Add an API query to retrieve the log (based on query/logevents)

### 0.2.0 [2020-10-21]
- Special page updated:
  - Add the option to filter by start/end date
  - Use OOUI for display
  - Use FormOptions and a form descriptor to handle all the fields instead of manually building it

### 0.1.0 [2017-09-07]
- Allow to mark a change as "taken care of" when viewing the log.
  This uses MW's default capability of applying tags to log lines as
  well.
- The log can be filtered by handled/queued changes.
- i18n has taken another hit - the extension is even more
  Kol-Zchut's-specific than ever.

### 0.0.3 [2016-02-29]
- Hide secondary change checkbox on action page if the page doesn't have
  an Arabic language link (Kol-Zchut centric, no way around this for now)
- Automatically mark a change as a secondary change too, if it has a langlink
  and it is being marked as a major change
- New Special:MajorChangesLog, which shows only relevant log entires
  AND allows filtering by the *revision's* tags (special:log only allows
  filtering by the log entry's tags, which is useless for this).

### 0.0.2 [2016-02-17]
Fix permission issues

### 0.0.1 [2016-02-10]
initial version
