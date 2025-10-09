# Pimono Wallet (PNMO)

A modern, secure digital wallet application built with Laravel and Vue.js that enables users to send money, manage balances, and track transaction history with real-time updates.

## 🚀 Features

### Core Functionality
- **User Authentication**: Secure registration, login, and logout with Laravel Sanctum
- **Digital Wallet**: Manage account balances with decimal precision
- **Money Transfers**: Send money between users with commission fee calculation (1.5%)
- **Transaction History**: View detailed transaction records with sender/receiver information
- **Real-time Updates**: Live transaction notifications using Laravel Echo and Pusher
- **Responsive Dashboard**: Modern, mobile-friendly interface built with Vue.js and Tailwind CSS

### Security Features
- **Rate Limiting**: API rate limiting for transaction endpoints (5 transfers per minute per user)
- **Input Sanitization**: Custom middleware for sanitizing user inputs
- **Security Headers**: Enhanced HTTP security headers
- **CSRF Protection**: Laravel's built-in CSRF protection
- **Database Transactions**: Atomic operations to ensure data consistency
- **Audit Logging**: Comprehensive audit trail for security monitoring

### Performance Optimizations
- **Database Indexing**: Optimized indexes for transaction queries
- **Union Queries**: Efficient data retrieval for user transactions
- **Pagination**: Built-in pagination for large datasets
- **Caching**: Database-based caching for improved performance
- **Queue System**: Background job processing for time-intensive tasks

### Key Technologies
- **Backend**: Laravel 12, PHP 8.2+, SQLite/MySQL
- **Frontend**: Vue.js 3, Pinia, Vue Router, Tailwind CSS 4
- **Real-time**: Laravel Echo, Pusher
- **Build Tools**: Vite, Laravel Vite Plugin
- **Authentication**: Laravel Sanctum

## 💰 Transaction System

### Commission Structure
- **Commission Rate**: 1.5% on all transfers
- **Calculation**: Automatic commission calculation and deduction
- **Display**: Clear breakdown showing amount, commission, and total deducted

### Transaction Flow
1. **Validation**: Amount and receiver validation
2. **Balance Check**: Sufficient funds verification (including commission)
3. **Database Transaction**: Atomic balance updates for sender and receiver
4. **Logging**: Transaction record creation with unique reference number
5. **Real-time Notification**: Instant updates to both parties
6. **Audit Trail**: Security logging for all transaction events

### Transaction Status
- **Pending**: Initial transaction state
- **Completed**: Successfully processed transaction
- **Failed**: Transaction that could not be completed

### Code Quality
- **Laravel Pint**: PHP code formatting
- **PHPUnit**: Backend testing framework
- **ESLint**: JavaScript linting (configurable)
- **Prettier**: Code formatting (configurable)

## 🚀 Setup Instructions

Follow these step-by-step instructions to set up the Pimono Wallet application on your local development environment.

### Prerequisites

Ensure you have the following installed on your system:

- **PHP 8.2 or higher** with the following extensions:
    - BCMath PHP Extension
    - Ctype PHP Extension
    - cURL PHP Extension
    - DOM PHP Extension
    - Fileinfo PHP Extension
    - JSON PHP Extension
    - Mbstring PHP Extension
    - OpenSSL PHP Extension
    - PCRE PHP Extension
    - PDO PHP Extension
    - Tokenizer PHP Extension
    - XML PHP Extension
- **Composer** (latest version)
- **Node.js 18 or higher** and **npm**
- **MySQL 8.0+**

### Step 1: Clone and Navigate

```bash
# Navigate to the src directory
cd /path/to/your/project/src

# Or if cloning from repository:
# git clone <repository-url> project-name
# cd project-name/src
```

### Step 2: Install PHP Dependencies

```bash
# Install Laravel and PHP dependencies
composer install

# If you encounter memory issues:
composer install --no-dev --optimize-autoloader
```

### Step 3: Environment Configuration

```bash
# Copy the environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

Edit the `.env` file to configure your environment:

```env
APP_NAME="Pimono Wallet"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (SQLite is default - set to mysql; update DB credentials)
DB_CONNECTION=sqlite

# For MySQL (optional):
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=pnmo_wallet
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# Broadcasting (for real-time features)
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_app_key
PUSHER_APP_SECRET=your_pusher_app_secret
PUSHER_APP_CLUSTER=mt1

# Mail (for notifications)
MAIL_MAILER=smtp
MAIL_HOST=your_mail_host
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=noreply@pimono.app
MAIL_FROM_NAME="Pimono Wallet"
```

### Step 4: Database Setup

Create the database first:
```sql
CREATE DATABASE pnmo_wallet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
# Run database migrations
php artisan migrate

# Seed the database (optional - creates sample data)
php artisan db:seed
```

### Step 5: Install Frontend Dependencies

```bash
# Install JavaScript dependencies
npm install

# Build development assets
npm run dev
```

### Step 6: Storage and Cache Setup

```bash
# Create symbolic link for storage
php artisan storage:link

# Clear and cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set proper permissions (Linux/Mac)
chmod -R 775 storage bootstrap/cache
```

### Step 7: Start Development Servers

#### Option A: All Services at Once (Recommended)
```bash
# Start all development services simultaneously
composer dev
```
This will start:
- Laravel development server (http://localhost:8000)

#### Option B: Individual Services
```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Frontend development server
npm run dev

# Terminal 3: Queue worker (optional)
php artisan queue:listen
```

### Step 8: Create Initial Users

#### Option A: Use Seeder (if run in Step 4)
The seeder creates sample users with initial balances.

#### Option B: Manual Database Entry
```bash
php artisan tinker
```
```php
// Create users with initial balance
$user1 = App\Models\User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => bcrypt('password'),
    'balance' => 1000.00,
    'login_token' => \Str::random(32)
]);

$user2 = App\Models\User::create([
    'name' => 'Jane Smith',
    'email' => 'jane@example.com',
    'password' => bcrypt('password'),
    'balance' => 500.00,
    'login_token' => \Str::random(32)
]);
```

### Step 9: Verify Installation

1. **Visit the Application**: http://localhost:8000
2. **Login with Test Credentials**: Use registered user credentials
3. **Test Core Features**:
    - View dashboard and balance
    - Send money between users
    - Check transaction history
    - Verify real-time notifications

### Troubleshooting

#### Common Issues

**1. Permission Errors**
```bash
sudo chown -R $USER:$USER storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

**2. Database Connection Issues**
- Verify SQLite file exists: `ls -la database/database.sqlite`
- Check MySQL credentials in `.env`
- Run `php artisan migrate:status` to check migrations

**3. Frontend Build Issues**
```bash
# Clear npm cache
npm cache clean --force
rm -rf node_modules package-lock.json
npm install
```

**4. Vite Server Issues**
- Check if port 5173 is available
- Verify Vite configuration in `vite.config.js`
- Clear browser cache and restart Vite

**5. Real-time Features Not Working**
- Configure Pusher credentials in `.env`
- Verify WebSocket connection in browser dev tools
- Check Laravel Echo configuration in `resources/js/bootstrap.js`

The application should now be running at http://localhost:8000 with full functionality including real-time features, secure transactions, and responsive design.
