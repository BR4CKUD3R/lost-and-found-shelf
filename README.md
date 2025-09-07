# Lost & Found Shelf System - MariaDB Edition

A comprehensive PHP/MariaDB web application for managing lost and found items in communities. Optimized for **MariaDB 9.0.1** with enhanced performance and modern UI.

## 🚀 Features

### Core Functionality
- **User Authentication**: Secure registration and login with enhanced validation
- **Item Management**: Report lost/found items with detailed descriptions and images
- **Advanced Search**: Filter items by category, location, status, and keywords
- **3-Question Claim System**: Comprehensive verification process for item claims
- **File Upload System**: Secure image upload with validation and display
- **Real-time Notifications**: In-app notification system for claims and updates
- **User Feedback**: Star rating and review system for user interactions
- **Reward System**: Database-ready bounty and reward management

### Enhanced UI/UX
- **Modern Design**: Bootstrap 5 with custom CSS and Font Awesome icons
- **Responsive Layout**: Mobile-first design for all devices
- **Interactive Dashboard**: Statistics cards and real-time updates
- **Gradient Themes**: Modern color schemes and animations
- **Password Strength**: Real-time password validation during registration
- **Loading States**: Smooth transitions and loading indicators

### MariaDB Optimizations
- **VARCHAR(191)**: Optimized for utf8mb4 charset to prevent key length errors
- **ENUM Types**: Efficient storage for status and category fields
- **Proper Indexing**: Optimized database indexes for better performance
- **Foreign Keys**: Referential integrity with CASCADE options
- **UTF8MB4 Support**: Full Unicode support including emojis

## 📋 Installation Guide for WampServer

### Prerequisites
- **WampServer** with MariaDB 9.0.1
- **PHP 7.4+** (included with WampServer)
- **Web Browser** (Chrome, Firefox, Safari, Edge)

### Step-by-Step Setup

1. **Extract Files**
   - Extract the ZIP file to `C:\wamp64\www\lost-found-system\`

2. **Start WampServer**
   - Launch WampServer and wait for green icon
   - Ensure Apache and MariaDB services are running

3. **Create Database**
   - Click WampServer icon → phpMyAdmin
   - Login (user: `root`, password: blank)
   - Create database: `lost_found_db`
   - Select `utf8mb4_unicode_ci` collation

4. **Configure Connection**
   - File: `config/database.php`
   - Default settings should work:
     ```php
     private $host = "localhost";
     private $db_name = "lost_found_db";
     private $username = "root";
     private $password = "";
     ```

5. **Run Setup Script**
   - Visit: `http://localhost/lost-found-system/setup_database.php`
   - Wait for "Database Setup Completed Successfully!" message
   - Note the demo credentials provided

6. **Access Application**
   - Go to: `http://localhost/lost-found-system/`
   - Login with demo credentials:
     - **Admin**: admin@lostandfound.com / admin123
     - **User**: user@lostandfound.com / user123

## 🗄️ Database Schema

### Core Tables (MariaDB Optimized)
- **User**: Authentication and profile management
- **Item**: Lost/found item records with full details
- **Category**: Item categorization system
- **Location**: Physical location management
- **Claim**: Item claim requests with verification
- **Verification_Question**: 3-question claim validation
- **Attachment**: File upload and image management
- **Notification**: Real-time user notifications
- **Feedback**: User rating and review system
- **Reward**: Bounty and reward management
- **Conversation**: Messaging system foundation

### Key Improvements for MariaDB 9.0.1
- **Optimized VARCHAR lengths** to prevent index issues
- **ENUM types** for better performance and data integrity
- **Proper charset settings** (utf8mb4_unicode_ci)
- **Efficient indexing** on frequently queried columns
- **Foreign key constraints** with appropriate CASCADE rules

## 🔧 Features Overview

### User Registration & Authentication
- Password strength indicator
- Secure password hashing with PHP's password_hash()
- Email and phone uniqueness validation
- Auto-login after successful registration

### Item Management
- **Report Lost Items**: Comprehensive form with image upload
- **Report Found Items**: Detailed reporting with location tracking
- **Image Upload**: Secure file validation and storage
- **Status Tracking**: Reported → Claimed → Rejected → Donated
- **Priority Levels**: Low, Medium, High priority classification
- **Expiration System**: 30-day automatic expiration

### Search & Browse
- **Advanced Filters**: Category, location, status, keyword search
- **Responsive Grid**: Card-based layout
- **Image Thumbnails**: Image resizing and display
- **Sorting Options**: Date, priority, category sorting

### Claim System
- **3-Question Verification**: Comprehensive ownership validation
- **Admin Approval (Optional)**: Manual review and approval process
- **Notification System**: Real-time updates for all parties
- **Status Tracking**: Pending → Approved/Rejected workflow
- **Evidence Collection**: Detailed verification questions

### Dashboard & Analytics
- **Statistics Cards**: Active items, user items, claims, notifications
- **Recent Activity**: Latest items and updates
- **Quick Actions**: Fast access to common tasks
- **Notification Center**: Unread message tracking
- **User Profile**: Account management and settings

## 🎨 UI/UX Features

### Modern Design Elements
- **CSS Variables**: Consistent theming system
- **Gradient Backgrounds**: Modern visual appeal
- **Font Awesome Icons**: Comprehensive icon library
- **Bootstrap 5**: Latest responsive framework
- **Custom Animations**: Smooth transitions and hover effects with the help of artifical intelligence

### Interactive Components
- **Password Toggle**: Show/hide password functionality
- **Real-time Validation**: Instant form feedback
- **Loading States**: Progress indicators for operations
- **Toast Notifications**: Non-intrusive user feedback
- **Modal Dialogs**: Clean popup interfaces

### Responsive Design
- **Mobile First**: Optimized for mobile devices
- **Tablet Support**: Perfect for tablet browsing
- **Desktop Enhanced**: Full-featured desktop experience
- **Touch Friendly**: Large touch targets and gestures

## 🔒 Security Features

### Data Protection
- **SQL Injection Prevention**: PDO prepared statements throughout
- **File Upload Security**: Type validation and secure storage
- **Session Management**: Proper session handling and timeout
- **Password Security**: Strong hashing

### Access Control
- **Authentication Gates**: Protected page access
- **Cross-Site Request Forgery Protection**: Form token validation ready
- **Input Validation**: Server-side validation for all inputs
- **Secure Headers**: Security header implementation ready

## 🚀 Performance Optimizations

### Database Performance
- **Optimized Queries**: Efficient JOIN operations
- **Proper Indexing**: Strategic index placement
- **Connection Pooling**: Efficient database connections
- **Query Caching**: MariaDB query cache utilization
- **Minimal Queries**: Reduced database calls per page

### Frontend Performance
- **CSS Optimization**: Minified and compressed styles
- **Image Optimization**: Responsive image loading
- **Lazy Loading**: Progressive content loading
- **Caching Headers**: Browser cache optimization
- **Compression Ready**: Gzip compression support

## 📱 Mobile Features

### Touch-Optimized Interface
- **Large Touch Targets**: Easy finger navigation
- **Swipe Gestures**: Intuitive mobile interactions
- **Responsive Images**: Adaptive image sizing
- **Mobile Navigation**: Collapsible menu system
- **Fast Loading**: Optimized for mobile networks

## 🔧 Customization Options

### Easy Configuration
- **Theme Colors**: CSS variable customization
- **Logo Replacement**: Simple branding updates
- **Email Templates**: Customizable notification emails
- **Category Management**: Add/remove item categories
- **Location Management**: Flexible location system

### Extension Points
- **Email Integration**: SMTP configuration ready
- **SMS Notifications**: Twilio integration ready
- **Social Login**: OAuth integration ready
- **Payment System**: Reward payment integration ready
- **Mobile App API**: REST API foundation included

## 🛠️ Troubleshooting

### Common Issues & Solutions

**Database Connection Error**
- Verify MariaDB is running in WampServer
- Check database name and credentials
- Ensure `lost_found_db` database exists

**Image Upload Issues**
- Check `uploads/` folder permissions
- Verify PHP `upload_max_filesize` setting

**"Key Too Long" Error (Resolved)**
- This project uses VARCHAR(191) for indexed columns
- Optimized for MariaDB 11.5.2 utf8mb4 charset
- No manual configuration needed

**Session/Login Issues**
- Clear browser cookies and cache
- Check PHP session configuration
- Verify session folder permissions

## Support & Development

### Development Information
- **PHP Version**: 7.4+ recommended, 8.x compatible
- **MariaDB Version**: 9.0.1 optimized
- **Framework**: PHP with Bootstrap 5
- **Architecture**: MVC-inspired structure
- **Coding Standards**: PSR-12 compliant

## 📄 License

Open source under MIT License - free for educational and commercial use.

---

