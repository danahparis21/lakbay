<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>LAKBAY — Explore Mountains</title>
<link rel="stylesheet" href="shared.css">
<style>
/* ── HERO ── */
.explore-hero {
  position: relative;
  height: 420px;
  background: linear-gradient(135deg, #100600 0%, #2C1A0E 50%, #3d2b1a 100%);
  overflow: hidden;
  display: flex;
  align-items: flex-end;
  padding: 40px;
  transition: all 0.3s ease;
}
.explore-hero::before {
  content: '';
  position: absolute; inset: 0;
  background: url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1200&q=80') center/cover;
  opacity: 0.32;
  transition: transform 0.4s ease, opacity 0.3s ease;
}
.explore-hero:hover::before { transform: scale(1.02); opacity: 0.28; }
.explore-hero::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 200px;
  background: linear-gradient(transparent, var(--cream));
}
.hero-content {
  position: relative; z-index: 2; width: 100%;
  animation: fadeInUp 0.8s ease;
}
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}
.hero-tag {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,0.14);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,0.22);
  padding: 6px 14px; border-radius: 50px;
  font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.88);
  letter-spacing: 1px; text-transform: uppercase; margin-bottom: 16px;
  transition: all 0.3s ease;
}
.hero-tag:hover { background: rgba(255,255,255,0.22); transform: translateY(-2px); }
.hero-title {
  font-family: 'Playfair Display', serif;
  font-size: clamp(28px, 5vw, 48px); font-weight: 700;
  color: var(--white); line-height: 1.15;
  max-width: 600px; margin-bottom: 20px;
  text-shadow: 0 2px 10px rgba(0,0,0,0.2);
}
.hero-search { display: flex; gap: 10px; max-width: 520px; }
.hero-search .inp {
  flex: 1; background: rgba(255,255,255,0.92); border-color: transparent;
  border-radius: 50px; padding: 14px 20px; font-size: 14px;
  transition: all 0.3s ease;
}
.hero-search .inp:focus { outline: none; background: white; box-shadow: 0 0 0 3px rgba(198,164,59,0.3); transform: scale(1.01); }
.hero-search .btn { 
  flex-shrink: 0; background: var(--espresso); color: white; border: none;
  border-radius: 50px; padding: 14px 28px; font-weight: 600; cursor: pointer;
  transition: all 0.3s ease;
}
.hero-search .btn:hover { background: var(--espresso-light); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }

/* ── MOBILE SEARCH ── */
.mob-search-bar {
  padding: 16px; background: var(--cream);
  position: sticky; top: 0; z-index: 50;
  border-bottom: 1px solid rgba(16,6,0,0.08);
}
.mob-search-row {
  display: flex; align-items: center; gap: 10px;
  background: rgba(255,255,255,0.7); backdrop-filter: blur(10px);
  border: 1.5px solid rgba(16,6,0,0.1);
  border-radius: 50px; padding: 10px 18px;
  transition: all 0.3s ease;
}
.mob-search-row:focus-within { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(198,164,59,0.2); background: white; }
.mob-search-row input {
  flex: 1; border: none; background: transparent;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14px; color: var(--espresso); outline: none;
}
.mob-search-row input::placeholder { color: var(--stone); }

/* ── SECTIONS ── */
.explore-content { padding: 32px 0 40px; }
.section-row {
  display: flex; align-items: center;
  justify-content: space-between; margin-bottom: 20px;
}
.see-all {
  font-size: 13px; font-weight: 600;
  color: var(--espresso-lt); text-decoration: none; opacity: 0.7;
  transition: all 0.3s ease;
}
.see-all:hover { opacity: 1; transform: translateX(4px); }

/* ── QUIZ BANNER ── */
.quiz-banner {
  background: #100600;
  border-radius: var(--radius);
  padding: 30px 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 40px;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}
.quiz-banner:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.15); }
.quiz-banner::before {
  content: '⛰';
  position: absolute; right: 120px; top: -16px;
  font-size: 110px; opacity: 0.06; pointer-events: none;
  transition: all 0.3s ease;
}
.quiz-banner:hover::before { transform: scale(1.05); opacity: 0.1; }
.quiz-banner::after {
  content: '';
  position: absolute; top: -60px; right: -60px;
  width: 220px; height: 220px;
  background: radial-gradient(circle, rgba(201,168,76,0.12) 0%, transparent 70%);
  border-radius: 50%; pointer-events: none;
}
.quiz-banner-text h3 {
  font-family: 'Playfair Display', serif;
  font-size: 21px; color: white; margin-bottom: 7px; font-weight: 600;
}
.quiz-banner-text p {
  font-size: 13px; color: rgba(255,255,255,0.6); line-height: 1.55; max-width: 400px;
}
.quiz-banner-action { position: relative; z-index: 1; flex-shrink: 0; }
.btn-quiz-glass {
  display: inline-flex; align-items: center; justify-content: center;
  gap: 8px; padding: 12px 26px; border-radius: 50px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14px; font-weight: 700; cursor: pointer;
  border: 1.5px solid rgba(201,168,76,0.6);
  background: rgba(201,168,76,0.18);
  backdrop-filter: blur(12px);
  color: var(--gold); text-decoration: none;
  transition: all 0.3s ease;
  box-shadow: 0 4px 20px rgba(201,168,76,0.15);
}
.btn-quiz-glass:hover {
  background: rgba(201,168,76,0.3);
  border-color: rgba(201,168,76,0.8);
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(201,168,76,0.25);
}

/* ── HORIZONTAL SCROLL ── */
.h-scroll {
  display: flex; gap: 16px; overflow-x: auto;
  padding-bottom: 8px; scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch;
}
.h-scroll::-webkit-scrollbar { height: 0; }
.h-scroll > * { scroll-snap-align: start; flex-shrink: 0; }

/* ── MOUNTAIN CARD ── */
.mtn-card {
  width: 280px;
  background: var(--white);
  border-radius: var(--radius);
  border: 1px solid rgba(16,6,0,0.07);
  overflow: hidden;
  box-shadow: var(--shadow);
  transition: all 0.35s cubic-bezier(0.2, 0.9, 0.4, 1.1);
  cursor: pointer;
}
.mtn-card:hover { transform: translateY(-6px) scale(1.02); box-shadow: var(--shadow-lg); }
.mtn-card-img {
  height: 170px; background-size: cover; background-position: center; position: relative;
  transition: transform 0.4s ease;
}
.mtn-card:hover .mtn-card-img { transform: scale(1.05); }
.mtn-card-img-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(transparent 40%, rgba(16,6,0,0.65));
}
.mtn-card-badges { position: absolute; top: 12px; left: 12px; display: flex; gap: 6px; }
.mtn-card-crowd {
  position: absolute; top: 12px; right: 12px;
  background: rgba(255,255,255,0.18);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255,255,255,0.28);
  padding: 4px 10px; border-radius: 50px;
  font-size: 10px; font-weight: 700; color: white;
  transition: all 0.3s ease;
}
.mtn-card:hover .mtn-card-crowd { transform: scale(1.05); background: rgba(0,0,0,0.5); }
.crowd-low  { background: rgba(27,112,69,0.72) !important; }
.crowd-med  { background: rgba(201,130,26,0.72) !important; }
.crowd-high { background: rgba(184,49,42,0.72) !important; }
.mtn-card-body { padding: 16px; }
.mtn-card-name {
  font-family: 'Playfair Display', serif;
  font-size: 17px; font-weight: 600; color: var(--espresso); margin-bottom: 4px;
}
.mtn-card-loc { font-size: 12px; color: var(--stone); margin-bottom: 10px; display: flex; align-items: center; gap: 4px; }
.mtn-card-stats { display: flex; gap: 16px; margin-bottom: 12px; }
.mtn-stat { display: flex; flex-direction: column; gap: 2px; }
.mtn-stat-val { font-size: 14px; font-weight: 700; color: var(--espresso); font-family: 'DM Mono', monospace; }
.mtn-stat-lbl { font-size: 10px; color: var(--stone); }
.mtn-card-footer { display: flex; align-items: center; justify-content: space-between; }
.mtn-card-rating { display: flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; color: var(--espresso); }

/* ── GUIDE CARD ── */
.guide-card {
  width: 200px; background: var(--white);
  border-radius: var(--radius); border: 1px solid rgba(16,6,0,0.07);
  padding: 20px 16px; text-align: center;
  box-shadow: var(--shadow); cursor: pointer; transition: all 0.35s ease;
}
.guide-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
.guide-avatar-lg {
  width: 64px; height: 64px; border-radius: 50%;
  background: #100600; color: var(--cream);
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; font-weight: 700;
  margin: 0 auto 12px;
  box-shadow: 0 4px 14px rgba(16,6,0,0.22);
  transition: all 0.3s ease;
}
.guide-card:hover .guide-avatar-lg { background: var(--gold); color: var(--espresso); transform: scale(1.05); }
.guide-card-name { font-weight: 700; font-size: 14px; color: var(--espresso); margin-bottom: 4px; }
.guide-card-mtns { font-size: 11px; color: var(--stone); margin-bottom: 10px; }
.guide-card-rating { font-size: 13px; font-weight: 600; color: var(--espresso); }
.guide-card-bookings { font-size: 11px; color: var(--stone); margin-top: 4px; }

/* ── FILTER SECTION ── */
.filter-section { margin-bottom: 28px; }
.filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.chip {
  padding: 8px 20px; border-radius: 50px; font-size: 13px; font-weight: 600;
  background: rgba(255,255,255,0.6); backdrop-filter: blur(6px);
  border: 1.5px solid rgba(16,6,0,0.12); cursor: pointer;
  transition: all 0.3s ease;
}
.chip:hover { background: white; transform: translateY(-2px); border-color: var(--gold); }
.chip.active { background: var(--espresso); color: white; border-color: var(--espresso); }
.filter-select {
  padding: 9px 16px; border-radius: 50px; font-size: 13px; font-weight: 600;
  border: 1.5px solid rgba(16,6,0,0.12);
  background: rgba(255,255,255,0.6); backdrop-filter: blur(6px);
  color: var(--espresso); cursor: pointer; outline: none;
  font-family: 'Plus Jakarta Sans', sans-serif;
  transition: all 0.3s ease;
}
.filter-select:hover { background: white; border-color: var(--gold); }

/* ── MOUNTAIN GRID ── */
.mtn-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 22px; margin-bottom: 40px;
}
.mtn-grid .mtn-card { width: auto; }

/* ── MODAL STYLES ── */
.modal-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(16,6,0,0.55); backdrop-filter: blur(8px);
  z-index: 1100; align-items: center; justify-content: center; padding: 20px;
}
.modal-bg.open { display: flex; animation: fadeIn 0.3s ease; }
@keyframes fadeIn {
  from { opacity: 0; } to { opacity: 1; }
}
.mtn-modal { align-items: flex-start !important; padding: 24px 20px !important; overflow-y: auto; }
.mtn-modal .modal {
  max-width: 760px !important;
  max-height: none !important;
  overflow: visible !important;
  border-radius: 28px !important;
  padding: 0 !important;
  position: relative;
  animation: slideUp 0.3s ease;
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}
.mtn-modal-img {
  height: 260px; background-size: cover; background-position: center;
  border-radius: 28px 28px 0 0; position: relative; flex-shrink: 0;
}
.mtn-modal-img-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(transparent 25%, rgba(16,6,0,0.75));
  border-radius: 28px 28px 0 0;
  display: flex; align-items: flex-end; padding: 24px 28px;
}
.mtn-modal-img-meta { flex: 1; }
.mtn-modal-img-title {
  font-family: 'Playfair Display', serif;
  font-size: 28px; font-weight: 700; color: white; line-height: 1.15;
}
.mtn-modal-img-sub { font-size: 13px; color: rgba(255,255,255,0.65); margin-top: 5px; display: flex; align-items: center; gap: 6px; }
.mtn-close-btn {
  position: absolute; top: 16px; right: 16px;
  width: 34px; height: 34px; border-radius: 50%;
  background: rgba(0,0,0,0.45); backdrop-filter: blur(8px);
  border: 1px solid rgba(255,255,255,0.2);
  color: white; font-size: 15px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.3s ease;
}
.mtn-close-btn:hover { background: rgba(0,0,0,0.7); transform: scale(1.05); }
.mtn-modal-scroll {
  max-height: calc(100vh - 320px);
  overflow-y: auto;
  padding: 0 28px 28px;
  scroll-behavior: smooth;
}
.mtn-modal-scroll::-webkit-scrollbar { width: 4px; }
.mtn-modal-scroll::-webkit-scrollbar-thumb { background: rgba(16,6,0,0.15); border-radius: 2px; }
.mtn-info-grid {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 12px; margin: 22px 0 22px;
}
.mtn-info-item {
  background: var(--sky);
  border-radius: var(--radius-sm);
  padding: 14px 12px; text-align: center;
  transition: all 0.3s ease;
}
.mtn-info-item:hover { transform: translateY(-2px); background: var(--mist); }
.mtn-info-val { font-family: 'DM Mono', monospace; font-size: 18px; font-weight: 700; color: var(--espresso); }
.mtn-info-lbl { font-size: 10px; color: var(--stone); margin-top: 3px; text-transform: uppercase; letter-spacing: 1px; }
.tabs-inline {
  display: flex; gap: 0;
  border-bottom: 2px solid rgba(16,6,0,0.08);
  margin-bottom: 20px; overflow-x: auto;
}
.tabs-inline::-webkit-scrollbar { height: 0; }
.tab-inline {
  padding: 10px 20px; font-size: 13px; font-weight: 600;
  color: var(--stone); cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px; transition: all 0.2s ease;
  white-space: nowrap;
}
.tab-inline:hover { color: var(--espresso); }
.tab-inline.active { color: var(--espresso); border-color: var(--espresso); }
.tab-panel { display: none; }
.tab-panel.active { display: block; animation: fadeIn 0.3s ease; }
.crowd-bar-row { display: flex; gap: 8px; }
.crowd-seg { flex: 1; height: 8px; border-radius: 4px; background: rgba(16,6,0,0.08); }
.crowd-seg.active-low  { background: #2e7d32; }
.crowd-seg.active-med  { background: #e65100; }
.crowd-seg.active-high { background: #c62828; }
.advisory-item {
  display: flex; gap: 12px; align-items: flex-start;
  background: #fff8e1; border-radius: var(--radius-sm);
  padding: 12px; margin-bottom: 8px;
  border: 1px solid rgba(245,167,0,0.2);
  transition: all 0.3s ease;
}
.advisory-item:hover { transform: translateX(4px); background: #fff3c9; }
.review-item {
  padding: 16px 0;
  border-bottom: 1px solid rgba(16,6,0,0.07);
  transition: all 0.3s ease;
}
.review-item:hover { background: rgba(198,164,59,0.05); padding-left: 8px; }
.review-item:last-child { border-bottom: none; }
.review-header { display: flex; align-items: center; gap: 10px; margin-bottom: 7px; }
.review-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: #100600; color: var(--cream);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; flex-shrink: 0;
  transition: all 0.3s ease;
}
.review-item:hover .review-avatar { background: var(--gold); color: var(--espresso); transform: scale(1.05); }
.review-author { font-weight: 700; font-size: 13px; color: var(--espresso); }
.review-stars { color: var(--gold); font-size: 11px; margin-top: 1px; }
.review-date { font-size: 11px; color: var(--stone); margin-left: auto; }
.review-text { font-size: 13px; color: var(--stone); line-height: 1.6; margin-bottom: 10px; }
.review-media { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.review-media-thumb {
  width: 72px; height: 72px; border-radius: 10px;
  background-size: cover; background-position: center;
  cursor: pointer; overflow: hidden; flex-shrink: 0;
  border: 1.5px solid rgba(16,6,0,0.07);
  transition: all 0.3s ease;
}
.review-media-thumb:hover { transform: scale(1.05); border-color: var(--gold); }
.reviews-modal-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(16,6,0,0.55); backdrop-filter: blur(8px);
  z-index: 1100; align-items: center; justify-content: center; padding: 20px;
}
.reviews-modal-bg.open { display: flex; }
.reviews-modal {
  background: rgba(245,242,235,0.96); backdrop-filter: blur(20px);
  border: 1px solid rgba(255,255,255,0.6);
  border-radius: 28px; width: 100%; max-width: 580px;
  max-height: 88vh; display: flex; flex-direction: column;
  box-shadow: 0 24px 64px rgba(16,6,0,0.18);
  animation: slideUp 0.28s ease;
}
.reviews-modal-hdr {
  padding: 22px 28px 16px;
  border-bottom: 1px solid rgba(16,6,0,0.08);
  display: flex; align-items: center; justify-content: space-between;
  flex-shrink: 0;
}
.reviews-modal-body { overflow-y: auto; padding: 20px 28px 28px; flex: 1; }
.lightbox-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(16,6,0,0.92); backdrop-filter: blur(12px);
  z-index: 1200; align-items: center; justify-content: center; padding: 20px;
}
.lightbox-bg.open { display: flex; }
.lightbox-img {
  max-width: 90vw; max-height: 85vh;
  border-radius: 16px; object-fit: contain;
  box-shadow: 0 24px 64px rgba(0,0,0,0.5);
  animation: fadeIn 0.3s ease;
}
.lightbox-close {
  position: fixed; top: 20px; right: 24px;
  width: 38px; height: 38px; border-radius: 50%;
  background: rgba(255,255,255,0.15); backdrop-filter: blur(8px);
  border: 1px solid rgba(255,255,255,0.2);
  color: white; font-size: 16px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.3s ease;
}
.lightbox-close:hover { background: rgba(255,255,255,0.3); transform: scale(1.05); }
.mtn-modal-cta {
  display: flex; gap: 12px;
  padding: 16px 28px;
  border-top: 1px solid rgba(16,6,0,0.07);
  background: rgba(245,242,235,0.96);
  backdrop-filter: blur(12px);
  border-radius: 0 0 28px 28px;
  flex-shrink: 0;
}
.btn-full { width: 100%; padding: 12px; border-radius: 50px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
.btn-outline { background: transparent; border: 1.5px solid var(--espresso); color: var(--espresso); }
.btn-outline:hover { background: var(--espresso); color: var(--cream); transform: translateY(-2px); }
.btn-primary { background: var(--espresso); color: var(--cream); border: none; }
.btn-primary:hover { background: var(--espresso-light); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
.toast {
  position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
  background: var(--espresso); color: var(--cream);
  padding: 12px 28px; border-radius: 60px;
  font-size: 14px; font-weight: 500;
  opacity: 0; transition: opacity 0.3s ease;
  pointer-events: none; z-index: 1300;
}
.toast.show { opacity: 1; }

@media (max-width: 768px) {
  .explore-hero { display: none; }
  .mtn-card { width: 230px; }
  .mtn-info-grid { grid-template-columns: repeat(2, 1fr); }
  .mtn-modal { padding: 0 !important; align-items: flex-end !important; }
  .mtn-modal .modal { border-radius: 24px 24px 0 0 !important; max-width: 100% !important; }
  .mtn-modal-img { border-radius: 24px 24px 0 0; height: 220px; }
  .mtn-modal-img-overlay { border-radius: 24px 24px 0 0; }
  .mtn-modal-scroll { max-height: 60vh; padding: 0 18px 16px; }
  .mtn-info-grid { margin: 16px 0; }
  .mtn-modal-cta { padding: 12px 18px; border-radius: 0; }
  .quiz-banner { flex-direction: column; align-items: flex-start; gap: 16px; }
  .quiz-banner::before { right: 20px; font-size: 80px; }
  .mtn-grid { grid-template-columns: 1fr 1fr; gap: 14px; }
}
@media (max-width: 480px) {
  .mtn-grid { grid-template-columns: 1fr; }
  .mtn-modal-img-title { font-size: 22px; }
  .reviews-modal { border-radius: 24px 24px 0 0; max-height: 92vh; }
}
@media (min-width: 1200px) {
  .mtn-grid { grid-template-columns: repeat(4, 1fr); }
}

/* Desktop Navigation (preserved from original) */
.desktop-nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  background: rgba(250,248,243,0.92); backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(16,6,0,0.08);
  height: 64px; display: flex; align-items: center;
  padding: 0 32px; gap: 32px;
}
.brand {
  font-family: 'Playfair Display', serif; font-size: 18px; font-weight: 700;
  color: var(--forest); text-decoration: none; display: flex; align-items: center; gap: 8px;
}
.brand svg { width: 28px; height: 28px; }
.tabs { display: flex; gap: 4px; flex: 1; justify-content: center; }
.tab-link {
  display: flex; align-items: center; gap: 6px; padding: 8px 16px;
  border-radius: 50px; font-size: 13px; font-weight: 600; color: var(--stone);
  text-decoration: none; transition: all 0.2s;
}
.tab-link:hover { background: var(--sky); color: var(--forest); transform: translateY(-2px); }
.tab-link.active { background: var(--forest); color: var(--cream); }
.tab-link svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; fill: none; }
.user-btn {
  width: 36px; height: 36px; border-radius: 50%; background: var(--forest);
  color: var(--cream); font-weight: 700; font-size: 13px;
  display: flex; align-items: center; justify-content: center; text-decoration: none;
  transition: all 0.2s;
}
.user-btn:hover { transform: scale(1.05); background: var(--gold); color: var(--espresso); }
.mobile-nav { display: none; }
@media (max-width: 768px) {
  .desktop-nav { display: none; }
  .mobile-nav {
    display: block; position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
    background: rgba(250,248,243,0.96); backdrop-filter: blur(16px);
    border-top: 1px solid rgba(16,6,0,0.08);
  }
  .mobile-nav-inner {
    display: flex; align-items: center; justify-content: space-around;
    padding: 8px 0 max(8px, env(safe-area-inset-bottom));
  }
  .mob-nav-item {
    display: flex; flex-direction: column; align-items: center; gap: 3px;
    font-size: 10px; font-weight: 600; color: var(--stone);
    text-decoration: none; padding: 4px 12px;
    transition: all 0.2s;
  }
  .mob-nav-item.active { color: var(--forest); transform: translateY(-2px); }
  .mob-nav-item svg { width: 20px; height: 20px; stroke: currentColor; stroke-width: 1.8; fill: none; }
  .mob-nav-item.quiz-center {
    width: 52px; height: 52px; border-radius: 50%; background: var(--forest);
    color: var(--cream); padding: 0; display: flex; align-items: center; justify-content: center;
    margin-top: -16px; box-shadow: 0 4px 16px rgba(26,46,26,0.3);
  }
}
</style>
</head>
<body>

<!-- DESKTOP NAV -->
<nav class="desktop-nav">
  <a href="explore.php" class="brand">
    <svg viewBox="0 0 32 32" fill="none"><path d="M4 26L10 12L16 20L21 9L28 26H4Z" fill="#100600" opacity=".9"/><path d="M16 20L21 9L28 26H16V20Z" fill="#100600" opacity=".35"/></svg>
    LAKBAY
  </a>
  <div class="tabs">
    <a href="explore.php" class="tab-link active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>Explore</a>
    <a href="bookings.php" class="tab-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Bookings</a>
    <a href="quiz.php" class="tab-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Quiz</a>
    <a href="messages.php" class="tab-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Messages</a>
  </div>
  <a href="hikerProfile.php" class="user-btn">J</a>
</nav>

<!-- MOBILE BOTTOM NAV -->
<nav class="mobile-nav">
  <div class="mobile-nav-inner">
    <a href="explore.php" class="mob-nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg><span>Explore</span></a>
    <a href="bookings.php" class="mob-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><span>Bookings</span></a>
    <a href="quiz.php" class="mob-nav-item quiz-center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></a>
    <a href="messages.php" class="mob-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Messages</span></a>
    <a href="hikerProfile.php" class="mob-nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Profile</span></a>
  </div>
</nav>

<!-- HERO (desktop only) -->
<div class="explore-hero">
  <div class="hero-content">
    <div class="hero-tag">SILENCE BENEATH STEPS</div>
    <div class="hero-title">Discover Your Next Peak Adventure</div>
    <div class="hero-search">
      <input class="inp" type="text" id="heroSearch" placeholder="Search mountains, locations...">
      <button class="btn btn-gold" onclick="applySearch()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        Search
      </button>
    </div>
  </div>
</div>

<!-- MOBILE SEARCH BAR -->
<div class="mob-search-bar" style="display:none;" id="mobSearchBar">
  <div class="mob-search-row">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
    <input type="text" placeholder="Search mountains..." id="mobSearchInp">
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="explore-content">
  <div class="container">

    <!-- Quiz Banner -->
    <div class="quiz-banner" id="quizBanner">
      <div class="quiz-banner-text">
        <h3>Find Your Perfect Trail</h3>
        <p>Take our 2-minute experience quiz and get personalized mountain recommendations matched to your skill level.</p>
      </div>
      <div class="quiz-banner-action">
        <a href="quiz.php" class="btn-quiz-glass">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          Take the Quiz
        </a>
      </div>
    </div>

    <!-- Featured Mountains -->
    <div class="section-row">
      <div>
        <div class="section-label">📍 Popular Now</div>
        <div class="section-title">Featured Mountains</div>
      </div>
      <a href="#all-mountains" class="see-all">See all →</a>
    </div>
    <div class="h-scroll" style="margin-bottom:40px;" id="featuredMtns"></div>

    <!-- Featured Guides -->
    <div class="section-row">
      <div>
        <div class="section-label">🧭 Expert Guides</div>
        <div class="section-title">Tour Guides</div>
      </div>
    </div>
    <div class="h-scroll" style="margin-bottom:40px;" id="featuredGuides"></div>

    <!-- All Mountains -->
    <div id="all-mountains">
      <div class="section-row">
        <div>
          <div class="section-label">🗻 Directory</div>
          <div class="section-title">All Mountains</div>
        </div>
      </div>
      <div class="filter-section">
        <div class="filter-row">
          <div class="chip active" onclick="filterMtns('all', this)">All</div>
          <div class="chip" onclick="filterMtns('easy', this)">Easy</div>
          <div class="chip" onclick="filterMtns('moderate', this)">Moderate</div>
          <div class="chip" onclick="filterMtns('hard', this)">Hard</div>
          <select class="filter-select" onchange="sortMtns(this.value)">
            <option value="">Sort by</option>
            <option value="rating">Rating</option>
            <option value="elevation">Elevation</option>
            <option value="time">Time</option>
          </select>
        </div>
      </div>
      <div class="mtn-grid" id="allMtns"></div>
    </div>

  </div>
</div>

<!-- MOUNTAIN PROFILE MODAL -->
<div class="modal-bg mtn-modal" id="mtnModal">
  <div class="modal" style="display:flex;flex-direction:column;">
    <div class="mtn-modal-img" id="modalImg">
      <button class="mtn-close-btn" onclick="closeMtnModal()">✕</button>
      <div class="mtn-modal-img-overlay">
        <div class="mtn-modal-img-meta">
          <div class="mtn-modal-img-title" id="modalMtnName"></div>
          <div class="mtn-modal-img-sub" id="modalMtnLoc"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><span></span></div>
        </div>
      </div>
    </div>
    <div class="mtn-modal-scroll" id="modalScroll">
      <div class="mtn-info-grid" id="modalInfoGrid"></div>
      <div class="tabs-inline">
        <div class="tab-inline active" onclick="switchTab('overview',this)">Overview</div>
        <div class="tab-inline" onclick="switchTab('reviews',this)">Reviews</div>
        <div class="tab-inline" onclick="switchTab('tips',this)">Tips</div>
        <div class="tab-inline" onclick="switchTab('advisories',this)">Advisories</div>
      </div>
      <div class="tab-panel active" id="tab-overview">
        <p style="font-size:14px;color:var(--stone);line-height:1.75;margin-bottom:20px;" id="modalDesc"></p>
        <div style="margin-bottom:18px;"><div style="font-size:11px;font-weight:600;color:var(--espresso-lt);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:10px;">Crowd Level</div>
        <div class="crowd-bar-row" id="modalCrowdBar"></div><div style="font-size:12px;color:var(--stone);margin-top:8px;" id="modalCrowdLabel"></div></div>
        <div style="background:var(--sky);border-radius:var(--radius-sm);padding:16px;"><div style="font-size:11px;font-weight:600;color:var(--espresso-lt);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:10px;">Fees & Requirements</div><div id="modalFees" style="font-size:13px;color:var(--espresso);line-height:2;"></div></div>
      </div>
      <div class="tab-panel" id="tab-reviews"><div id="modalReviews"></div><button class="btn btn-outline btn-full" style="margin-top:4px;" onclick="openAllReviews()">View All Reviews →</button></div>
      <div class="tab-panel" id="tab-tips"><div id="modalTips"></div></div>
      <div class="tab-panel" id="tab-advisories"><div id="modalAdvisories"></div></div>
    </div>
    <div class="mtn-modal-cta"><button class="btn btn-outline btn-full" onclick="saveMtn()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>Save</button><a href="bookings.php" class="btn btn-primary btn-full" onclick="setBookingMtn()">Book Hike <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a></div>
  </div>
</div>

<!-- ALL REVIEWS MODAL -->
<div class="reviews-modal-bg" id="reviewsModal"><div class="reviews-modal"><div class="reviews-modal-hdr"><div><div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:600;color:var(--espresso);" id="reviewsModalTitle">Reviews</div><div style="font-size:12px;color:var(--stone);margin-top:3px;" id="reviewsModalSub"></div></div><button class="modal-close" onclick="closeAllReviews()">✕</button></div><div class="reviews-modal-body" id="reviewsModalBody"></div></div></div>

<!-- LIGHTBOX -->
<div class="lightbox-bg" id="lightbox" onclick="closeLightbox()"><button class="lightbox-close" onclick="closeLightbox()">✕</button><img class="lightbox-img" id="lightboxImg" src="" alt=""></div>

<div class="toast" id="toast"></div>

<script>
const mountains = [
  { id:1, name:"Mt. Batulao", location:"Nasugbu, Batangas", elevation:"811m", time:"4–5 hrs", difficulty:"moderate", rating:4.8, reviews:124, crowd:"med", image:"https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800&q=80", desc:"Mt. Batulao is one of the most popular mountains in Luzon, known for its open ridges and stunning views of Laguna de Bay and Taal Volcano. Ideal for beginners and intermediate hikers looking for an accessible but rewarding adventure.", fees:"Registration: ₱150/head\nEnvironmental fee: ₱120\nGuide fee (day): ₱900 (1–5 pax)\nGuide fee (overnight): ₱1,600 (1–5 pax)\nCamping fee: +₱50\nParking (day): ₱100 · Parking (overnight): ₱150", reviews_list:[{ author:"Kai R.", initials:"KR", stars:5, date:"Apr 10, 2025", text:"Amazing ridge walk! Views of Taal are worth every step. Guide was super helpful.", media:["https://images.unsplash.com/photo-1551632811-561732d1e306?w=300&q=70"] },{ author:"Dana M.", initials:"DM", stars:4, date:"Mar 28, 2025", text:"Perfect trail for first-timers. The sunrise view from the ridge is absolutely magical.", media:[] }], tips:["Bring at least 2L of water","Start early (5–6 AM) to avoid heat","Wear trail shoes"], advisories:["⚠️ Trail slippery after rain — exercise caution","✅ No active closures as of this week"] },
  { id:2, name:"Mt. Talamitam", location:"Nasugbu, Batangas", elevation:"630m", time:"3–4 hrs", difficulty:"easy", rating:4.6, reviews:89, crowd:"low", image:"https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800&q=80", desc:"A gentle peak often recommended for beginners. Mt. Talamitam features a scenic grassland summit with panoramic views.", fees:"Registration: ₱100/head\nGuide fee (day): ₱700 (1–5 pax)\nParking: ₱80", reviews_list:[{ author:"Marco L.", initials:"ML", stars:5, date:"Apr 12, 2025", text:"Best first hike ever! Easy trail, friendly locals, and incredible grassland views.", media:[] }], tips:["Great for beginners and families","Summit is wide — bring a picnic blanket"], advisories:["✅ Trail is open and accessible","ℹ️ Bring your own food"] },
  { id:3, name:"Mt. Apayang", location:"Batangas", elevation:"980m", time:"6–7 hrs", difficulty:"hard", rating:4.9, reviews:56, crowd:"low", image:"https://images.unsplash.com/photo-1519681393784-d120267933ba?w=800&q=80", desc:"One of Batangas' hidden gems, Mt. Apayang offers dense jungle trails and a challenging ascent rewarded by a stunning crater lake view at the summit.", fees:"Registration: ₱200/head\nGuide fee (day): ₱1,200 (1–5 pax)\nCamping fee: ₱100", reviews_list:[{ author:"Alex T.", initials:"AT", stars:5, date:"Apr 8, 2025", text:"Challenging but incredibly rewarding. The crater lake is absolutely breathtaking.", media:[] }], tips:["Experienced hikers only","Bring trekking poles","Start by 4 AM for overnight trips"], advisories:["⚠️ Trail requires experienced guide — mandatory","⚠️ River crossings during rainy season"] },
  { id:4, name:"Mt. Lantik", location:"Alfonso, Cavite", elevation:"710m", time:"4–5 hrs", difficulty:"moderate", rating:4.7, reviews:73, crowd:"high", image:"https://images.unsplash.com/photo-1501854140801-50d01698950b?w=800&q=80", desc:"Mt. Lantik is known for its pine forest trail and sea of clouds during the early morning. A favorite among photographers.", fees:"Registration: ₱130/head\nEnvironmental fee: ₱100\nGuide fee (day): ₱900 (1–5 pax)\nParking: ₱100", reviews_list:[{ author:"Jess V.", initials:"JV", stars:4, date:"Apr 14, 2025", text:"Sea of clouds was magical! Trail is well-marked.", media:[] }], tips:["Best visited October–February for sea of clouds","Bring a jacket — summit is cold at dawn"], advisories:["⚠️ High footfall on weekends — expect queues","✅ No current weather advisories"] }
];

const guides = [
  {name:"John Dela Cruz", initials:"JD", mountains:["Apayang"], rating:4.9, bookings:42},
  {name:"Maya Reyes", initials:"MR", mountains:["Batulao","Talamitam"], rating:4.8, bookings:35},
  {name:"Rico Cabanlit", initials:"RC", mountains:["Lantik"], rating:5.0, bookings:58},
  {name:"Elena Llorente", initials:"EL", mountains:["Batulao"], rating:4.7, bookings:28}
];

let currentFilter = 'all';
let currentMtn = null;
let currentSort = '';

function buildMtnCard(m) {
  const card = document.createElement('div'); card.className = 'mtn-card'; card.onclick = () => openMtnModal(m);
  const diffLabel = { easy:'Easy', moderate:'Moderate', hard:'Hard' }[m.difficulty];
  card.innerHTML = `<div class="mtn-card-img" style="background-image:url('${m.image}')"><div class="mtn-card-img-overlay"></div><div class="mtn-card-badges"><span class="badge badge-${m.difficulty}">${diffLabel}</span></div><div class="mtn-card-crowd crowd-${m.crowd}">${m.crowd==='low'?'🟢 Low':m.crowd==='med'?'🟡 Medium':'🔴 High'} crowd</div></div><div class="mtn-card-body"><div class="mtn-card-name">${m.name}</div><div class="mtn-card-loc"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>${m.location}</div><div class="mtn-card-stats"><div class="mtn-stat"><div class="mtn-stat-val">${m.elevation}</div><div class="mtn-stat-lbl">Elevation</div></div><div class="mtn-stat"><div class="mtn-stat-val">${m.time}</div><div class="mtn-stat-lbl">Duration</div></div></div><div class="mtn-card-footer"><div class="mtn-card-rating">★ ${m.rating} <span style="color:var(--stone);font-weight:400;font-size:11px;">(${m.reviews})</span></div><button class="btn btn-primary btn-sm">View</button></div></div>`;
  return card;
}

function renderFeatured() { const el = document.getElementById('featuredMtns'); el.innerHTML = ''; mountains.forEach(m => el.appendChild(buildMtnCard(m))); }
function renderGuides() { const el = document.getElementById('featuredGuides'); el.innerHTML = ''; guides.forEach(g => { const card = document.createElement('div'); card.className = 'guide-card'; card.innerHTML = `<div class="guide-avatar-lg">${g.initials}</div><div class="guide-card-name">${g.name}</div><div class="guide-card-mtns">${g.mountains.join(' · ')}</div><div class="guide-card-rating">★ ${g.rating}</div><div class="guide-card-bookings">${g.bookings} trips completed</div>`; el.appendChild(card); }); }
function renderAllMtns() { const el = document.getElementById('allMtns'); el.innerHTML = ''; let filtered = mountains.filter(m => currentFilter === 'all' || m.difficulty === currentFilter); if (currentSort === 'rating') filtered.sort((a,b) => b.rating - a.rating); else if (currentSort === 'elevation') filtered.sort((a,b) => parseInt(b.elevation) - parseInt(a.elevation)); else if (currentSort === 'time') filtered.sort((a,b) => parseInt(b.time) - parseInt(a.time)); filtered.forEach(m => el.appendChild(buildMtnCard(m))); }
function filterMtns(f, el) { currentFilter = f; document.querySelectorAll('.chip').forEach(c => c.classList.remove('active')); el.classList.add('active'); renderAllMtns(); }
function sortMtns(by) { currentSort = by; renderAllMtns(); }
function buildReviewHTML(r) { const mediaHTML = r.media && r.media.length ? `<div class="review-media">${r.media.map(url=>`<div class="review-media-thumb" style="background-image:url('${url}')" onclick="openLightbox('${url}')"></div>`).join('')}</div>` : ''; return `<div class="review-item"><div class="review-header"><div class="review-avatar">${r.initials}</div><div><div class="review-author">${r.author}</div><div class="review-stars">${'★'.repeat(r.stars)}${'☆'.repeat(5-r.stars)}</div></div><div class="review-date">${r.date}</div></div><div class="review-text">${r.text}</div>${mediaHTML}</div>`; }
function openMtnModal(m) { currentMtn = m; document.getElementById('modalImg').style.backgroundImage = `url('${m.image}')`; document.getElementById('modalMtnName').textContent = m.name; document.getElementById('modalMtnLoc').querySelector('span').textContent = m.location; document.getElementById('modalDesc').textContent = m.desc; document.getElementById('modalFees').innerHTML = m.fees.replace(/\n/g,'<br>'); document.getElementById('modalInfoGrid').innerHTML = `<div class="mtn-info-item"><div class="mtn-info-val">${m.elevation}</div><div class="mtn-info-lbl">Elevation</div></div><div class="mtn-info-item"><div class="mtn-info-val">${m.time}</div><div class="mtn-info-lbl">Duration</div></div><div class="mtn-info-item"><div class="mtn-info-val">★ ${m.rating}</div><div class="mtn-info-lbl">Rating</div></div>`; const crowdMap = {low:1,med:2,high:3}; const crowdN = crowdMap[m.crowd]; const colors = ['active-low','active-med','active-high']; document.getElementById('modalCrowdBar').innerHTML = [1,2,3].map(i=>`<div class="crowd-seg ${i<=crowdN?colors[crowdN-1]:''}"></div>`).join(''); document.getElementById('modalCrowdLabel').textContent = {low:'🟢 Low crowd — Great time to visit!',med:'🟡 Moderate crowd — Weekdays recommended.',high:'🔴 High crowd — Expect queues at peak sections.'}[m.crowd]; document.getElementById('modalReviews').innerHTML = m.reviews_list.slice(0,2).map(buildReviewHTML).join(''); document.getElementById('modalTips').innerHTML = m.tips.map(t=>`<div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:12px;"><span style="color:var(--gold);font-size:16px;">•</span><span style="font-size:13px;color:var(--espresso);line-height:1.65;">${t}</span></div>`).join(''); document.getElementById('modalAdvisories').innerHTML = m.advisories.map(a=>`<div class="advisory-item"><span style="font-size:13px;line-height:1.5;">${a}</span></div>`).join(''); document.querySelectorAll('.tab-inline').forEach((t,i)=>t.classList.toggle('active',i===0)); document.querySelectorAll('.tab-panel').forEach((p,i)=>p.classList.toggle('active',i===0)); document.getElementById('modalScroll').scrollTop = 0; document.getElementById('mtnModal').classList.add('open'); }
function closeMtnModal() { document.getElementById('mtnModal').classList.remove('open'); }
function switchTab(name, el) { document.querySelectorAll('.tab-inline').forEach(t=>t.classList.remove('active')); document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active')); el.classList.add('active'); document.getElementById('tab-'+name).classList.add('active'); }
function openAllReviews() { if (!currentMtn) return; document.getElementById('reviewsModalTitle').textContent = currentMtn.name + ' — Reviews'; document.getElementById('reviewsModalSub').textContent = `★ ${currentMtn.rating}  ·  ${currentMtn.reviews} total reviews`; document.getElementById('reviewsModalBody').innerHTML = currentMtn.reviews_list.map(buildReviewHTML).join(''); document.getElementById('reviewsModal').classList.add('open'); }
function closeAllReviews() { document.getElementById('reviewsModal').classList.remove('open'); }
function openLightbox(url) { document.getElementById('lightboxImg').src = url; document.getElementById('lightbox').classList.add('open'); }
function closeLightbox() { document.getElementById('lightbox').classList.remove('open'); }
function setBookingMtn() { if(currentMtn) localStorage.setItem('bookingMtn', JSON.stringify(currentMtn)); }
function saveMtn() { showToast(`${currentMtn.name} saved to favorites 🔖`); }
function applySearch() { const q = document.getElementById('heroSearch').value.toLowerCase(); if (!q) { renderAllMtns(); return; } const el = document.getElementById('allMtns'); el.innerHTML = ''; mountains.filter(m => m.name.toLowerCase().includes(q) || m.location.toLowerCase().includes(q)).forEach(m => el.appendChild(buildMtnCard(m))); }
function showToast(msg) { const t = document.getElementById('toast'); t.textContent = msg; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'), 2800); }
document.getElementById('mtnModal').addEventListener('click', e => { if(e.target===document.getElementById('mtnModal')) closeMtnModal(); });
document.getElementById('reviewsModal').addEventListener('click', e => { if(e.target===document.getElementById('reviewsModal')) closeAllReviews(); });
if (window.innerWidth <= 768) { document.getElementById('mobSearchBar').style.display = 'block'; document.getElementById('mobSearchInp').addEventListener('input', (e) => { const q = e.target.value.toLowerCase(); const el = document.getElementById('allMtns'); el.innerHTML = ''; mountains.filter(m => m.name.toLowerCase().includes(q)).forEach(m => el.appendChild(buildMtnCard(m))); }); }
renderFeatured(); renderGuides(); renderAllMtns();
</script>
</body>
</html>