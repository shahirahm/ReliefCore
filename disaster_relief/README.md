# Disaster Relief Camp & Volunteer Coordination System - PHP Version

## What is included

- PHP pages
- MySQL database connection
- Login/register system
- Admin approval system
- Camp manager forms
- Volunteer task status update
- Donor donation forms
- Affected person help request forms
- Simple chat system
- Success/error popup alerts
- Shared CSS and JS

## Folder Structure

```
disaster_relief_php/
  public/
    index.php
    login.php
    register.php
    admin.php
    camp-manager.php
    volunteer.php
    donor.php
    affected-person.php
    guest.php
    chat.php
    process_*.php
    create_admin.php
    css/
    js/
  config/
    database.php
  includes/
    header.php
    footer.php
    auth.php
    functions.php
  db/
    disaster_relief_db.sql
```

## Setup Steps in XAMPP / WAMP

1. Copy the `disaster_relief_php` folder into your `htdocs` folder.
2. Start Apache and MySQL.
3. Open phpMyAdmin.
4. Import `db/disaster_relief_db.sql`.
5. Edit `config/database.php` if your MySQL username/password is different.
6. Open:

```
http://localhost/disaster_relief_php/public/create_admin.php
```

7. Then login:

```
Email: admin@relief.com
Password: admin123
```

8. Open:

```
http://localhost/disaster_relief_php/public/index.php
```

## Important Notes

- This is a course-project-friendly PHP + MySQL version.
- It is intentionally simple and readable.
- Passwords are hashed using `password_hash()`.
- Most role dashboards are functional enough for demonstration.
- PDF generation buttons can be added later using libraries like TCPDF or FPDF.


## Emergency Theme Update

This version updates the design to look more like an emergency/disaster relief website:
- White navbar
- Red emergency color scheme
- Disaster hero section
- Live situation card
- Homepage stats
- Active camp cards
- Role cards and CTA sections


## DB Accurate Stats Update

Homepage and guest page now use real database values:
- Active camps count comes from `relief_camps`
- Registered families comes from `affected_families`
- People supported comes from `SUM(affected_families.total_members)`
- Approved volunteers comes from `users` joined with `roles`
- Money donation total comes from `SUM(donations.amount)`
- Supply donation count comes from `donations`
- Stock percentage is calculated from `camp_stock` as:
  `SUM(quantity) / SUM(minimum_required) * 100`

If a camp has no stock records or the minimum required value is zero, the stock level shows `Not Updated`.


## Navbar and Camp Update Fix

- Public menu now follows the screenshot:
  Home, About, Roles, Features, Active Camps, Donate, Contact, Login, Sign Up.
- After login, a Dashboard link appears based on the logged-in user's role.
- Admin now has a Relief Camps submenu.
- Camps can now be added from Admin > Relief Camps.
- Public homepage and guest page show camps with status Active, Standby, Ongoing, blank, or NULL.
- Closed camps are hidden from the public homepage and guest page.


## Navbar Update

- Removed `Roles` from the public menu bar.
