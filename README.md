# School HR and Academic Performance Management System

A comprehensive web-based system for the Academic Office and Human Resource Manager to monitor, evaluate, and report on teacher performance.

## Technology Stack

- **Database:** MySQL
- **Frontend:** Vue.js 3 + Tailwind CSS
- **Backend:** PHP (RESTful API)
- **Charts:** Chart.js

## Features

### 1. User Authentication and Roles
- Secure login with JWT authentication
- Role-based access control (Admin, Academic Office, HR Manager)
- Session management

### 2. Teacher Data Management
- Add, edit, delete teacher records
- Bulk import from CSV/JSON
- Fields: Full Name, Email, Contact, Subject, Class, Stream

### 3. Subject Teacher Performance Module
- Track performance per term
- Automatic calculations:
  - TC1 = EOT1 - BOT1
  - TC2 = EOT2 - EOT1
  - TC3 = EOT3 - EOT2
  - AGP = (TC1 + TC2 + TC3) / 3
- Automated comments based on TC values
- Visual performance tracking

### 4. Lesson Monitoring Module
- Weekly time lost tracking (Week 1-13)
- Automatic conversion:
  - Single lesson = 40 minutes
  - Double lesson = 80 minutes
- Summary statistics

### 5. Lesson Observation Module
- Two observation rounds per term
- Scores, areas of improvement, action points
- Automatic average calculation: AV = (Score 1 + Score 2) / 2
- Works for all three terms

### 6. Teachers' Duty Performance Module
- Evaluate on: Punctuality, Supervision, Cleanliness, Time Keeping, Participation
- Scores out of 100%
- Visual charts and leaderboards
- Teacher of the Week/Month/Year awards

### 7. Class Teacher Performance Module
- Weekly tracking: Roll Call, Mentorship, Devotion, Class Cleanliness (Week 1-13)
- Parent contact tracking
- Academic performance with automatic calculations:
  - C1 = T1 - BT1
  - C2 = T2 - T1
  - C3 = T3 - T2
  - Average = (C1 + C2 + C3) / 3
- Automated comments

### 8. Reports and Dashboard
- Summary dashboard with statistics
- Charts and visualizations
- Weekly, Termly, and Yearly reports
- Best Teacher awards tracking
- Printable reports

## Installation

### Prerequisites
- XAMPP or WAMP server
- PHP 7.4+
- MySQL 5.7+

### Setup Steps

1. **Clone/Extract the project** to your web server directory:
   ```
   c:/xampp/htdocs/Accademics/
   ```

2. **Create the database:**
   - Open phpMyAdmin
   - Import the database schema from `database/schema.sql`
   - This creates the database with sample data

3. **Default Login Credentials:**
   - Email: `admin@school.com`
   - Password: `admin123`

4. **Access the application:**
   ```
   http://localhost/Accademics/
   ```

## Database Schema

The system uses the following main tables:
- `users` - Authentication and roles
- `teachers` - Teacher information
- `subject_teacher_performance` - Subject performance tracking
- `lesson_monitoring` - Weekly lesson monitoring
- `lesson_observations` - Observation rounds
- `duty_performance` - Duty performance scores
- `class_teacher_performance` - Class teacher tracking
- `teacher_awards` - Awards and recognition

## API Endpoints

### Authentication
- `POST auth.php?action=login` - User login
- `POST auth.php?action=verify` - Verify token

### Teachers
- `GET teachers.php` - List all teachers
- `POST teachers.php` - Create teacher
- `PUT teachers.php?id=X` - Update teacher
- `DELETE teachers.php?id=X` - Delete teacher
- `POST import-teachers.php` - Bulk import

### Performance Modules
- `GET/POST subject-performance.php` - Subject performance
- `GET/POST lesson-monitoring.php` - Lesson monitoring
- `GET/POST lesson-observations.php` - Observations
- `GET/POST duty-performance.php` - Duty performance
- `GET/POST class-teacher-performance.php` - Class teacher performance

### Dashboard & Reports
- `GET dashboard.php` - Dashboard statistics
- `GET reports.php?type=X` - Generate reports
- `GET/POST awards.php` - Teacher awards

## File Structure

```
Accademics/
├── backend/
│   ├── api/          # API endpoints
│   │   ├── auth.php
│   │   ├── teachers.php
│   │   ├── subject-performance.php
│   │   ├── lesson-monitoring.php
│   │   ├── lesson-observations.php
│   │   ├── duty-performance.php
│   │   ├── class-teacher-performance.php
│   │   ├── dashboard.php
│   │   ├── reports.php
│   │   └── awards.php
│   └── config/
│       ├── Database.php
│       └── JWT.php
├── frontend/
│   ├── app.js        # Main Vue app
│   └── components/   # Vue components
│       ├── Teachers.js
│       ├── SubjectPerformance.js
│       ├── LessonMonitoring.js
│       ├── LessonObservations.js
│       ├── DutyPerformance.js
│       ├── ClassPerformance.js
│       └── Reports.js
├── database/
│   └── schema.sql    # Database schema
├── index.html        # Entry point
├── .htaccess         # URL rewriting rules
└── README.md         # Documentation
```

## Usage Guide

### Dashboard
- View summary statistics
- See top performers and teachers needing follow-up
- Filter by year and term

### Teacher Management
- Add individual teachers
- Import multiple teachers via JSON
- Edit or delete teacher records

### Subject Performance
- Enter BOT and EOT scores
- View automatic TC and AGP calculations
- See automated comments

### Lesson Monitoring
- Track weekly time lost in minutes
- View automatic lesson equivalencies
- Identify patterns in time loss

### Observations
- Record two rounds of observations per term
- Track improvement areas and action points
- View average scores

### Duty Performance
- Score teachers on 5 criteria (20 points each)
- View leaderboards
- Give Teacher of the Week/Month/Year awards

### Class Teacher Performance
- Track weekly activities
- Record academic performance percentages
- View automatic change calculations

### Reports
- Generate weekly, termly, or yearly reports
- View best teacher awards
- Print professional reports

## Security Features

- JWT-based authentication
- Password hashing (bcrypt)
- Role-based access control
- SQL injection prevention (prepared statements)
- CORS configuration
- Input validation

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Responsive design for tablets and mobile

## License

This is a proprietary system for educational institutions.

## Support

For technical support or feature requests, contact the system administrator.
