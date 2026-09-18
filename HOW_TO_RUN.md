# DisBasura PHP — How to Run & Edit in VS Code

## ═══ STEP 1: COPY FILES ═══
1. Extract this zip
2. Copy the `disbasura` folder into your htdocs:
   - XAMPP default: C:\xampp\htdocs\disbasura\
   - Your location:  Desktop\xampp1\htdocs\disbasura\

## ═══ STEP 2: IMPORT DATABASE ═══
1. Open XAMPP → Start Apache + MySQL (both green)
2. Go to: http://localhost/phpmyadmin
3. Click Import tab at top
4. Choose file → select schema_capstone2_update.sql (inside disbasura folder)
5. Click Go → all ~21 tables are created in one shot: users, collectors,
   schedules, requests, cities, barangays, sitios, sites, administrators,
   disputes, notifications, feedback, announcements, activity_log, and more.
   This one file is the complete, current database — nothing else to import.

## ═══ STEP 3: CREATE ADMIN ═══
1. Go to: http://localhost/disbasura/admin/login.php
2. First time → you'll see "First Time Setup"
3. Fill in your Name, Username, Email, Password
4. Click Create Admin Account

## ═══ STEP 4: ALL LOGIN URLS ═══
| Role       | URL                                              |
|------------|--------------------------------------------------|
| Admin      | http://localhost/disbasura/admin/login.php       |
| Resident   | http://localhost/disbasura/login.php             |
| Collector  | http://localhost/disbasura/collector/login.php   |

## ═══ HOW TO OPEN & EDIT IN VS CODE ═══

### Open the project:
1. Open VS Code
2. Click File → Open Folder
3. Navigate to: Desktop\xampp1\htdocs\disbasura
4. Click Select Folder
5. All files appear in the left panel (Explorer)

### Making changes:
- Click any file in the left panel to open it
- Edit the code
- Press Ctrl+S to save
- Refresh your browser — changes appear instantly (no restart needed)

### Recommended VS Code Extensions:
- PHP Intelephense — code suggestions for PHP
- Prettier — auto format HTML/CSS/JS
- Auto Rename Tag — rename HTML tags automatically
- GitLens — if using Git

### Common files you'll edit:
| File | What to change |
|------|---------------|
| config/db.php | Database password if needed |
| assets/css/base.css | Colors, fonts, global styles |
| assets/css/dashboard.css | Admin panel layout |
| assets/css/auth.css | Login/register page styles |
| admin/dashboard.php | Admin dashboard content |
| admin/collectors.php | Collector management |
| resident/dashboard.php | Resident dashboard |
| collector/dashboard.php | Collector app |
| includes/base_admin.php | Sidebar navigation |

### To change a color:
Open assets/css/base.css → find :root { } → change any --green-main, --orange, etc.

### To add a new page:
1. Create a new .php file in the right folder (admin/, resident/, etc.)
2. Add require at the top:
   <?php require_once __DIR__ . '/../middleware/admin_auth.php'; ?>
3. Add your HTML below it

## ═══ WORKFLOW SUMMARY ═══
1. XAMPP running → edit file in VS Code → save → refresh browser ✅
2. No server restarts needed for PHP changes
3. Only restart Apache if you change php.ini or .htaccess

## ═══ TROUBLESHOOTING ═══
| Problem | Solution |
|---------|----------|
| Blank white page | Enable PHP errors: add ini_set('display_errors',1); at top |
| DB connection failed | Check config/db.php — DB_PASS should be empty for XAMPP |
| Page not found | Make sure folder is named 'disbasura' inside htdocs |
| GPS not working | Chrome blocks GPS on HTTP — use localhost (not IP) |
| Upload not working | Make sure uploads/ folder exists in disbasura/ |
