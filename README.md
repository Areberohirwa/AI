# Learning Platform

A web application where instructors can create servers and upload lessons, and students can subscribe to these servers to access the content.

## Features

- User authentication (instructors and students)
- Server creation and management for instructors
- Lesson upload and management
- Subscription system with Stripe payment integration
- Review system for lessons
- User profiles and statistics

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer
- Stripe account and API keys

## Setup Instructions

1. Clone the repository:
```bash
git clone <repository-url>
cd learning-platform
```

2. Install dependencies:
```bash
composer install
```

3. Create a MySQL database and import the schema:
```bash
mysql -u root -p < database.sql
```

4. Configure your database connection in `config/database.php`

5. Set up your Stripe API keys:
   - Sign up for a Stripe account at https://stripe.com
   - Get your API keys from the Stripe Dashboard
   - Update the Stripe API keys in `subscribe.php`

6. Create an uploads directory for lesson files:
```bash
mkdir -p uploads/lessons
chmod 777 uploads/lessons
```

7. Configure your web server (Apache/Nginx) to point to the project directory

8. Access the application through your web browser

## Directory Structure

```
learning-platform/
├── config/
│   └── database.php
├── uploads/
│   └── lessons/
├── vendor/
├── assets/
│   └── css/
├── database.sql
├── composer.json
├── index.php
├── login.php
├── register.php
├── dashboard.php
├── create_server.php
├── manage_server.php
├── browse_servers.php
├── subscribe.php
├── view_server.php
└── logout.php
```

## Security Notes

- Make sure to keep your Stripe API keys secure
- Set up proper file permissions for the uploads directory
- Use HTTPS in production
- Implement proper input validation and sanitization
- Use prepared statements for all database queries

## License

This project is licensed under the MIT License - see the LICENSE file for details.
