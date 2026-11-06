# LAKBAY — Trail Discovery & Hiking Management Platform

A web-based platform for trail discovery, nature exploration, real-time hike tracking, and tourism management in Nasugbu, Batangas.

---

## Overview

**LAKBAY** is a full-featured hiking management platform built for the Municipality of Nasugbu, Batangas. It connects four distinct user roles — **Hikers, Tour Guides, Mountain Managers, and the Municipal Tourism Office** — through a unified, carefully designed web application.

The platform addresses the lack of a centralized, official information system for the growing hiking community in Nasugbu, covering mountains such as **Mount Batulao**, **Mount Talamitam**, **Mount Lantik**, and **Mount Apayang**.

---

## Screenshots

### Login

![Login](assets/demo/login.png)

---

### Hiker Interface

**Home — Mountain Map with Weather Details**
> Built with Leaflet.js, the interactive map shows mountain locations with live weather overlays and heatmap layers for crowd density.

![Hiker Home Map](assets/demo/hiker_home_mountainslocinMapwithWeatherDetails.png)

**Home — Search & Explore**

![Hiker Home Search](assets/demo/Hiker_Home_Search.png)

**Home — Browse Accredited Tour Guides**

![Hiker Tour Guide List](assets/demo/hiker_homeListOfTourGuides.png)

**Trail Recommendation Quiz — Welcome**

![Quiz Welcome](assets/demo/hiker_quiz_welcome.png)

**Trail Recommendation Quiz — Beginner Assessment**

![Quiz Beginner](assets/demo/hiker_quiz_beginner.png)

**Mountain Details Page**

![Mountain Details](assets/demo/mountain_details.png)

**Book a Mountain**

![Book Mountain](assets/demo/book_mountain.png)

**Choose Hiker Count per Booking**

![Choose Hikers](assets/demo/choose_hiker_per_mountainbooking.png)

**Bookings Tab**

![Hiker Bookings](assets/demo/hiker_bookingstab.png)

**GCash Payment Proof Submitted**

![Payment Proof Sent](assets/demo/payment_proofsent.png)

**GCash Payment Window Expired**

![Payment Expired](assets/demo/payment_expired_gcash.png)

**Messages**

![Hiker Messages](assets/demo/hiker_messages.png)

**Trail Map — Completed Hike**

![Trail Map](assets/demo/trail_map_pic.png)

**Profile**

![Hiker Profile](assets/demo/hiker_profile.png)

---

### Tour Guide Interface

**Dashboard**

![Tour Guide Dashboard](assets/demo/touGuide_dashboard.png)

**Scheduled Bookings List**

![Guide Bookings List](assets/demo/tourgGuide_ScheduledBookingsLits.png)

**Booking Details**

![Guide Booking Details](assets/demo/tourguide_bookingDetails.png)

**Bookings with Ratings**

![Guide Bookings Ratings](assets/demo/tour_guide_bookings_ratings.png)

**Heatmap — Crowd Density View**

![Guide Heatmap](assets/demo/tourGuide_heatmap.png)

**Track Hikers Safety (Live Map)**

![Track Hikers Safety](assets/demo/tourGuide_TrackHikersSafety.png)

**Messages**

![Guide Messages](assets/demo/tourGudie_Messages.png)

**Profile**

![Guide Profile](assets/demo/tourGuide_profile.png)

---

### Mountain Manager Interface

**Dashboard**

![Mountain Manager Dashboard](assets/demo/MountainManager_dashboard.png)

**KPI Overview**

![Mountain Manager KPIs](assets/demo/mountainManager_kpi.png)

**Analytics**

![Mountain Manager Analytics](assets/demo/mountainManager_Analytics.png)

**Payments**

![Mountain Manager Payments](assets/demo/mountainManager_Payments.png)

**Advisories**

![Mountain Manager Advisories](assets/demo/mountain_manager_Advisories.png)

---

## Features

### For Hikers
- **Trail Discovery & Search** — Browse and filter mountains by difficulty, estimated duration, barangay, and popularity
- **Experience-Based Quiz Recommendation** — Input hiking experience, physical readiness, and preferred duration to get a matched trail recommendation
- **Mountain Profiles** — Each mountain has elevation, difficulty rating (1–10), trail description, fees, access rules, and seasonal advisories
- **Bookings** — Book a mountain hike online; booking flows through guide confirmation and GCash payment validation with a time limit
- **Active Hike Tracking** — GPS-based real-time trail tracking; records steps, duration, elevation, and the full route
- **Shareable Hike Summary Card** — After finishing, download or copy a shareable image showing stats and trail map (perfect for IG Stories)
- **Heatmap** — See which parts of the trail are most crowded before you go
- **Group Hiking** — Join a group and see every member's live location on the map in real time
- **Messaging** — Built-in messaging interface where booking confirmations, cancellations, payment requests, and alerts appear automatically in the thread
- **Safety Alerts** — Trigger an emergency alert that notifies the Tourism Office and Mountain Manager instantly
- **Trail History** — View all past hikes with statistics; export hike history
- **Reviews & Ratings** — Rate and review mountains, upload photos
- **Saved Trails** — Bookmark favorite mountains for quick access
- **Weather Monitoring** — Live weather display per mountain area
- **Environmental Awareness** — Carry-your-trash reminders, bonfire restrictions, and responsible hiking guidelines

---

### For Tour Guides
- **Guide Dashboard** — Overview of active bookings, upcoming hikes, and assigned hikers
- **Booking Management** — Accept or decline booking requests; approve GCash payment proofs within the time-limited payment window
- **Live Map** — See all assigned hikers' real-time GPS positions on the trail
- **Crowd Reporting** — Mark specific trail areas as crowded to alert other guides and hikers
- **Communication Interface** — Dedicated messaging hub; booking notifications appear inline in the conversation thread
- **Safety Monitoring** — View hiker safety status and respond to alerts
- **Activity View** — Review completed hike details per hiker
- **Guide Profile** — Manage personal info, profile photo, and credentials

---

### For Mountain Managers
- **Manager Dashboard** — Overview of bookings, active hikers, and mountain-level KPIs
- **Booking Management** — View, approve, and update booking status; assign guides; manage bulk payments
- **Active Hiker Monitor** — See all currently hiking individuals on their mountain via live tracking
- **Advisories** — Post and manage trail advisories and seasonal announcements
- **Analytics** — View hiking trends, booking stats, and mountain usage data
- **Payment Tracking** — Monitor individual and bulk payment statuses
- **Safety Alerts** — Receive real-time alerts from hikers and guides
- **Profile Management** — Update manager profile and credentials

---

### For Tourism Office (Admin)
- **Admin Dashboard** — Full system overview: users, bookings, revenue, and alerts
- **Guide Registration Approvals** — Review tour guide applications with OCR-powered ID verification (Tesseract OCR); approve or reject with reasoning
- **Mountain Management** — Add, edit, and update mountain profiles, fees, and information
- **Hiker & Guide Directory** — Full list of registered hikers and accredited guides
- **Booking Oversight** — Monitor all system bookings and their statuses
- **Payment Management** — Track all system payments and financial activity
- **Announcements & Broadcasts** — Send system-wide announcements to all users
- **Alerts Management** — Post, update, acknowledge, and resolve safety alerts
- **Analytics & Reports** — Revenue reports, hiking trends, and system-wide statistics
- **Messaging** — Communicate with any user through the admin messaging interface
- **Reviews Management** — Moderate submitted mountain reviews

---

### Messaging & Notification System

The built-in messaging interface acts as both a chat platform and a smart notification hub:
- All transactions surface automatically in the conversation thread: booking requests, confirmations, cancellations, payment instructions, and payment approvals
- GCash payment proofs are submitted and approved or rejected within the message interface
- Payments have a time limit — if a hiker does not pay within the window, the booking is automatically cancelled by the cron job
- Real-time unread message badge counts across all roles

---

### Real-Time Tracking & Safety
- Live GPS location tracking for hikers during active hikes
- Group tracking — see all group members on a shared Leaflet.js map
- Tour guide map — guides see all assigned hikers live
- Mountain manager view — managers see all active hikers on their mountain
- Boundary violation logging — alerts triggered when hikers go off-trail
- Emergency safety alert (SOS) — one-tap notification to Tourism Office and Mountain Manager
- Crowd hotspot detection — algorithmic detection of crowded areas from live location data
- Heatmap visualization — density map of hiker activity powered by Leaflet.js

---

## User Roles

| Role | Description |
|------|-------------|
| Hiker | Primary end users; explore trails, book hikes, track activity, and stay safe |
| Tour Guide | Accredited guides who manage bookings and monitor hiker safety |
| Mountain Manager | Per-mountain administrators managing bookings, advisories, and active hikers |
| Tourism Office (Admin) | Super-admin overseeing all system operations, users, and data |

---

## Booking Flow

```
Hiker books mountain
        |
        v
Message sent to Guide
        |
        v
Guide accepts booking  -->  GCash payment request sent (timer starts)
        |
        v
Hiker uploads GCash proof  -->  Guide reviews and approves
        |
        v
Booking confirmed  -->  Hiker can start hike on the scheduled day

[If timer expires before payment]  -->  Booking auto-cancelled by cron job
```

---

## Project Structure

```
lakbay/
|-- index.php                         Entry point
|-- Dockerfile                        PHP 8.2 + Apache + Tesseract OCR + ImageMagick
|-- docker-compose.yml                PHP app, MySQL 8.0, phpMyAdmin
|
|-- frontend/                         Shared landing & public pages
|-- hiker-frontend/                   Hiker interface (explore, bookings, hike tracking, messages)
|-- tourguide-frontend/               Tour guide interface (bookings, map, safety, messages)
|-- mountain-manager-frontend/        Mountain manager interface (bookings, advisories, analytics)
|-- admin frontend/                   Tourism Office admin panel (guides, mountains, analytics)
|
|-- pages/                            Shared page components & modals
|   |-- landing/                      Public landing page & guidelines
|   |-- explore/                      Mountain directory & individual mountain pages
|   |-- admin/                        Admin-specific page handlers
|   `-- modals/                       Booking, login, signup, logout modals
|
|-- api/                              REST API endpoints (PHP)
|   |-- update_location.php           Live GPS location updates
|   |-- get_nearby_hikers.php         Group & guide tracking
|   |-- detect_crowd_hotspots.php     Crowd analysis
|   |-- send_safety_alert.php         SOS / emergency alerts
|   |-- get_weather.php               Weather data
|   |-- hiker_messages.php            Hiker messaging
|   |-- guide_messages.php            Guide messaging
|   |-- upload_proof.php              GCash payment proof upload
|   |-- save_badge.php                Hike achievement badges
|   |-- export_history.php            Hike history export
|   `-- ...                           30+ additional API endpoints
|
|-- config/
|   `-- db.php                        Database connection
|
|-- includes/                         Shared PHP includes (header, footer, navbar)
|-- assets/                           Global CSS, JS, Leaflet trail map JS
|-- cron/                             Cron jobs (auto-cancel expired bookings & payments)
|-- login-and-signup/                 Auth pages
`-- uploads/                          User-uploaded files (guide IDs, payment proofs, photos)
```

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.2 |
| Database | MySQL 8.0 |
| Frontend | HTML, CSS, JavaScript |
| Maps & Heatmaps | Leaflet.js |
| OCR | Tesseract OCR (guide ID verification) |
| Image Processing | ImageMagick |
| Containerization | Docker + Docker Compose |
| Web Server | Apache (PHP official Docker image) |
| Database UI | phpMyAdmin |

---

## Getting Started (Docker)

### Prerequisites
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running

### 1. Clone the repository

```bash
git clone https://github.com/danahparis21/lakbay.git
cd lakbay
```

### 2. Start all services

```bash
docker compose up --build
```

### 3. Access the application

| Service | URL |
|---------|-----|
| LAKBAY App | http://localhost:8000 |
| phpMyAdmin | http://localhost:8081 |

### 4. Database Setup
- Open phpMyAdmin at http://localhost:8081
- Login: `root` / `root`
- Import the database SQL file

### 5. Stop the services

```bash
docker compose down
```

---

## Mountains Covered

| Mountain | Difficulty | Notes |
|----------|-----------|-------|
| Mount Batulao | Medium–Hard | Most popular; heatmap helps manage overcrowding |
| Mount Talamitam | Medium | Scenic grasslands |
| Mount Lantik | Easy–Medium | Lesser known; promoted through platform |
| Mount Apayang | Easy | Beginner friendly |

---

## Cron Jobs

| Job | Purpose |
|-----|---------|
| `cancel_expired_bookings.php` | Auto-cancels bookings not confirmed in time |
| `expire_downpayment.php` | Invalidates GCash payment windows that have timed out |

---

## Guide Registration & OCR Verification

Tour guides register through a dedicated form and upload a government-issued ID. The system uses **Tesseract OCR** to extract text from the uploaded ID image for identity verification. The Tourism Office admin reviews the extracted data alongside the original ID before approving or rejecting the registration.

---

## Hike Tracking & Shareable Card

When a hiker starts an active hike:
- Real-time GPS location is tracked and displayed on a live Leaflet.js map
- Step count, elapsed time, elevation, and route are recorded throughout
- Upon completion, a shareable summary card is generated containing the trail map, duration, distance, step count, mountain name, and achievement badge
- The card can be downloaded or copied to share directly on social media stories

---

## License

This project is developed as an academic capstone project for the Municipal Tourism Office of Nasugbu, Batangas. All rights reserved.
