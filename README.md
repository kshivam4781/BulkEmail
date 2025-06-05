# Bulk Email Campaign System

A PHP-based bulk email campaign management system that allows users to create, schedule, and send email campaigns to multiple recipients.

## Features

- Create and manage email campaigns
- Schedule emails for future delivery
- Support for CC and BCC recipients
- Email tracking capabilities
- Draft saving functionality
- Personalization support
- Excel file import for recipient lists

## Requirements

- PHP 7.4 or higher
- MySQL/MariaDB
- XAMPP (or similar local development environment)
- SMTP server access

## Installation

1. Clone the repository:
```bash
git clone [your-repository-url]
```

2. Set up your database:
   - Create a new database in MySQL
   - Import the database schema (if provided)

3. Configure the database connection:
   - Copy `config/database.example.php` to `config/database.php`
   - Update the database credentials in `config/database.php`

4. Configure your web server:
   - Point your web server to the project directory
   - Ensure the web server has write permissions for logs and uploads

## Usage

1. Access the application through your web browser
2. Log in with your credentials
3. Create a new campaign or load a draft
4. Upload recipient list or select from existing lists
5. Compose your email
6. Schedule or send immediately

## Security

- All sensitive configuration files are excluded from version control
- Passwords are hashed
- Input validation and sanitization implemented
- Session-based authentication

## License

[Your chosen license]

## Contributing

[Your contribution guidelines] 