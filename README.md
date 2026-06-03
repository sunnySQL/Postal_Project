# Postal Pro

Postal Pro is a comprehensive postal service management system built with PHP and MySQL. This application provides a complete solution for managing a postal service operation, including package tracking, inventory management, customer support, and logistical operations.

## System Features

### Multi-Role User System
- **Customer Portal**: Send packages, track shipments, create support tickets, and manage payment history
- **Employee Dashboard**: Role-specific functionality for clerks, drivers, pilots, and customer support staff
- **Admin Controls**: Complete system management including user administration, reporting, and configuration

### Core Functionality
- **Package Management**: Create, track, and manage shipments through the entire delivery lifecycle
- **Inventory System**: Track stock levels in postal shops with low-stock alerts and sales processing
- **Logistics Management**: Coordinate trips between facilities with package assignment and tracking
- **Support Ticketing**: Handle customer inquiries and resolve shipping issues
- **Shop Management**: Process sales, manage inventory, and generate sales reports
- **Facility Tracking**: Track packages across different types of facilities (hubs, post offices, airports)

## Development Environment Setup

1. Download and install XAMPP from [https://www.apachefriends.org/index.html](https://www.apachefriends.org/index.html).
2. Start the Apache and MySQL modules from the XAMPP control panel.
3. Clone this repository to your local machine.

## Database Setup

1. Open phpMyAdmin at [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/).
2. Create a new database named `postal`.
3. Import `Schema.sql` to create the tables.

## Local Configuration (required)

These files are **not** in the repository. Create them after cloning:

1. **Database connection**
   ```bash
   cp db_connect.example.php db_connect.php
   ```
   Edit `db_connect.php` with your MySQL username and password.

2. **Optional — enable debug output locally**
   ```bash
   cp config.local.php.example config.local.php
   ```
   With `debug => true`, PHP errors display in the browser. Leave disabled (`false` or omit the file) on any shared or production server.

3. Create a MySQL user with access to the `postal` database and use those credentials in `db_connect.php`.

## Running the Project Locally

1. Place the project in your XAMPP `htdocs` directory.
2. Ensure `db_connect.php` and (optionally) `config.local.php` exist.
3. Open [http://localhost/postal/](http://localhost/postal/) (adjust path if needed).

## Security Notes

- Never commit `db_connect.php`, `config.local.php`, or database dump files.
- Use `Schema.sql` for fresh installs; do not commit full database exports.
- If secrets were previously pushed to GitHub, rotate database passwords and force-push a cleaned history or open a new repository.
