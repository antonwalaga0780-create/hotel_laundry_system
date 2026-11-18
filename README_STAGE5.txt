HOTEL LAUNDRY SYSTEM - STAGE 5

1. Keep your existing signup.php, login.php, logout.php, send_item.php,
   return_item.php, transactions.php, edit_item.php and delete_item.php.
2. Copy the Stage 5 PHP files into:
   C:\xampp\htdocs\hotel_laundry_system\
3. Replace dashboard.php, reports.php and view_items.php with these Stage 5 versions.
4. Copy damaged_item.php and export_report.php into the same project folder.
5. Open phpMyAdmin, select hotel_laundry_db, click SQL and run database_stage5.sql ONCE.
6. Open:
   http://localhost/hotel_laundry_system/dashboard.php

IMPORTANT:
- database_stage5.sql adds the damaged_quantity column. Run the ALTER TABLE only once.
- Stage 5 expects the existing laundry_items columns:
  id, item_name, item_type, quantity, sent_quantity, returned_quantity,
  status, notes.
- If your existing table uses different column names, stop and ask for an adjustment
  rather than deleting your existing data.
