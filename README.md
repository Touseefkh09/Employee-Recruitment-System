# Employee Recruitment System

## Project Overview
This is a centralized web-based recruitment platform developed as a Final Year Project. It connects candidates, recruiters, and administrators in a seamless hiring ecosystem.

## Features
- **Candidate Module**: Job search, profile management, application tracking.
- **Recruiter Module**: Job posting, applicant management, interview scheduling.
- **Admin Module**: User management, system configuration, reporting.

## Technology Stack
- **Frontend**: HTML5, CSS3 (Custom + Bootstrap 5), JavaScript
- **Backend**: PHP
- **Database**: MySQL

## Setup Instructions
1.  **Server**: Ensure you have XAMPP or WAMP installed.
2.  **Database**: Import the `database/schema.sql` file (coming soon) into your MySQL server.
3.  **Config**: Update `includes/db.php` with your database credentials.
4.  **Run**: Place this folder in your `htdocs` directory and access via `localhost/employee_recruitment_system`.

## Directory Structure
- `/assets`: CSS, JS, Images
- `/includes`: Reusable PHP components (Header, Footer, DB)
- `/candidate`: Candidate-specific pages
- `/recruiter`: Recruiter-specific pages
- `/admin`: Admin dashboard
