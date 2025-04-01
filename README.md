# Sm4rt W4tch - Health Analytics Platform

A modern health and activity tracking application with AI-powered insights and analytics.

## Overview

Sm4rt W4tch is a full-stack web application that helps users track, analyze, and gain insights from their health and activity data. The platform uses Laravel for the backend API, React for the frontend interface, and integrates with a local AI service to provide personalized health insights and recommendations.

## Features

- **Activity Tracking**: Record and monitor steps, active minutes, and distance
- **CSV Data Import**: Import activity data from CSV files
- **AI-Powered Insights**: Get personalized health insights based on activity data
- **AI Predictions**: Receive activity predictions and anomaly detection
- **Historical Analysis**: View trends and patterns in your activity data
- **Responsive Dashboard**: Modern, mobile-friendly user interface

## Tech Stack

- **Backend**: Laravel 10 (PHP)
- **Frontend**: React with Redux
- **AI Service**: Local AI model served via REST API
- **Database**: MySQL
- **Authentication**: JWT-based authentication

## Installation

### Prerequisites

- PHP 8.1+
- Composer
- Node.js and npm
- MySQL
- Local AI service (LM Studio or similar)

### Backend Setup

1. Navigate to the server directory:

   ```bash
   cd sm_server
   ```

2. Install PHP dependencies:

   ```bash
   composer install
   ```

3. Create a `.env` file:

   ```bash
   cp .env.example .env
   ```

4. Configure your database and AI service in `.env`:

   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=sm4rt_w4tch
   DB_USERNAME=root
   DB_PASSWORD=

   AI_ENDPOINT=http://localhost:1234
   AI_MODEL=deepseek-r1-distill-qwen-7b
   AI_TIMEOUT=20
   ```

5. Generate application key:

   ```bash
   php artisan key:generate
   ```

6. Run database migrations:

   ```bash
   php artisan migrate
   ```

7. Start the Laravel server:
   ```bash
   php artisan serve
   ```

### Frontend Setup

1. Navigate to the frontend directory:

   ```bash
   cd sm_frontend
   ```

2. Install JavaScript dependencies:

   ```bash
   npm install
   ```

3. Start the development server:
   ```bash
   npm run dev
   ```

## AI Service Setup

1. Install LM Studio or similar local AI serving solution
2. Load a suitable model (recommended: deepseek-r1-distill-qwen-7b)
3. Start the server on http://localhost:1234 (or update the AI_ENDPOINT in `.env`)
4. Test the connection using the included test script:
   ```
   http://localhost:8000/ai_connection_test.php
   ```

## API Documentation

The application provides the following API endpoints:

### Authentication

- `POST /api/auth/register` - Register a new user
- `POST /api/auth/login` - Login and get JWT token
- `POST /api/auth/logout` - Logout and invalidate token
- `GET /api/auth/me` - Get current user data

### Activity Data

- `GET /api/activity-metrics` - Get all activity metrics
- `GET /api/activity-metrics/daily` - Get daily activity metrics
- `GET /api/activity-metrics/weekly` - Get weekly activity metrics
- `GET /api/activity-metrics/monthly` - Get monthly activity metrics
- `POST /api/csv-uploads` - Upload activity data CSV

### AI Features

- `GET /api/ai/test` - Test AI service connection
- `POST /api/ai/insights` - Get AI insights about activity data
- `POST /api/ai/predict` - Get AI predictions based on activity history

## Testing

The project includes a Postman collection for API testing. Import the `Sm4rt_W4tch_API_Tests.postman_collection.json` file into Postman to access the test suite.

### Running Tests

1. Set up environment variables in Postman:

   - `api_url`: API base URL (default: http://localhost:8000/api)
   - `user_email`: Test user email
   - `user_password`: Test user password

2. Run the Authentication flow first to get a valid token
3. Run individual test requests or the entire collection

## Troubleshooting

### AI Connection Issues

- Check if the AI service is running on the configured endpoint
- Verify that the AI model is loaded correctly
- Increase the AI_TIMEOUT value in the `.env` file if requests are timing out
- Use the AI connection test script to diagnose specific issues

### Data Format Issues

- Ensure activity metrics are provided in the correct format:
  ```json
  {
    "daily_steps": 8542,
    "active_minutes": 35,
    "distance": 6.83
  }
  ```

## Data Import

To import activity data, ensure you have a file named `smrt.csv` in the `public` directory. The file should be formatted as follows:

```
user_id,date,steps,distance_km,active_minutes
12,2025-01-01,17159,13.08,126
12,2025-01-02,15792,12.04,34
12,2025-01-03,14956,11.4,144
12,2025-01-04,13972,10.65,61
12,2025-01-05,14214,10.83,92
12,2025-01-06,4379,3.34,148
12,2025-01-07,6738,5.14,44
12,2025-01-08,1481,1.13,146
12,2025-01-09,11352,8.65,91
12,2025-01-10,3910,2.98,25
12,2025-01-11,10592,8.07,146
12,2025-01-12,8325,6.35,138
12,2025-01-13,1389,1.06,162
12,2025-01-14,2091,1.59,177
12,2025-01-15,12739,9.71,174
12,2025-03-28,13378,10.2,134
12,2025-03-30,17788,13.56,26
12,2025-03-31,1755,1.34,57
```

This CSV file will be used to import activity data into the application.

## License

This project is tottally legit under the my guidance - see the LICENSE file for details :) .
