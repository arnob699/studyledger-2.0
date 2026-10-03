# Study Ledger 2.0

A stats-first study tracker built on your `study_tracker` schema: 90-minute study
cycles + 30-minute revision blocks, with a dashboard that reads straight from the
`daily_stats`, `subject_stats`, `streaks`, and `v_subject_neglect` tables your
triggers already keep in sync.

## Stack

Plain PHP (PDO) + MySQL/MariaDB + vanilla HTML/CSS/JS. No framework, no build step,
no Composer. Charts are drawn with Chart.js, loaded from a CDN on the dashboard only.

## Setup

1. **Import the schema.**

   ```
   mysql -u root -p < schema.sql
   ```

   This creates the `study_tracker` database, tables, triggers, and authentication
   tables. The first user creates their account from the registration screen.

2. **Set your DB credentials.**
   Open `config/db.php` and edit `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` to match
   your local MySQL setup (defaults assume `root` with no password on `localhost`,
   which is the typical XAMPP/Laragon/MAMP default).

3. **Serve the folder.**
   - **XAMPP/Laragon/MAMP:** drop the `study-ledger` folder into `htdocs`
     (or `www`) and open `http://localhost/study-ledger/`.
   - **PHP's built-in server:** from inside the folder, run
     ```
     php -S localhost:8000
     ```
     and open `http://localhost:8000/`.

That's it — no `.env`, no migrations beyond the one SQL file.

## What's included

| Page                            | Purpose                                                                                                                                                                                            |
| ------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Dashboard** (`index.php`)     | Today's cycle count vs. your goal, streak, weekly rhythm chart, time-split by subject, neglected-subjects ranking, goal progress, recent cycles. Refreshes itself every 30s without a page reload. |
| **Study cycle** (`cycle.php`)   | Start a cycle against a subject/material, run a live countdown ring through the study phase, hand off into the revision phase, then complete or abandon.                                           |
| **Subjects** (`subjects.php`)   | Add/edit/archive/delete subjects with a color tag; each card shows its running totals.                                                                                                             |
| **Materials** (`materials.php`) | Break a subject into chapters/topics, track difficulty and status (not started → in progress → learned → mastered).                                                                                |
| **Goals** (`goals.php`)         | Set a daily and/or weekly cycle (and optional minute) target; the dashboard tracks progress against whichever is active.                                                                           |
| **Settings** (`settings.php`)   | Light/dark theme (light is the default), default cycle lengths, timezone label.                                                                                                                    |

### Logging a session manually

Not every study block happens through the live timer — sometimes you just want to
backfill a session you already did. Every page now has a **Log manually** button in
the top bar (next to Start/Resume cycle) that opens a small form: subject, optional
material, date, study/revision minutes, and status. It writes straight into the
`cycles` table like any other cycle, so the same triggers roll it into
`daily_stats`, `subject_stats`, and the streak automatically — no separate table or
recalculation needed.

One caveat: the streak trigger assumes you're logging in chronological order, so
backfilling a date that isn't right after your current streak's last active day can
throw the streak count off. That's a limitation of the existing trigger logic, not
something the manual-add form can fully guard against — treat the streak as
best-effort if you're backfilling old sessions out of order.

## How the stats stay "real-time"

Every write goes through `cycles` (insert on start, update on each phase change).
The schema's own `AFTER INSERT`/`AFTER UPDATE` triggers roll that straight into
`daily_stats`, `subject_stats`, and `streaks` — the PHP layer never recalculates
totals by hand, it just reads the pre-aggregated tables. The dashboard's 30-second
poll (`api/stats.php`) is what makes that feel live in the browser.

## Folder structure

```
study-ledger/
├── schema.sql              your original schema, unchanged
├── config/db.php           PDO connection (edit this)
├── includes/               shared header/sidebar/footer + query helpers
├── api/                    JSON endpoints the frontend calls (cycle, subjects,
│                           materials, goals, settings, stats)
├── assets/css/style.css    the whole design system, one file
├── assets/js/              one small JS file per page, plus shared app.js
├── index.php, cycle.php, subjects.php, materials.php, goals.php, settings.php
```

## Upgrading an existing install

The authentication schema adds ownership columns to the existing tables, so export
any existing data before importing the new `schema.sql`. Existing single-user data
must be assigned to an account during migration; the new schema intentionally does
not guess which login should own it.

## Notes

- Users register and log in with a password of at least 8 characters. Subjects,
  materials, cycles, goals, and settings are scoped to the logged-in account.
- The 90/30 split is just the _default_; you can set a different study/revision
  length per cycle from the start-cycle form, or change the app-wide default in
  Settings.
>>>>>>> 5071c3e (feat: add LeetCode activity heatmap and quarterly progress bar chart)
