# Lakshmi Finance - Gold Finance Management System

A professional web-based gold finance management system built with PHP, MySQL, HTML, CSS, and JavaScript. This system is designed to manage jewelry pawn loans, customers, interest calculations, and financial transactions.

## Features

### 🏠 Dashboard
- Summary cards showing customer count, active loans, and recoveries
- Detailed jewelry pawn information table
- Search and filter functionality

### 👥 Customer Management
- Add, edit, and delete customers
- Search customers by name, mobile number, or customer number
- Pagination support

### 💰 Loan Management
- Create new jewelry pawn loans
- Track loan details including principal amount, interest rate, and pledge items
- Status tracking (active/closed)
- Tamil language support for status indicators

### 📊 Master Data
- **Groups**: Manage jewelry categories
- **Products**: Manage jewelry products with English and Tamil names

### 💸 Interest & Closing
- Interest calculation and tracking
- Loan closing functionality
- Interest payment records

### 📈 Reports
- Balance Sheet
- Day Book
- Advance Report
- Pledge Report

### 🔄 Transactions
- Credit and debit transaction entries
- Transaction history tracking

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **UI Framework**: Custom CSS with Font Awesome icons
- **Authentication**: Session-based PHP authentication

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- PHP extensions: PDO, PDO_MySQL

### Setup Instructions

1. **Clone or Download the Project**
   ```bash
   # If using git
   git clone <repository-url>
   cd lakshmi-finance
   ```

2. **Configure Database**
   - Edit `config/database.php`
   - Update database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'lakshmi_finance');
     define('DB_USER', 'your_username');
     define('DB_PASS', 'your_password');
     ```

3. **Initialize Database**
   - Open your web browser
   - Navigate to: `http://your-domain/init.php`
   - This will create the database and sample data

4. **Access the Application**
   - Navigate to: `http://your-domain/`
   - Login with default credentials:
     - **Username**: admin
     - **Password**: admin123

## Database Schema

### Core Tables
- **users**: System users and authentication
- **customers**: Customer information
- **loans**: Jewelry pawn loan records
- **interest**: Interest payment records
- **loan_closings**: Loan closure records
- **transactions**: Financial transactions
- **groups**: Jewelry categories
- **products**: Jewelry products with bilingual names

## File Structure

```
lakshmi-finance/
├── index.php                 # Login page
├── dashboard.php             # Main dashboard
├── init.php                  # Database initialization
├── config/
│   └── database.php         # Database configuration
├── auth/
│   ├── login.php            # Login handler
│   └── logout.php           # Logout handler
├── api/
│   ├── customers.php        # Customer API
│   ├── loans.php            # Loan API
│   ├── groups.php           # Group API
│   └── products.php         # Product API
├── pages/
│   ├── dashboard.php        # Dashboard content
│   ├── customers.php        # Customer management
│   ├── loans.php            # Loan management
│   ├── groups.php           # Group management
│   └── products.php         # Product management
├── assets/
│   ├── css/
│   │   └── style.css        # Main stylesheet
│   └── js/
│       ├── login.js         # Login page JavaScript
│       └── dashboard.js     # Dashboard JavaScript
└── README.md                # This file
```

## Features in Detail

### Authentication System
- Secure login with password hashing
- Session-based authentication
- Role-based access control

### Responsive Design
- Mobile-friendly interface
- Clean, professional design
- Consistent color scheme (blue, green, white)

### Search & Filter
- Real-time search functionality
- Pagination for large datasets
- Advanced filtering options

### Data Management
- CRUD operations for all entities
- Data validation and sanitization
- Foreign key constraints for data integrity

### Multi-language Support
- English interface
- Tamil language support for status indicators
- Bilingual product names

## Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection prevention with prepared statements
- XSS protection with `htmlspecialchars()`
- Session security
- Input validation and sanitization

## Customization

### Branding
- Change company name from "Lakshmi Finance" in:
  - `index.php` (login page)
  - `dashboard.php` (sidebar)
  - `assets/css/style.css` (styling)

### Colors
- Primary blue: `#2196F3`
- Success green: `#4CAF50`
- Warning orange: `#FF9800`
- Update colors in `assets/css/style.css`

### Database
- Modify `config/database.php` for different database settings
- Add new tables in `init.php` initialization function

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials in `config/database.php`
   - Ensure MySQL service is running
   - Verify database exists

2. **Page Not Found Errors**
   - Check web server configuration
   - Ensure PHP is properly configured
   - Verify file permissions

3. **Login Issues**
   - Run `init.php` to create default admin user
   - Check session configuration
   - Verify PHP session directory permissions

### Performance Tips

- Enable PHP OPcache for better performance
- Configure MySQL query cache
- Use CDN for Font Awesome icons
- Optimize images and assets

## Support

For technical support or feature requests, please contact the development team.

## License

This project is proprietary software. All rights reserved.

---

**Lakshmi Finance** - Professional Gold Finance Management System 