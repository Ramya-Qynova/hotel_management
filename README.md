# Hotel Management System (HMS)

A web-based Hotel Management System designed to manage daily hotel operations through a centralized web application. The platform streamlines room management, walk-in and online reservations, guest registration, billing, payments, check-in/check-out, additional services, and operational reports.

---

## 1. Technology Stack

### Frontend
- **Framework**: React 19
- **Build Tool**: Vite 8
- **Language**: JavaScript (ESModules)
- **Routing**: React Router DOM (v7)
- **HTTP Client**: Axios
- **Default Port**: `5173` (`http://localhost:5173`)

### Backend
- **Framework**: Laravel 12.x
- **Language**: PHP 8.2+ / PHP 8.3+
- **API Architecture**: REST API (`/api/*`)
- **Authentication**: Laravel Sanctum (configured for future token/cookie auth)
- **CORS**: Configured for `http://localhost:5173`
- **Default Port**: `8000` (`http://127.0.0.1:8000`)

### Database
- **Engine**: MySQL Server 8.0
- **Host**: `127.0.0.1`
- **Port**: `3306`
- **Database Name**: `hotel_management`

---

## 2. Project Directory Structure

```
hotel-management/
│
├── frontend/                               # React + Vite application
│   ├── src/
│   │   ├── api/                            # API services & Axios client
│   │   │   ├── axios.js                    # Configured Axios instance with interceptors
│   │   │   └── healthService.js            # Health check service call
│   │   ├── components/
│   │   │   └── layout/                     # Reusable layout components (Navbar, Footer, MainLayout)
│   │   ├── pages/                          # Page views (HomePage, NotFoundPage)
│   │   ├── router/                         # Client-side routing (AppRouter)
│   │   ├── App.jsx                         # Main application component & health status manager
│   │   ├── main.jsx                        # React root entry point
│   │   └── index.css                       # Responsive UI design system styles
│   ├── .env                                # Frontend environment variables (VITE_API_BASE_URL)
│   ├── .env.example                        # Template for frontend environment
│   └── package.json
│
├── backend/                                # Laravel application
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Requests/
│   │   │   ├── Resources/
│   │   │   └── Middleware/
│   │   ├── Models/                         # Eloquent Models (User, etc.)
│   │   ├── Services/                       # Business logic services
│   │   ├── Policies/                       # Authorization policies
│   │   ├── Enums/                          # Domain enums
│   │   ├── Events/                         # Event definitions
│   │   ├── Listeners/                      # Event listeners
│   │   └── Jobs/                           # Asynchronous jobs
│   ├── config/
│   │   ├── cors.php                        # CORS configuration allowing http://localhost:5173
│   │   └── database.php                    # MySQL & connection configurations
│   ├── database/
│   │   └── migrations/                     # Laravel database migrations
│   ├── routes/
│   │   ├── api.php                         # REST API routes (including /api/health)
│   │   └── web.php
│   ├── .env                                # Local environment secrets (excluded from git)
│   ├── .env.example                        # Environment blueprint
│   └── composer.json
│
├── docs/                                   # Project documentation directory
├── HOTEL MANAGEMENT SYSTEM DOCUMENTATION.md # Core functional & technical specification
├── .gitignore                              # Git exclusion rules
└── README.md                               # Project setup and developer guide
```

---

## 3. Prerequisites

Before running the application, make sure you have:

- **PHP**: PHP 8.2 or 8.3+ with `pdo_mysql`, `mbstring`, `openssl`, `curl`, `intl` extensions enabled.
- **Composer**: Composer 2.x
- **Node.js**: Node.js v18+ (verified on v22.19.0) and npm 10+
- **MySQL**: Existing MySQL Server 8.0 running on `127.0.0.1:3306`
- **Git**: Git version control

---

## 4. Environment Configuration

### Frontend (`frontend/.env`)
Create or edit `frontend/.env`:
```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

### Backend (`backend/.env`)
Copy `backend/.env.example` to `backend/.env` if not present:
```bash
cp backend/.env.example backend/.env
```

Configure your local MySQL credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hotel_management
DB_USERNAME=YOUR_MYSQL_USERNAME
DB_PASSWORD=YOUR_MYSQL_PASSWORD

FRONTEND_URL=http://localhost:5173
```

---

## 5. Database Setup & Migrations

1. Ensure MySQL Server 8.0 is running on port 3306.
2. Create the `hotel_management` database if it does not already exist:
   ```sql
   CREATE DATABASE IF NOT EXISTS hotel_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Run the default Laravel migrations from the `backend/` folder:
   ```bash
   cd backend
   php artisan migrate
   ```

---

## 6. Running the Development Environment

### Start the Laravel Backend
From the root workspace, open a terminal:
```bash
cd backend
php artisan serve
```
Backend will start on: **`http://127.0.0.1:8000`**

### Start the React Frontend
In a separate terminal:
```bash
cd frontend
npm run dev
```
Frontend will start on: **`http://localhost:5173`**

---

## 7. API Health Check

The backend provides a health-check endpoint to verify API and CORS functionality:

### Endpoint
- **Method**: `GET`
- **URL**: `http://127.0.0.1:8000/api/health`

### Expected JSON Response
```json
{
  "success": true,
  "message": "Hotel Management API is running"
}
```

### React Health Status Indicator
When visiting `http://localhost:5173`, the React application calls this endpoint via Axios and displays real-time connection status (`Connected`, `Loading`, or `Disconnected` with detailed troubleshooting if offline).

