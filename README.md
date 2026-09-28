# Campus Coin - Frontend PHP

This version is a PHP frontend with browser LocalStorage for demo data. No MySQL database is required.

## Monthly cycle
- The app detects the current month automatically.
- Dashboard transactions show only the current month.
- When a new month starts, the current transaction list starts empty automatically.
- Previous transactions remain saved in browser history and are used by Reports and CSV export.
- Monthly budgets restart for the new month while previous budget data remains stored.
- Savings goals and Emergency Fund remain cumulative instead of resetting, because they represent long-term savings.

## Date and time
- Every income/expense has a date and time.
- Existing transactions without a time use 12:00 as a fallback.
- The top bar also shows the current date and time.

## Run
1. Extract this folder into `C:\xampp\htdocs\CampusCoin_Frontend_PHP`.
2. Start Apache in XAMPP.
3. Open `http://localhost/CampusCoin_Frontend_PHP/`.
4. Use Ctrl + F5 if the browser has cached an older JavaScript file.

## Important
This is frontend PHP only. Data is stored in the browser using LocalStorage, not MySQL.

## Admin dashboard
Open `admin/login.php` for the admin portal.

Demo admin credentials:
- Email: `admin@campuscoin.com`
- Password: `admin123`

The admin dashboard is responsive and reads the same browser LocalStorage data as the frontend demo. For true multi-user administration across devices, connect PHP + MySQL.
