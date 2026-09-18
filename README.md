# DisBasura

Web-based garbage collection management and private collector dispatch system for Cebu City — Capstone Project, University of Cebu – Main Campus.

## Team
- **Sayson** — Backend / DBA
- **Paller** — UI/UX / Frontend
- **Paradilla** — Documentation / QA

## Stack
PHP + MySQL/MariaDB (XAMPP), vanilla JS, no framework.

## Features
- 4 roles: Admin, Resident, Sitio Leader, Collector
- Weekly + one-off pickup scheduling
- AI Smart Dispatch (rule-based: proximity, workload, rating)
- Live GPS collector tracking
- SMS notifications (Semaphore API)
- Dispute resolution, resident feedback/ratings, announcements
- Bilingual UI (English / Filipino)
- Full geographic hierarchy: City → Barangay → Sitio → Site

## Setup

1. **Clone the repo** into your XAMPP `htdocs` folder:
   ```bash
   cd C:\xampp\htdocs
   git clone <this-repo-url> disbasura
   ```
2. **Start Apache + MySQL** in XAMPP.
3. **Import the database** — open [phpMyAdmin](http://localhost/phpmyadmin) → Import → select `schema_capstone2_update.sql` → Go. This one file creates the entire database (21 tables).
4. **Create your admin account** — go to `http://localhost/disbasura/admin/login.php`, it'll prompt "First Time Setup" the first time.
5. **Add a city, barangay, and sitio** — Admin → Manage Locations — before anyone can register.
6. Full walkthrough: see [`HOW_TO_RUN.md`](HOW_TO_RUN.md).

## Project structure
```
disbasura/
├── schema_capstone2_update.sql   # full DB schema — import this
├── config/                       # DB connection, session, SMS config
├── includes/                     # shared helpers, smart dispatch logic
├── admin/                        # admin panel (16 pages)
├── collector/                    # collector portal
├── leader/                       # sitio leader portal
├── resident/                     # resident portal
├── api/                          # AJAX endpoints (geo dropdowns, feedback, GPS, etc.)
├── lang/                         # en.php / fil.php translations
└── uploads/                      # user-uploaded photos (gitignored — local only)
```

## Working as a team

- **Don't push directly to `main`.** Create a branch per feature/fix:
  ```bash
  git checkout -b yourname/short-description
  ```
- Commit small, working changes with clear messages.
- Push your branch and open a Pull Request on GitHub — even solo teams benefit from the diff view before merging.
  ```bash
  git push -u origin yourname/short-description
  ```
- Pull before you start working each session:
  ```bash
  git pull origin main
  ```
- If you change the database, add a new numbered migration file rather than editing `schema_capstone2_update.sql` directly once teammates have already imported it — otherwise everyone's local database silently drifts out of sync.
- `uploads/` is gitignored on purpose — don't force-add real uploaded photos into the repo.

## Status
Capstone 2 — in active development.
