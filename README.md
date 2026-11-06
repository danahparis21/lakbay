# 🏔️ LAKBAY — Trail Discovery & Hiking Management Platform

> **A web-based platform for trail discovery, nature exploration, real-time hike tracking, and tourism management in Nasugbu, Batangas.**

---

## 📖 Overview

**LAKBAY** is a full-featured hiking management platform built for the Municipality of Nasugbu, Batangas. It goes far beyond a simple trail directory — it is a live, multi-role ecosystem connecting **hikers, tour guides, mountain managers, and the Municipal Tourism Office** through a unified web application.

The platform addresses the lack of a centralized, official information system for the growing hiking community in Nasugbu, covering mountains such as **Mount Batulao**, **Mount Talamitam**, **Mount Lantik**, and **Mount Apayang**.

---

## ✨ Key Features

### 🧭 For Hikers
- **Trail Discovery & Search** — Browse and filter mountains by difficulty, estimated duration, barangay, and popularity
- **Experience-Based Quiz Recommendation** — Input your hiking experience, physical readiness, and preferred duration to get a matched trail recommendation
- **Mountain Profiles** — Each mountain has elevation, difficulty rating (1–10), trail description, fees, access rules, and seasonal advisories
- **Bookings** — Book a mountain hike online; the booking flows through guide confirmation and GCash payment validation
- **Active Hike Tracking (Strava-style)** — GPS-based real-time trail tracking while hiking; tracks steps, duration, elevation, and the route taken
- **Shareable Hike Summary Card** — After finishing, download or copy a shareable image showing your stats and trail map (perfect for IG Stories!)
- **Heatmap** — See which parts of the trail are most crowded before you go
- **Group Hiking** — Join a group and see every member's live location on the map in real time, so no one gets left behind
- **Messaging** — Built-in messaging interface with guides and admin; booking confirmations, cancellations, payment requests, and alerts appear automatically in the message thread
- **Safety Alerts** — Trigger an emergency alert that notifies the Tourism Office and Mountain Manager instantly
- **Trail History** — View all past hikes and their statistics; export hike history
- **Reviews & Ratings** — Rate and review mountains, upload photos
- **Saved Trails** — Bookmark favorite mountains for quick access
- **Weather Monitoring** — Live weather display for each mountain's area
- **Environmental Awareness** — Carry-your-trash reminders, bonfire restrictions, and responsible hiking guidelines embedded throughout

---

### 🧑‍🦯 For Tour Guides
- **Guide Dashboard** — Overview of active bookings, upcoming hikes, and assigned hikers
- **Booking Management** — Accept or decline booking requests; approve GCash payment proofs (with time-limited payment window — expired payments auto-cancel the booking)
- **Live Map** — See all assigned hikers' real-time GPS locations on the trail map
- **Crowd Reporting** — Mark specific trail areas as crowded to alert other guides and hikers
- **Communication Interface** — Dedicated messaging hub with hikers and admin; booking notifications appear inline
- **Safety Monitoring** — View hiker safety status; receive and respond to safety alerts
- **Activity View** — Review completed hike details for each hiker
- **Guide Profile** — Manage personal info, profile photo, and credentials

---

### 🏔️ For Mountain Managers
- **Manager Dashboard** — Overview of bookings, active hikers, and mountain status
- **Booking Management** — View, approve, and update booking status; assign guides to bookings; manage bulk payments
- **Active Hiker Monitor** — See all currently hiking individuals on their mountain via live tracking
- **Advisories** — Post and manage trail advisories and seasonal announcements
- **Analytics** — View hiking trends, booking stats, and mountain usage data
- **Payment Tracking** — Monitor individual and bulk payment statuses
- **Safety Alerts** — Receive real-time safety alerts from hikers and tour guides
- **Profile Management** — Update manager profile and credentials

---

### 🏛️ For Tourism Office (Admin)
- **Admin Dashboard** — Full system overview: users, bookings, revenue, alerts
- **Guide Registration Approvals** — Review tour guide applications with **OCR-powered ID verification** (Tesseract OCR); approve or reject with reasoning
- **Mountain Management** — Add/edit/update mountain profiles, fees, and information
- **Hiker & Guide Directory** — Full list of registered hikers and accredited guides
- **Booking Oversight** — View all system bookings and their statuses
- **Payment Management** — Monitor all payments and financial activity
- **Announcements & Broadcasts** — Send system-wide announcements to all users
- **Alerts Management** — Post, update, acknowledge, and resolve safety alerts
- **Analytics & Reports** — Revenue reports, hiking trends, and system-wide statistics
- **Messaging** — Communicate with any user through the admin messaging interface
- **Reviews Management** — Moderate submitted mountain reviews

---

### 💬 Messaging & Notification System
The built-in messaging interface acts as both a **chat platform and a smart notification hub**:
- All transactions flow through messages: booking requests, confirmations, cancellations, payment instructions, and payment approvals are automatically surfaced in the conversation thread
- GCash payment proofs are submitted and approved/rejected within the message interface
- Payments have a **time limit** — if a hiker doesn't pay within the window, the booking is automatically cancelled
- Real-time unread message badge counts

---

### 📍 Real-Time Tracking & Safety
- **Live GPS location tracking** for hikers during active hikes
- **Group tracking** — see all group members on a shared map
- **Tour guide map** — guides see all their assigned hikers live
- **Mountain manager view** — managers see all active hikers on their mountain
- **Boundary violation logging** — alerts triggered when hikers go off-trail
- **Emergency safety alert** — one-tap SOS to Tourism Office and Mountain Manager
- **Crowd hotspot detection** — algorithmic detection of crowded areas from location data
- **Heatmap visualization** — visual density map of hiker activity

---

### 🌦️ Weather Monitoring
- Integrated weather API showing real-time conditions per mountain area
- Displayed on mountain profiles and during active hikes

---

## 👥 User Roles

| Role | Description |
|------|-------------|
| **Hiker** | Primary end users; explore trails, book hikes, track activity, and stay safe |
| **Tour Guide** | Accredited guides who manage assigned bookings and monitor hiker safety |
| **Mountain Manager** | Per-mountain administrators managing bookings, advisories, and active hikers |
| **Tourism Office (Admin)** | Super-admin overseeing all system operations, users, and data |

---

## 🗂️ Project Structure

```
lakbay/
├── index.php                    # Entry point
├── Dockerfile                   # PHP 8.2 + Apache + Tesseract OCR + ImageMagick
├── docker-compose.yml           # PHP app, MySQL 8.0, phpMyAdmin
│
├── frontend/                    # Shared landing & public pages
├── hiker-frontend/              # Hiker interface (explore, bookings, hike tracking, messages)
├── tourguide-frontend/          # Tour guide interface (bookings, map, safety, messages)
├── mountain-manager-frontend/   # Mountain manager interface (bookings, advisories, analytics)
├── admin frontend/              # Tourism Office admin panel (guides, mountains, analytics)
│
├── pages/                       # Shared page components & modals
│   ├── landing/                 # Public landing page & guidelines
│   ├── explore/                 # Mountain directory & individual mountain pages
│   ├── admin/                   # Admin-specific page handlers
│   └── modals/                  # Booking, login, signup, logout modals
│
├── api/                         # REST API endpoints (PHP)
│   ├── update_location.php      # Live GPS location updates
│   ├── get_nearby_hikers.php    # Group & guide tracking
│   ├── detect_crowd_hotspots.php# Crowd analysis
│   ├── send_safety_alert.php    # SOS / emergency alerts
│   ├── get_weather.php          # Weather data
│   ├── hiker_messages.php       # Hiker messaging
│   ├── guide_messages.php       # Guide messaging
│   ├── upload_proof.php         # GCash payment proof upload
│   ├── save_badge.php           # Hike achievement badges
│   ├── export_history.php       # Hike history export
│   └── ...                      # 30+ additional API endpoints
│
├── config/
│   └── db.php                   # Database connection
│
├── includes/                    # Shared PHP includes (header, footer, navbar)
├── assets/                      # Global CSS, JS, trail map JS
├── cron/                        # Cron jobs (auto-cancel expired bookings & payments)
├── login-and-signup/            # Auth pages
└── uploads/                     # User-uploaded files (guide IDs, payment proofs, photos)
```

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 8.2 |
| **Database** | MySQL 8.0 |
| **Frontend** | HTML, CSS, JavaScript (Vanilla) |
| **Maps** | Leaflet.js with GIS/heatmap layers |
| **OCR** | Tesseract OCR (guide ID verification) |
| **Image Processing** | ImageMagick |
| **Containerization** | Docker + Docker Compose |
| **Web Server** | Apache (via PHP official Docker image) |
| **Database UI** | phpMyAdmin |

---

## 🚀 Getting Started (Docker)

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
| **LAKBAY App** | http://localhost:8000 |
| **phpMyAdmin** | http://localhost:8081 |

### 4. Database Setup
- Open phpMyAdmin at http://localhost:8081
- Login: `root` / `root`
- Import the database SQL file (if provided separately)

### 5. Stop the services
```bash
docker compose down
```

---

## 🏔️ Mountains Covered

| Mountain | Barangay | Difficulty | Notes |
|----------|----------|-----------|-------|
| Mount Batulao | Batulao | Medium–Hard | Most popular; risk of overcrowding |
| Mount Talamitam | Papaya | Medium | Scenic grasslands |
| Mount Lantik | Lantik | Easy–Medium | Lesser known; promoted by platform |
| Mount Apayang | Apayang | Easy | Beginner friendly |

---

## 🔄 Booking Flow

```
Hiker books mountain → Message sent to Guide
        ↓
Guide accepts booking → GCash payment request sent (with timer)
        ↓
Hiker uploads GCash proof → Guide reviews and approves
        ↓
Booking confirmed → Hiker can start hike on the scheduled day
        ↓
         [If timer expires before payment] → Booking auto-cancelled
```

---

## 📊 Cron Jobs

| Job | Purpose |
|-----|---------|
| `cancel_expired_bookings.php` | Auto-cancels bookings not confirmed in time |
| `expire_downpayment.php` | Invalidates GCash payment windows that have timed out |

---

## 🔐 Guide Registration & OCR Verification

Tour guides register through a dedicated form and upload a **government-issued ID**. The system uses **Tesseract OCR** to extract text from the uploaded ID image for identity verification. The Tourism Office admin reviews the extracted data alongside the original ID before approving or rejecting the registration.

---

## 🌟 Hike Tracking & Shareable Card

When a hiker starts an active hike:
- Real-time GPS location is tracked and displayed on a live map
- Step count, elapsed time, elevation, and route are recorded
- Upon completion, a **shareable summary card** is generated containing:
  - Trail map of the route taken
  - Duration and distance
  - Step count
  - Mountain name and achievement badge
- The card can be **downloaded or copied** to share on social media stories

---

## 👥 Contributing

This project was developed as an academic capstone project for the **Municipal Tourism Office of Nasugbu, Batangas**.

---

## 📄 License

This project is for academic and local government use. All rights reserved.
