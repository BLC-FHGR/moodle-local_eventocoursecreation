# Evento Course Creation

Moodle plugin of the FH Graubuenden which connects Moodle courses to the events of the
Evento application. It does two things:

1. It creates Moodle courses out of the Evento events of a category.
2. It imports the Evento module description into those courses and keeps it up to date.

Both parts run as scheduled tasks and need the `local_evento` plugin, which holds the
access to the Evento SOAP webservice.

## Requirements

*   `local_evento`, in a version that offers `getEventoModulBeschreibung`. The module
    description import needs that operation, course creation works without it.
*   Moodle. The plugin is developed and run on Moodle 5.2. The `requires` value in
    `version.php` still names 2016120500 and is older than what the code actually needs:
    the module description import uses `\core_courseformat\formatactions` and
    `\core\cron`, which older releases do not have.

## Course creation

A course is created for every Evento event of a category whose idnumber carries the module
prefix, for example `mod.dbm`. The new course gets

*   the long name from the Evento module name,
*   the short name from the Evento event number without the leading `mod.`,
*   start and end date from the Evento event,
*   the idnumber from the Evento event number,
*   an Evento enrolment method.

Only events edited at most a year ago and starting in the future are taken into account.
If a template course is configured, the new course is restored from it.

Further prefixes exist next to `mod`, evento also uses `modk`, `mods`, `modh` and `modg`.
All of them are accepted.

Two options are kept for existing installations but are not developed further:

*   several courses of studies in one category (deprecated)
*   common courses for several Evento events (deprecated)

### Settings

*   Enable the plugin
*   Naming pattern for the long and the short course name
*   Default course settings: visibility, number of announcements, number of sections
*   Start and end of the course creation window for the spring and the autumn term
*   Option to run only on the start date instead of during the whole window
*   Template course

Per category the creation can be switched off and a template course of its own can be set.

## Module description import

The short module description of Evento is written into the course as a page resource. It
is placed at the top of the general section and is updated whenever the description
changes in Evento. Teachers cannot edit, move, hide or delete it.

### How it works

*   The event number of a course comes from its idnumber, or from the oldest Evento
    enrolment method of the course if the idnumber does not carry one.
*   The HTML of Evento is rewritten before it is stored. The elements `article` and
    `section`, the `id` attribute and the data attributes do not survive the Moodle
    purifier, so their meaning is carried over into class attributes prefixed `eventomb`.
    Fields whose value Evento delivers entity encoded are unpacked into real markup.
    Everything is purified afterwards, mod_page renders without cleaning.
*   The page is recognised again by the idnumber of the course module,
    `evento.modulbeschreibung` by default. That idnumber survives a course backup and
    restore, which is how a page carried over from a template is taken over instead of
    being duplicated.
*   A page is only written when something really changed. The content is compared through
    a hash, the description line and the name are compared separately, and the Evento
    version and validity are kept in a link record.
*   The description line above the text names the Evento version and the date the
    description is valid from. It is built in the language of the site and the time zone of
    the server, so that it comes out the same on every run.
*   The editing protection is checked on every pass, not only when the page is written, so
    a role that gained the right to edit activities later is covered as well.
*   The credits of the module are written into the module properties of the description.
    They live on the Evento event and not on the module description, so they need a second
    webservice call, which is only made when the credits sentence is configured at all. An
    It goes in front of the first property, wrapped in the
    configured element; Evento writes every property as a definition list of its own, so
    the default `dl` spaces it exactly like one property against the next.

### Settings

| Setting | Meaning |
| --- | --- |
| Import module descriptions | Switches the whole import on and off |
| Courses to synchronise | Running and future courses, or future courses only |
| Page name | Name of the page resource |
| Heading above the description | Put in front of the text inside the page, empty for none |
| Credits sentence | Written into the module properties, `[ECTS]` carries the Evento value, empty for none |
| Element of the credits sentence | Decides how large the sentence is shown |
| Element around the credits sentence | `dl` spaces it like one Evento property against the next, empty for none |
| Page ID number | Marker on the course module, do not change it once courses exist |
| Accepted Evento status ids | Comma separated, 61003 is "approved", empty accepts every status |
| Import descriptions valid in the future | Needed for courses created before the term starts |
| Display | How mod_page shows the page |
| Display last modified date | Shows when the description was last changed |
| Page deleted manually | Create it again, do nothing, or stop synchronising the course |
| Page renamed manually | Restore the configured name or keep the one given by hand |
| Batch size | Courses per run of the scheduled task, oldest check first |
| Retry delay in hours | How long a course is held back after a failure |

A module without a description in Evento is not a failure. Evento answers such a request
with a fault, which is recognised and stored as a missing description, without holding the
course back and without stopping the run.

### Storage

The table `eventocoursecreation_page` holds one row per course: the event number, the
course module of the page, the Evento values `idMB`, `idStatus`, `mbVersion` and
`mbGueltigAb`, the hash of the stored content, the name the page carries, the state, the
failure counter and the timestamps. It holds no personal data.

## Tasks

| Task | When |
| --- | --- |
| `evento_course_creation_sync_task` | daily at 22:15 |
| `evento_modul_description_sync_task` | daily at 03:20 |
| `modul_description_course_task` | adhoc, queued after a course was created from a template and after a course was restored |

A restored course never fires `course_created`, so the import is queued from an observer on
`course_restored`.

## Command line

Run everything as the web server user, from the Moodle web root.

```
# Course creation, optionally for one category only
php local/eventocoursecreation/cli/sync.php --verbose [--catid=ID]

# Module description import
php local/eventocoursecreation/cli/sync_moduldescriptions.php --verbose
php local/eventocoursecreation/cli/sync_moduldescriptions.php --courseid=ID --verbose
php local/eventocoursecreation/cli/sync_moduldescriptions.php --list
php local/eventocoursecreation/cli/sync_moduldescriptions.php --retry

# What would end up in the page, without changing anything
php local/eventocoursecreation/cli/preview_moduldescription.php --courseid=ID [--raw]
php local/eventocoursecreation/cli/preview_moduldescription.php --anlassnummer=NUMBER
php local/eventocoursecreation/cli/preview_moduldescription.php --sample

# Why the import did or did not happen, reads only
php local/eventocoursecreation/cli/diagnose_moduldescription.php [--courseid=ID]
```

`--courseid` synchronises a single course even when it is outside the configured scope.
`--retry` drops the backoff for one run, which brings back courses held back after a
failure. Every script prints its options with `-h`.

Use `preview_moduldescription.php` whenever Evento changes the markup of its module
descriptions. It shows the result of the conversion and the cleaning before anything is
written, and it fails with a non zero exit code when one of its checks does not hold.

## Development

*   Business logic lives in `classes/modul_description.php` and is covered by PHPUnit tests
    in `tests/`. That class writes nothing to a course.
*   Everything that changes a course lives in `classes/modul_description_page.php`.
*   The run itself is orchestrated in `classes/modul_description_sync.php`.
*   Strings are in `lang/en` and `lang/de`.

```
vendor/bin/phpunit --filter local_eventocoursecreation
```

## License

* Copyright (C) HTW Chur, FH Graubuenden

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details:
http://www.gnu.org/copyleft/gpl.html
