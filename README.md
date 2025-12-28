Ticket Purchasing System

This project is a module-based web application developed as part of the Web Programming and Database Systems & Algorithms courses.

The system represents a role-based bus ticket purchasing platform that demonstrates backend–frontend interaction, database design, and secure user workflows.

Project Overview

The application allows users to search for bus trips, select seats, apply discount coupons, and manage purchased tickets.
Administrative roles can manage companies, trips, coupons, and system users.

The project focuses on:

Modular backend architecture

Relational database modeling

Role-based authorization

Secure form handling

Practical use of SQL and algorithms in real-world scenarios

User Roles

User – Searches trips, purchases tickets, manages account and balance

Company Admin – Manages trips and coupons for a specific bus company

Admin – Manages companies, assigns company administrators, and controls global coupons

Pages

index.html — Home page with trip search and featured routes

listings.html — Trip listing and filtering

trip-details.html — Detailed trip information

purchase.html — Seat selection, coupon validation, and purchase summary

tickets.html — Account page and purchased tickets (PDF button is a mockup)

login.html / register.html — Authentication forms (demo logic)

firmapanel.html — Company admin panel (trip & coupon management)

admin.html — System admin panel (company and user management)

404.html — Error handling page

🚀 Installation (Docker)
git clone https://github.com/mrburak0/bilet-satin-alma.git
cd bilet-satin-alma
docker compose up -d --build


The application will be available after the container build process is completed.

⚙️ Technologies Used

PHP 8.3

Apache 2.4

SQLite (PDO)

Bootstrap 5

Docker & Docker Compose

🔓 Demo Credentials
User:
user@example.com / usertest

Admin:
admin@example.com / admintest

Company Admin:
firma@example.com / firmatest

📘 Academic Scope

This project was developed as a course module project for:

Web Programming

Database Systems & Algorithms

It demonstrates:

SQL-based data manipulation and querying

Algorithmic filtering and validation logic

Secure session-based authentication

CRUD operations with relational integrity

Layered system design suitable for scalable applications

⌛ Project Status

The project is actively maintained and open for further improvements.

Developer: Burak Aslan
Year: 2025
