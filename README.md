# SportsSync – Turf Booking & Management System

**Project Overview**

SportsSync is a full-stack turf booking and management platform that connects players, turf vendors, and administrators. It enables seamless turf discovery, booking, payment processing, and real-time verification using QR codes.

This repository is a personal **mirror** of the original team repository, pushed from the SportsSync team GitHub account for portfolio purposes.
All commits reflect team activity; individual contributions are detailed below.

---

**Key Features**

# User Module

* User Registration & Login with secure password hashing (SHA-256)
* OTP-based verification (Email authentication)
* Forgot Password with secure reset flow
* Turf listing with location-based sorting
* Promoted turf prioritization
* Detailed turf view page with:

  * Full turf information
  * Google Maps integration (Get Directions)
* Slot booking system
* Booking receipt generation (PDF + QR Code)
* View upcoming & past bookings
* Download booking receipts
* Booking cancellation (allowed before 36 hours)
* Tournament participation system (enhanced to production level)

---

# Vendor Module

* Vendor dashboard with booking management
* Accept / Reject bookings
* QR Code scanning via mobile camera for booking verification
* Add / Manage turf:

  * OpenStreetMap API integration for location selection
  * Full backend & frontend implementation
* Real-time chat system with Admin (encrypted communication)
* Tournament management system
* Reports generation and system monitoring

---

# Admin Module

* Admin authentication with OTP-based login
* Real-time chat system with vendors (end-to-end encrypted)
* Complete database administration

---

# Security Features

* Password hashing using SHA-256
* OTP-based authentication (Email)
* Secure login system with role-based validation
* Encrypted real-time chat system (Vendor ↔ Admin)
* Secure booking verification via QR codes

---

# Real-Time Systems

* WhatsApp-like real-time chat between Vendor and Admin
* Encrypted message handling
* Live communication for operational coordination

---

#  My Contribution

I was responsible for designing and implementing the **core logic, security systems, and critical user/vendor workflows** of the platform.

#  Authentication & Security

* OTP system (Email-based verification)
* Password hashing (SHA-256)
* Forgot password system with secure flow
* Role-based login (Admin OTP login, Vendor/User standard login)

# User-Side Development

* Turf listing with location-based sorting
* Promoted turf logic
* Detailed turf view with Google Maps integration
* Booking system flow
* PDF receipt generation with QR code verification
* Booking history (upcoming & past)
* Booking cancellation logic (36-hour rule)
* Tournament system upgraded to production-ready

# Vendor-Side Development

* Vendor dashboard (booking management)
* Booking rejection system
* QR code scanning for booking verification (camera-based)
* Add Turf module (Frontend + Backend)
* OpenStreetMap API integration for location handling
* Vendor-Admin encrypted chat system

# Admin & Backend

* Admin chat system (real-time encrypted)
* Database design & administration
* Reports generation system

---

# Technologies Used

* PHP (Core Backend)
* MySQL (Database)
* JavaScript / AJAX
* OpenStreetMap API
* Google Maps API
* QR Code Generation
* PDF Generation Libraries

---

# Note

This repository is a **mirrored copy** of the original team project maintained under the SportsSync GitHub account.
It is published here strictly for **portfolio and demonstration purposes**.
