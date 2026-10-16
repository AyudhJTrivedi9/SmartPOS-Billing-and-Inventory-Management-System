#Screenshorts
<img width="1920" height="1080" alt="01_Login" src="https://github.com/user-attachments/assets/a48605fb-8fd0-4088-bb33-6521eb9d8455" />
<img width="1920" height="1080" alt="02_Dashboard" src="https://github.com/user-attachments/assets/c0dd957a-36aa-41ec-ad03-3a94b8d4c989" />
<img width="1920" height="1080" alt="03_Product" src="https://github.com/user-attachments/assets/e3a44ea0-dc84-4555-8e59-4622eb355cb6" />
<img width="1920" height="1080" alt="04_Add_Product" src="https://github.com/user-attachments/assets/a4c9ac50-0b96-491b-81af-1539543abf4a" />
<img width="1920" height="1080" alt="05_Added_Product" src="https://github.com/user-attachments/assets/5a6ff997-d593-4ab8-9f1c-a1b4feb0efef" />
<img width="1920" height="1080" alt="06_Product_Details" src="https://github.com/user-attachments/assets/3754375e-5015-4111-8525-2a22ec9b08b0" />
<img width="1920" height="1080" alt="07_Update_Product" src="https://github.com/user-attachments/assets/2e378ad7-b5e0-45ec-8715-6eecce95759c" />
<img width="1920" height="1080" alt="08_Updated_Product" src="https://github.com/user-attachments/assets/596f98c5-c861-4c5e-a783-de5d37be495a" />
<img width="1920" height="1080" alt="09_POS_Billing" src="https://github.com/user-attachments/assets/b8282569-d44b-4301-8e1b-61d791a855c9" />
<img width="1920" height="1080" alt="10_Save_Invoice" src="https://github.com/user-attachments/assets/79386aab-b290-4acf-bd15-2e4f27836d9f" />
<img width="1920" height="1080" alt="11_Saved_Invoice" src="https://github.com/user-attachments/assets/9756b3ca-7a65-49a1-8173-1a15fe82c1da" />
<img width="1920" height="1080" alt="12_Invoice_Summary" src="https://github.com/user-attachments/assets/7e44318d-37d0-4fcd-9f6f-9329cb84333f" />
<img width="1920" height="1080" alt="13_Total_Invoices" src="https://github.com/user-attachments/assets/a1b441da-324c-4de8-a1c6-1d99d900534f" />
<img width="1920" height="1080" alt="14_Sales_Report" src="https://github.com/user-attachments/assets/3fcafca7-5af7-49a5-aaa4-d16da3bcd0bb" />
<img width="1920" height="1080" alt="15_Sales_Graph_Summary" src="https://github.com/user-attachments/assets/bfdedfaf-b5a8-481a-bcdb-19b7f5e4eec0" />
<img width="1920" height="1080" alt="16_Best_Selling_Product" src="https://github.com/user-attachments/assets/af9ce4c0-3bfc-498f-9e2e-ab95c8cfa418" />


# SmartPOS – Product & Billing Management System

A Core PHP 8.1+ / MySQL 8 / Bootstrap 5 application for product management and
point-of-sale billing. No framework, no Composer.

## What's new in v3 (extra features and a new look)
**New look:** emerald/teal theme, floating gradient sidebar, rounded cards, a greeting banner on the dashboard, and a
**light / dark theme switch** (moon/sun button in the top bar; your choice is remembered in the browser).

**New features**
| Feature | Where |
|---|---|
| Payment method (Cash / UPI / Card) saved on every invoice and shown on the list and invoice page | POS, Invoices |
| **Quick add** buttons for the top-selling in-stock products | POS |
| **Cash received** box that shows the change to return (display only) | POS |
| Keyboard shortcuts: `F2` focus search, `Ctrl+Enter` save invoice | POS |
| **Share on WhatsApp** button with a ready-made invoice message | Invoice page |
| **Export CSV** of the filtered invoice list (opens in Excel) | Invoices |
| **Quick restock** (add received units to stock) | Product details page |
| **Low stock only** filter and a stock-level bar on each row | Products |
| Today's sales and profit, **top customers**, and **payment mix** | Dashboard |
| Sales by payment method | Reports |

**Already imported the first version?** Either re-import `database.sql` (resets all data), or keep your data and run
`upgrade_v2.sql` once in phpMyAdmin (SQL tab). It only adds the `payment_method` column.

**New files:** `products/restock.php`, `invoices/export.php`, `upgrade_v2.sql`.

### Extra tests for v3
- [ ] Click the moon icon: the whole app turns dark; reload the page and it stays dark. Click the sun to go back.
- [ ] POS: click a "Quick add" chip: the product is added; click it again: quantity goes up, no duplicate line.
- [ ] POS: choose UPI, save: the invoice page shows "Paid by UPI"; the Invoices list shows a UPI badge.
- [ ] POS: type a cash amount larger than the total: "Change to return" shows the difference; smaller: "Short by".
- [ ] POS: press `F2` (focuses search) and `Ctrl+Enter` (saves when the cart is valid).
- [ ] Invoice page: "Share on WhatsApp" opens WhatsApp with the invoice summary filled in.
- [ ] Invoices: filter by a date, click Export CSV: the file contains only the filtered rows.
- [ ] Product details: Quick restock +20 raises the stock by 20; an empty, 0 or negative value is rejected.
- [ ] Products: "Low stock only" lists items with 10 or fewer units; Clear shows everything again.
- [ ] Dashboard shows today's sales, top customers and the payment mix; Reports shows sales by payment method.

## 1. Requirements
- Windows with **XAMPP** providing **PHP 8.1 or newer** and **MySQL 8.0.16+**
  (CHECK constraints are only enforced from 8.0.16). If your XAMPP ships MariaDB
  instead of MySQL, see Troubleshooting.
- No internet needed: all front-end libraries are included in `assets/vendor/`.

## 2. Setup (Windows + XAMPP)
1. Install XAMPP and open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache** and **MySQL**. Both should turn green.
3. Copy the project folder into `C:\xampp\htdocs\` and make sure it is named exactly
   **`smartpos`**, so you have `C:\xampp\htdocs\smartpos\index.php`.
4. Open **http://localhost/phpmyadmin**.
5. Click the **Import** tab, choose `C:\xampp\htdocs\smartpos\database.sql`, then click **Import**.
   This creates the `smartpos_db` database with sample data. (Re-importing resets all data.)
6. Check `config/database.php`. XAMPP defaults (`root`, empty password) already match.
   If you set a MySQL password, change `DB_PASS`.
7. The folder name is detected automatically. Only if links are still wrong, set `$__baseOverride`
   in `config/config.php` (for example `'/myfolder'`).
8. Open the app.

## 3. URL
**http://localhost/smartpos/**

## 4. Project structure
```
smartpos/
├── index.php                 Dashboard
├── database.sql              Schema + sample data
├── upgrade_v2.sql            Adds payment_method to an existing v1 database
├── config/    config.php, database.php
├── includes/  bootstrap.php, helpers.php, customers.php, header.php, sidebar.php,
│              footer.php, product_functions.php, invoice_functions.php, report_functions.php
├── products/  index.php, add.php, edit.php, view.php, delete.php, restock.php, _form.php
├── pos/       index.php, search.php (AJAX), save.php
├── invoices/  index.php, view.php, export.php
├── reports/   index.php
├── assets/    css/style.css, css/print.css, js/app.js, js/pos.js
└── storage/logs/app.log      (created automatically; PHP and app errors are logged here)
```

## 5. Error logging (development)
Errors are never shown to users. They are written to `storage/logs/app.log`.
Open that file when you see "Something went wrong". Before deploying anywhere public,
set `APP_ENV` to `'production'` in `config/config.php`, which hides the developer hint.

## 6. Manual testing checklist

### Products
- [ ] Products page lists 16 sample products, 10 per page, with a working pagination bar.
- [ ] Search "logitech" shows only Logitech products. Search "router" finds the TP-Link router. Search "zzz" shows the empty state and a Clear button.
- [ ] Add Product with valid data: success message, product appears at the top of the list.
- [ ] Add with empty fields: each field shows an error. Add with cost `-5`: rejected. Add `abc` or `1.234`: rejected.
- [ ] Add the same name + brand again (even in different letter case): "already exists" error.
- [ ] Add a product with selling price below cost: saved, with a warning message.
- [ ] View Details shows prices, profit margin, stock, timestamps and sales summary.
- [ ] Edit a product, change price/stock, save: list reflects it.
- [ ] Delete "Laptop Backpack 15.6 inch" (ID 12) is **blocked** because it is on an invoice.
- [ ] Add a brand-new product, then Delete it: a confirmation dialog appears, and it is removed after confirming.
- [ ] Open `http://localhost/smartpos/products/delete.php` directly in the address bar: nothing is deleted, you get a warning and a redirect.

### POS: customer, search, cart
- [ ] Customer dropdown lists 6 customers.
- [ ] Type "log": results show name, brand, price and stock. "Wireless Charger 15W" (stock 0) shows "Out of stock" and cannot be clicked.
- [ ] Click a product: it appears in the cart with line total and subtotal.
- [ ] Add the same product again: **no new line**, quantity goes up by 1.
- [ ] Press Enter in the search box: adds the first available result and does not submit the form.
- [ ] Change a quantity: line total, subtotal and grand total update immediately.
- [ ] Enter a quantity above stock (for example 99 for "HD Webcam C270", stock 3): red error, Save disabled. Leaving the field snaps it back to 3.
- [ ] Enter `0`, empty or `-2`: error shown, Save disabled.
- [ ] Remove a line with the X button. "Clear cart" asks for confirmation.
- [ ] Empty cart: Save Invoice is disabled.

### Discounts
- [ ] Percentage 10 on a subtotal of ₹1,000.00: discount ₹100.00, total ₹900.00.
- [ ] Percentage 150: error "cannot exceed 100%".
- [ ] Fixed 500 on a subtotal of ₹300: error "cannot exceed the subtotal".
- [ ] Fixed 0 or a negative value: error. Discount type set but value blank: Save disabled with a hint.
- [ ] Percentage 100: total ₹0.00 (allowed, never negative).

### Saving and stock
- [ ] Note the stock of "Wireless Mouse M221" (38). Sell 2 with a 10% discount and save.
- [ ] You land on the invoice page with a success message and an invoice number like `INV-YYYYMMDD-0001`.
- [ ] Products page now shows stock 36.
- [ ] Sell more than the remaining stock using two browser tabs (sell the last units in tab 1, then try tab 2): tab 2 shows an "Only N units..." error and nothing is saved.
- [ ] Click the browser Back button after saving and try to resubmit: the form reloads fresh (no duplicate invoice).
- [ ] Trigger a validation failure on the server (for example, change the stock of an item in another tab, then save): the cart is restored with the error message.

### Invoice history
- [ ] Invoices page lists the 3 sample invoices plus yours, newest first.
- [ ] Search "INV-20261008" and search "priya": correct results.
- [ ] Date filter: set From/To around 2026-10-11 and only that invoice shows. From later than To: dates are swapped with a notice.
- [ ] Open an invoice: product list, quantities, unit prices, line totals, subtotal, discount (with % label) and grand total are correct.
- [ ] No edit or delete controls exist for invoices.

### Printing
- [ ] On an invoice page click Print: the preview shows only the invoice sheet (no sidebar, top bar, buttons or footer).
- [ ] From the invoice list click the printer icon: a new tab opens and the print dialog appears.

### Dashboard and reports
- [ ] Dashboard cards show total products (16 + any you added), total invoices, total sales and low-stock count. Numbers change after you create a sale.
- [ ] Low-stock list shows products with stock 10 or fewer, lowest first.
- [ ] Reports: default range is the last 30 days. Change the dates and Apply: cards, chart, "Sales by day" and "Best-selling products" update.
- [ ] A range with no sales (for example in the future/past): empty-state messages instead of the chart and tables.
- [ ] Quick buttons (Today, Last 7 days, Last 30 days, This month) work.

### Responsive layout
- [ ] Resize the browser below 992px: the sidebar hides and the hamburger button opens it. Clicking the dark backdrop or pressing Esc closes it.
- [ ] The active menu item is highlighted on every page.

## 7. Troubleshooting
| Problem | Fix |
|---|---|
| Apache won't start (port 80 in use) | Another program (Skype, IIS, another web server) is using port 80. Stop it, or change Apache's port in XAMPP (Config > httpd.conf, `Listen 8080`) and browse to `http://localhost:8080/smartpos/`. |
| MySQL won't start (port 3306 in use) | Stop other MySQL services in Windows Services, or change the port in `my.ini` and set `DB_PORT` in `config/database.php`. |
| "Something went wrong" page | Open `storage/logs/app.log`; the last lines show the real error. |
| `SQLSTATE[HY000] [1049] Unknown database` | `database.sql` was not imported. Repeat setup step 5. |
| `SQLSTATE[HY000] [1045] Access denied` | Wrong `DB_USER`/`DB_PASS` in `config/database.php`. |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL is not running. Start it in the XAMPP Control Panel. |
| Import fails with a syntax error near `CHECK` or `ON UPDATE` | Your server is too old or is MariaDB. Update XAMPP to a build with MySQL 8, or MariaDB 10.5+ (the script usually works there too). |
| Pages are unstyled, no icons, or no chart | The CDN is blocked or you are offline. Connect to the internet. |
| 404 on every page, or all links lead to a wrong address | The folder is not named `smartpos`, or `BASE_URL` in `config/config.php` does not match the folder name. |
| "Security token invalid or expired" | The session expired or cookies are blocked. Reload the page and try again. |
| "Parse error ... unexpected" / `never` return type errors | PHP is older than 8.1. Check with `php -v` and upgrade XAMPP. |
| Invoice time is off by a few hours | Set `date.timezone` in `php.ini` and make sure the Windows clock/timezone is correct. |
| Search or pages show stale data | Hard-refresh with Ctrl+F5. |

## 8. Requirements checklist
- [x] Core PHP 8.1+, MySQL, Bootstrap 5, JavaScript/AJAX, Bootstrap Icons, XAMPP-ready
- [x] `smartpos_db` with `products`, `invoices`, `invoice_items`, keys, indexes, timestamps, sample data
- [x] Product CRUD: list, add, view, edit, delete with confirm, validation, search, pagination, duplicate handling
- [x] Products used on invoices cannot be deleted (clear message; DB foreign key as backstop)
- [x] POS: static customer dropdown (6 customers), AJAX search by name/brand, stock display
- [x] Cart without duplicate lines, quantity edit, remove, line totals, subtotal, stock validation
- [x] Percentage and fixed discounts with validation; live totals; total never negative
- [x] Server recalculates prices and totals from the database; client totals are never trusted
- [x] Transaction: invoice + items + stock update, rollback on failure, locked rows to prevent overselling
- [x] Unique invoice numbers, success message, redirect to invoice page
- [x] Prevents empty carts, invalid customers/quantities, duplicate submits, negative totals
- [x] Invoice list with search by number/customer and date filter; detail page; print stylesheet; no editing
- [x] Dashboard + reports: database-driven cards, date-filtered report, best sellers, Chart.js with empty states
- [x] Responsive sidebar layout, breadcrumbs, active menu state, empty states, confirm dialogs, labelled forms
- [x] PDO prepared statements, `htmlspecialchars` output escaping, CSRF tokens, POST-only writes, secure session cookie settings, no raw errors shown, error log
