<?php
// advisories.php - Lakbay Manager Advisories Page
session_start();

$MANAGER = (object)['name' => 'John Rivera', 'initials' => 'JR', 'mountainId' => 'mt1', 'mountainName' => 'Mt. Pulag'];

function getManagerMountain() {
    return [
        'id' => 'mt1',
        'name' => 'Mt. Pulag',
        'location' => 'Benguet',
        'elevation' => '2,922m',
        'difficulty' => 'Intermediate',
        'description' => 'Highest peak in Luzon, famous for sea of clouds'
    ];
}

// Sample advisories data
function getAdvisories() {
    return [
        [
            'id' => 'ADV001',
            'type' => 'weather',
            'severity' => 'critical',
            'title' => 'Typhoon Signal No. 2 Advisory',
            'message' => 'PAGASA has raised Typhoon Signal No. 2 over Benguet province. All hiking activities on Mt. Pulag are SUSPENDED until further notice. Hikers currently on the trail must descend immediately.',
            'affectedDates' => ['2026-05-14', '2026-05-15', '2026-05-16'],
            'issuedBy' => 'PAGASA / DENR',
            'issuedAt' => '2026-05-12 08:00:00',
            'expiresAt' => '2026-05-17 00:00:00',
            'status' => 'active',
            'affectedBookings' => ['BK001'],
            'notifyHikers' => true,
        ],
        [
            'id' => 'ADV002',
            'type' => 'trail',
            'severity' => 'warning',
            'title' => 'Trail 3 Rockslide — Partial Closure',
            'message' => 'A minor rockslide has blocked Trail 3 (Ambangeg Route) near the 4km mark. Trail 1 and Trail 2 remain open. Hikers must take the alternate Akiki Trail. Expect an additional 1.5 hours of travel time.',
            'affectedDates' => ['2026-06-18', '2026-06-19', '2026-06-20'],
            'issuedBy' => 'PNPNNP Ranger Station',
            'issuedAt' => '2026-06-17 14:30:00',
            'expiresAt' => '2026-06-25 00:00:00',
            'status' => 'active',
            'affectedBookings' => ['BK002'],
            'notifyHikers' => false,
        ],
        [
            'id' => 'ADV003',
            'type' => 'capacity',
            'severity' => 'info',
            'title' => 'Trail Capacity Limit Reached — June 28',
            'message' => 'The daily visitor cap of 150 persons has been reached for June 28, 2026. No additional walk-in registrations will be accepted. All pre-booked groups are confirmed.',
            'affectedDates' => ['2026-06-28'],
            'issuedBy' => 'Mt. Pulag Management Office',
            'issuedAt' => '2026-06-24 09:00:00',
            'expiresAt' => '2026-06-29 00:00:00',
            'status' => 'active',
            'affectedBookings' => [],
            'notifyHikers' => false,
        ],
        [
            'id' => 'ADV004',
            'type' => 'health',
            'severity' => 'warning',
            'title' => 'Altitude Sickness Cases Reported',
            'message' => 'Three (3) cases of acute altitude sickness (AMS) have been reported at Camp 2 last weekend. Guides are reminded to monitor hikers closely, enforce acclimatization protocols, and carry emergency medication. Hikers with existing respiratory conditions are advised to consult a physician before ascending.',
            'affectedDates' => [],
            'issuedBy' => 'DOH — Cordillera Region',
            'issuedAt' => '2026-05-10 11:00:00',
            'expiresAt' => '2026-06-10 00:00:00',
            'status' => 'active',
            'affectedBookings' => [],
            'notifyHikers' => true,
        ],
        [
            'id' => 'ADV005',
            'type' => 'maintenance',
            'severity' => 'info',
            'title' => 'Campsite Maintenance — Kit床 Area Closed',
            'message' => 'The communal cooking area at Camps 2 and 3 will undergo scheduled maintenance from July 5–7. Groups on overnight treks must bring portable cooking stoves. The ranger station at Camp 2 remains operational.',
            'affectedDates' => ['2026-07-05', '2026-07-06', '2026-07-07'],
            'issuedBy' => 'Mt. Pulag Management Office',
            'issuedAt' => '2026-07-01 08:00:00',
            'expiresAt' => '2026-07-08 00:00:00',
            'status' => 'active',
            'affectedBookings' => ['BK003'],
            'notifyHikers' => false,
        ],
        [
            'id' => 'ADV006',
            'type' => 'wildlife',
            'severity' => 'info',
            'title' => 'Wildlife Protection Reminder — Nesting Season',
            'message' => 'The critically endangered Philippine Eagle has been sighted nesting near Summit Trail. All hikers must stay on designated paths and maintain a minimum distance of 50 meters from marked wildlife zones. Feeding or disturbing wildlife is strictly prohibited.',
            'affectedDates' => [],
            'issuedBy' => 'DENR — Biodiversity Division',
            'issuedAt' => '2026-04-01 00:00:00',
            'expiresAt' => '2026-08-31 00:00:00',
            'status' => 'active',
            'affectedBookings' => [],
            'notifyHikers' => false,
        ],
        [
            'id' => 'ADV007',
            'type' => 'weather',
            'severity' => 'info',
            'title' => 'LPA Weather System — Monitor Only',
            'message' => 'A Low Pressure Area (LPA) is being monitored east of Visayas. No immediate threat to Cordillera region but conditions may change. Guides should monitor PAGASA bulletins and be prepared to adjust itineraries on short notice.',
            'affectedDates' => ['2026-05-18', '2026-05-19'],
            'issuedBy' => 'PAGASA',
            'issuedAt' => '2026-05-16 16:00:00',
            'expiresAt' => '2026-05-20 00:00:00',
            'status' => 'resolved',
            'affectedBookings' => [],
            'notifyHikers' => false,
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>LAKBAY Manager — Advisories</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --ink: #100600;
  --ink2: #3a2a1a;
  --ink3: #7a6a5a;
  --ink4: #b0a090;
  --white: #ffffff;
  --off: #faf9f7;
  --surface: #f4f1ec;
  --border: rgba(16,6,0,0.09);
  --border2: rgba(16,6,0,0.15);
  --gold: #c9a84c;
  --gold2: #e8c96a;

  /* Severity colors */
  --critical: #b91c1c;
  --critical-bg: #fef2f2;
  --critical-border: rgba(185,28,28,0.2);
  --critical-glow: rgba(185,28,28,0.08);

  --warning: #c2410c;
  --warning-bg: #fff7ed;
  --warning-border: rgba(194,65,12,0.2);
  --warning-glow: rgba(194,65,12,0.06);

  --info: #1e40af;
  --info-bg: #eff6ff;
  --info-border: rgba(30,64,175,0.2);
  --info-glow: rgba(30,64,175,0.06);

  --resolved: #166534;
  --resolved-bg: #f0fdf4;
  --resolved-border: rgba(22,101,52,0.2);

  --green: #2e7d32;
  --green-bg: #e8f5e9;
  --amber: #e65100;
  --amber-bg: #fff3e0;
  --blue: #1565c0;
  --blue-bg: #e3f2fd;
  --red: #c62828;
  --red-bg: #fce4ec;

  --r: 18px;
  --r-sm: 10px;
  --shadow: 0 4px 24px rgba(16,6,0,0.08);
  --shadow-lg: 0 16px 48px rgba(16,6,0,0.14);
  --shadow-xl: 0 24px 64px rgba(16,6,0,0.18);
  --glass: rgba(255,255,255,0.72);
  --sidebar-w: 260px;
  --sidebar-w-sm: 72px;
  --topbar-h: 64px;
  --mobile-nav-h: 60px;
}

html, body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--surface); color: var(--ink); min-height: 100vh; }
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-thumb { background: rgba(16,6,0,0.15); border-radius: 2px; }

.app-shell { display: flex; min-height: 100vh; }

/* ── SIDEBAR ── */
.sidebar {
  width: var(--sidebar-w);
  background: #F8F6F0;
  display: flex; flex-direction: column;
  position: fixed; top: 0; left: 0; bottom: 0;
  z-index: 200; transition: width .25s ease; overflow: hidden;
}
.sidebar.collapsed { width: var(--sidebar-w-sm); }
.sidebar.collapsed .nav-label,
.sidebar.collapsed .nav-text,
.sidebar.collapsed .sidebar-brand-text,
.sidebar.collapsed .sidebar-footer-text,
.sidebar.collapsed .mountain-badge-text { display: none; }
.sidebar.collapsed .sidebar-brand { justify-content: center; }
.sidebar.collapsed .nav-item { justify-content: center; padding: 14px 0; }
.sidebar.collapsed .mountain-badge { justify-content: center; padding: 12px; }

.sidebar-brand {
  display: flex; align-items: center; gap: 12px;
  padding: 22px 24px 18px; border-bottom: 1px solid var(--border); text-decoration: none;
}
.sidebar-logo { width: 36px; height: 36px; border-radius: 10px; background: var(--gold); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sidebar-logo svg { width: 20px; height: 20px; }
.sidebar-app-name { font-family: 'Playfair Display', serif; font-size: 15px; font-weight: 700; color: var(--ink); letter-spacing: .3px; }
.sidebar-app-sub { font-size: 9px; color: rgba(16,6,0,0.35); letter-spacing: 1.5px; text-transform: uppercase; margin-top: 1px; }

.mountain-badge {
  display: flex; align-items: center; gap: 10px;
  margin: 14px 16px;
  background: linear-gradient(135deg, rgba(201,168,76,0.1), rgba(201,168,76,0.05));
  border: 1px solid rgba(201,168,76,0.3); border-radius: 12px; padding: 12px 14px;
}
.mountain-badge-icon { width: 34px; height: 34px; border-radius: 9px; background: var(--gold); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.mountain-badge-icon svg { width: 16px; height: 16px; stroke: var(--ink); }
.mountain-badge-name { font-size: 12px; font-weight: 700; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mountain-badge-role { font-size: 10px; color: rgba(16,6,0,0.5); margin-top: 1px; }

.nav-section { padding: 0 0 8px; flex: 1; overflow-y: auto; }
.nav-label { font-size: 9px; font-weight: 700; letter-spacing: 1.8px; text-transform: uppercase; color: rgba(16,6,0,0.35); padding: 16px 24px 6px; }
.nav-item {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 24px; font-size: 13px; font-weight: 500;
  color: rgba(16,6,0,0.6); cursor: pointer; text-decoration: none;
  transition: all .15s; border-left: 3px solid transparent; white-space: nowrap;
}
.nav-item:hover { color: var(--ink); background: rgba(16,6,0,0.04); transform: translateX(2px); }
.nav-item.active { color: var(--ink); background: rgba(201,168,76,0.1); border-left-color: var(--gold); }
.nav-item svg { width: 17px; height: 17px; stroke: currentColor; stroke-width: 1.8; flex-shrink: 0; }
.nav-divider { height: 1px; background: rgba(16,6,0,0.08); margin: 8px 20px; }
.nav-item.logout-red { margin-top: 12px; border-top: 1px solid var(--border); color: #b91c1c; }
.nav-item.logout-red:hover { background: rgba(185,28,28,0.08); color: #b91c1c; }
.nav-item.logout-red svg { stroke: #b91c1c; }
.sidebar-footer { padding: 16px 20px; border-top: 1px solid rgba(16,6,0,0.08); display: flex; align-items: center; gap: 10px; }
.sidebar-footer-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: var(--ink); flex-shrink: 0; }
.sidebar-footer-name { font-size: 12px; font-weight: 700; color: var(--ink); }
.sidebar-footer-role { font-size: 10px; color: rgba(16,6,0,0.5); }

/* ── MAIN ── */
.main-area { flex: 1; margin-left: var(--sidebar-w); display: flex; flex-direction: column; min-height: 100vh; transition: margin-left .25s ease; }
.main-area.expanded { margin-left: var(--sidebar-w-sm); }

.topbar {
  height: var(--topbar-h); background: var(--glass); backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border); display: flex; align-items: center;
  justify-content: space-between; padding: 0 28px; position: sticky; top: 0; z-index: 100;
}
.topbar-left { display: flex; align-items: center; gap: 14px; }
.sidebar-toggle { width: 36px; height: 36px; border-radius: 9px; border: 1px solid var(--border2); background: var(--white); cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--ink); transition: all .15s; }
.sidebar-toggle:hover { background: var(--ink); color: var(--white); transform: rotate(90deg); }
.sidebar-toggle svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2; }
.topbar-page-title { font-size: 15px; font-weight: 700; color: var(--ink); }
.topbar-page-sub { font-size: 11px; color: var(--ink3); margin-top: 1px; }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.topbar-date { font-family: 'DM Mono', monospace; font-size: 11px; color: var(--ink4); padding: 6px 12px; background: var(--off); border-radius: 20px; }
.topbar-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--ink); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; cursor: pointer; transition: transform .2s; }
.topbar-avatar:hover { transform: scale(1.05); }

/* ── MOBILE NAV ── */
.mobile-bottom-nav {
  display: none; position: fixed; bottom: 0; left: 0; right: 0; height: var(--mobile-nav-h);
  background: var(--white); border-top: 1px solid var(--border); z-index: 150;
  justify-content: space-around; align-items: center; padding: 8px 16px;
}
.mobile-nav-item { display: flex; flex-direction: column; align-items: center; gap: 4px; background: none; border: none; cursor: pointer; padding: 6px 12px; border-radius: 12px; transition: all 0.2s; color: var(--ink3); text-decoration: none; font-family: 'Plus Jakarta Sans', sans-serif; }
.mobile-nav-item svg { width: 22px; height: 22px; stroke: currentColor; stroke-width: 1.8; }
.mobile-nav-item span { font-size: 10px; font-weight: 500; }
.mobile-nav-item.active { color: var(--gold); background: rgba(201,168,76,0.1); }

/* ── CONTENT ── */
.content { flex: 1; padding: 28px; display: flex; flex-direction: column; gap: 24px; }

/* Alert Bar */
.alert-bar {
  display: flex; align-items: center; gap: 14px;
  background: linear-gradient(135deg, var(--critical) 0%, #991b1b 100%);
  border-radius: var(--r); padding: 16px 20px;
  animation: pulseBar 3s ease-in-out infinite;
  box-shadow: 0 8px 32px rgba(185,28,28,0.25);
}
@keyframes pulseBar { 0%,100% { box-shadow: 0 8px 32px rgba(185,28,28,0.25); } 50% { box-shadow: 0 12px 40px rgba(185,28,28,0.4); } }
.alert-bar-icon { width: 40px; height: 40px; border-radius: 12px; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0; animation: iconPulse 1.5s ease-in-out infinite; }
@keyframes iconPulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.1); } }
.alert-bar-icon svg { width: 20px; height: 20px; stroke: white; stroke-width: 2; }
.alert-bar-content { flex: 1; }
.alert-bar-title { font-size: 13px; font-weight: 700; color: white; margin-bottom: 2px; }
.alert-bar-sub { font-size: 11px; color: rgba(255,255,255,0.75); }
.alert-bar-action { background: white; color: var(--critical); border: none; border-radius: 8px; padding: 8px 16px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; white-space: nowrap; font-family: 'Plus Jakarta Sans', sans-serif; }
.alert-bar-action:hover { background: rgba(255,255,255,0.9); transform: scale(1.02); }

/* Stats */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.stat-card {
  background: var(--white); border-radius: var(--r); border: 1px solid var(--border);
  padding: 20px; box-shadow: var(--shadow); text-align: center;
  transition: all 0.3s cubic-bezier(0.4,0,0.2,1); cursor: pointer; position: relative; overflow: hidden;
}
.stat-card:hover { transform: translateY(-4px) scale(1.02); box-shadow: var(--shadow-lg); }
.stat-card-icon { width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; }
.stat-card-icon svg { width: 22px; height: 22px; stroke-width: 1.8; }
.stat-value { font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 700; color: var(--ink); line-height: 1; }
.stat-label { font-size: 11px; font-weight: 600; color: var(--ink3); margin-top: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-card.critical .stat-card-icon { background: var(--critical-bg); }
.stat-card.critical .stat-card-icon svg { stroke: var(--critical); }
.stat-card.critical .stat-value { color: var(--critical); }
.stat-card.warning .stat-card-icon { background: var(--warning-bg); }
.stat-card.warning .stat-card-icon svg { stroke: var(--warning); }
.stat-card.warning .stat-value { color: var(--warning); }
.stat-card.info-card .stat-card-icon { background: var(--info-bg); }
.stat-card.info-card .stat-card-icon svg { stroke: var(--info); }
.stat-card.info-card .stat-value { color: var(--info); }
.stat-card.resolved-card .stat-card-icon { background: var(--resolved-bg); }
.stat-card.resolved-card .stat-card-icon svg { stroke: var(--resolved); }
.stat-card.resolved-card .stat-value { color: var(--resolved); }

/* Toolbar Panel */
.panel { background: var(--white); border-radius: var(--r); border: 1px solid var(--border); box-shadow: var(--shadow); overflow: hidden; }
.panel-body { padding: 20px 22px; }
.toolbar { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
.search-box { display: flex; align-items: center; gap: 10px; background: var(--off); border: 1.5px solid var(--border2); border-radius: 50px; padding: 8px 16px; flex: 1; min-width: 200px; transition: all 0.3s; }
.search-box:focus-within { border-color: var(--gold); background: var(--white); box-shadow: 0 0 0 3px rgba(201,168,76,0.1); }
.search-box svg { width: 14px; height: 14px; stroke: var(--ink4); stroke-width: 2; flex-shrink: 0; }
.search-box input { flex: 1; border: none; background: transparent; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; color: var(--ink); outline: none; }
.search-box input::placeholder { color: var(--ink4); }
.select { appearance: none; padding: 8px 32px 8px 12px; border-radius: var(--r-sm); border: 1.5px solid var(--border2); background: var(--white); font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; color: var(--ink); outline: none; cursor: pointer; transition: all 0.2s; }
.select:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.1); }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 8px 16px; border-radius: 8px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 12px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
.btn-primary { background: var(--ink); color: var(--white); }
.btn-primary:hover { background: var(--ink2); transform: translateY(-2px); box-shadow: var(--shadow); }
.btn-outline { background: transparent; border: 1.5px solid var(--border2); color: var(--ink); }
.btn-outline:hover { background: var(--ink); color: var(--white); border-color: var(--ink); transform: translateY(-2px); }
.btn-danger { background: var(--critical); color: white; }
.btn-danger:hover { background: #991b1b; transform: translateY(-2px); }
.btn-sm { padding: 6px 12px; font-size: 11px; }
.btn-xs { padding: 4px 10px; font-size: 10px; border-radius: 6px; }

.filter-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 14px; }
.chip { padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1.5px solid var(--border2); background: var(--white); color: var(--ink3); cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
.chip svg { width: 12px; height: 12px; stroke: currentColor; }
.chip:hover { border-color: var(--gold); color: var(--gold); transform: translateY(-2px); }
.chip.active { background: var(--gold); color: var(--ink); border-color: var(--gold); box-shadow: var(--shadow); }

/* Advisory Cards Grid */
.advisories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 20px;
}

/* Advisory Card */
.advisory-card {
  background: var(--white);
  border-radius: 20px;
  border: 1.5px solid var(--border);
  box-shadow: var(--shadow);
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
  cursor: pointer;
  position: relative;
  display: flex;
  flex-direction: column;
  animation: cardIn 0.4s ease both;
}
@keyframes cardIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
.advisory-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: rgba(16,6,0,0.15); }
.advisory-card.critical { border-color: var(--critical-border); }
.advisory-card.warning { border-color: var(--warning-border); }
.advisory-card.info { border-color: var(--info-border); }
.advisory-card.resolved { opacity: 0.75; }
.advisory-card.resolved:hover { opacity: 1; }

/* Severity Banner */
.card-banner {
  height: 6px;
  background: var(--border);
}
.advisory-card.critical .card-banner { background: linear-gradient(90deg, var(--critical), #ef4444); }
.advisory-card.warning .card-banner { background: linear-gradient(90deg, var(--warning), #f97316); }
.advisory-card.info .card-banner { background: linear-gradient(90deg, var(--info), #3b82f6); }
.advisory-card.resolved .card-banner { background: linear-gradient(90deg, var(--resolved), #22c55e); }

.card-body { padding: 20px; flex: 1; }
.card-top { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 14px; }

.card-type-icon {
  width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: transform 0.3s;
}
.advisory-card:hover .card-type-icon { transform: scale(1.08) rotate(-3deg); }
.advisory-card.critical .card-type-icon { background: var(--critical-bg); }
.advisory-card.critical .card-type-icon svg { stroke: var(--critical); }
.advisory-card.warning .card-type-icon { background: var(--warning-bg); }
.advisory-card.warning .card-type-icon svg { stroke: var(--warning); }
.advisory-card.info .card-type-icon { background: var(--info-bg); }
.advisory-card.info .card-type-icon svg { stroke: var(--info); }
.advisory-card.resolved .card-type-icon { background: var(--resolved-bg); }
.advisory-card.resolved .card-type-icon svg { stroke: var(--resolved); }
.card-type-icon svg { width: 20px; height: 20px; stroke-width: 1.8; }

.card-meta { flex: 1; min-width: 0; }
.card-badges { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 6px; }
.severity-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
.severity-badge.critical { background: var(--critical-bg); color: var(--critical); border: 1px solid var(--critical-border); }
.severity-badge.warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning-border); }
.severity-badge.info { background: var(--info-bg); color: var(--info); border: 1px solid var(--info-border); }
.severity-badge.resolved { background: var(--resolved-bg); color: var(--resolved); border: 1px solid var(--resolved-border); }
.severity-badge-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.advisory-card.critical .severity-badge-dot { animation: blinkDot 1.2s ease-in-out infinite; }
@keyframes blinkDot { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }

.type-badge { display: inline-flex; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; background: var(--off); color: var(--ink3); border: 1px solid var(--border); text-transform: capitalize; }

.card-title { font-family: 'Playfair Display', serif; font-size: 15px; font-weight: 600; color: var(--ink); line-height: 1.3; margin-bottom: 2px; }
.card-issued { font-size: 10px; color: var(--ink4); font-family: 'DM Mono', monospace; }

.card-message { font-size: 12.5px; color: var(--ink3); line-height: 1.65; margin-bottom: 16px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

.card-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
.card-footer-left { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.card-issuer { display: flex; align-items: center; gap: 6px; font-size: 10px; color: var(--ink4); }
.card-issuer svg { width: 11px; height: 11px; stroke: currentColor; }

.affected-bookings { display: flex; gap: 4px; }
.bk-chip { background: rgba(201,168,76,0.12); color: #7a5a10; border: 1px solid rgba(201,168,76,0.3); border-radius: 6px; padding: 2px 8px; font-size: 10px; font-weight: 600; font-family: 'DM Mono', monospace; }

.expiry-pill { display: flex; align-items: center; gap: 4px; background: var(--off); border-radius: 20px; padding: 4px 10px; font-size: 10px; color: var(--ink3); font-family: 'DM Mono', monospace; }
.expiry-pill svg { width: 10px; height: 10px; stroke: currentColor; }
.expiry-pill.urgent { background: var(--critical-bg); color: var(--critical); }

.notify-indicator { display: flex; align-items: center; gap: 4px; font-size: 10px; color: var(--green); font-weight: 600; }
.notify-indicator svg { width: 11px; height: 11px; stroke: currentColor; }

/* Empty */
.empty-state { text-align: center; padding: 80px 20px; }
.empty-state-icon { width: 80px; height: 80px; border-radius: 40px; background: var(--off); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; animation: bounce 2s infinite; }
@keyframes bounce { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
.empty-state-icon svg { width: 40px; height: 40px; stroke: var(--ink4); }
.empty-state h3 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
.empty-state p { font-size: 13px; color: var(--ink3); }

/* ── MODAL ── */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(6px); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
.modal-overlay.open { display: flex; }
.modal-container { background: var(--white); border-radius: 24px; width: 100%; max-width: 680px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: var(--shadow-xl); animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1); }
@keyframes modalIn { from { transform: scale(0.88) translateY(-30px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }

.modal-banner { height: 8px; }
.modal-container.critical .modal-banner { background: linear-gradient(90deg, var(--critical), #ef4444); }
.modal-container.warning .modal-banner { background: linear-gradient(90deg, var(--warning), #f97316); }
.modal-container.info .modal-banner { background: linear-gradient(90deg, var(--info), #3b82f6); }
.modal-container.resolved .modal-banner { background: linear-gradient(90deg, var(--resolved), #22c55e); }

.modal-header { padding: 20px 24px 18px; display: flex; justify-content: space-between; align-items: flex-start; flex-shrink: 0; border-bottom: 1px solid var(--border); }
.modal-header-left { display: flex; align-items: flex-start; gap: 14px; }
.modal-header-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.modal-container.critical .modal-header-icon { background: var(--critical-bg); }
.modal-container.critical .modal-header-icon svg { stroke: var(--critical); }
.modal-container.warning .modal-header-icon { background: var(--warning-bg); }
.modal-container.warning .modal-header-icon svg { stroke: var(--warning); }
.modal-container.info .modal-header-icon { background: var(--info-bg); }
.modal-container.info .modal-header-icon svg { stroke: var(--info); }
.modal-container.resolved .modal-header-icon { background: var(--resolved-bg); }
.modal-container.resolved .modal-header-icon svg { stroke: var(--resolved); }
.modal-header-icon svg { width: 24px; height: 24px; stroke-width: 1.8; }
.modal-title { font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 600; color: var(--ink); line-height: 1.25; margin-bottom: 4px; }
.modal-sub { font-size: 11px; color: var(--ink4); font-family: 'DM Mono', monospace; }
.modal-close { width: 36px; height: 36px; border-radius: 50%; background: var(--off); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--ink3); transition: all 0.2s; flex-shrink: 0; }
.modal-close:hover { background: var(--ink); color: white; transform: rotate(90deg); }
.modal-close svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2; }

.modal-body { flex: 1; overflow-y: auto; padding: 24px; background: var(--surface); display: flex; flex-direction: column; gap: 20px; }

.detail-block { background: var(--white); border-radius: 16px; padding: 20px; border: 1px solid var(--border); }
.detail-block-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: var(--ink3); margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.detail-block-title svg { width: 14px; height: 14px; stroke: currentColor; }
.detail-row { display: flex; gap: 14px; padding: 10px 0; border-bottom: 1px solid var(--border); align-items: flex-start; }
.detail-row:last-child { border-bottom: none; padding-bottom: 0; }
.detail-icon { width: 32px; height: 32px; border-radius: 9px; background: var(--off); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.detail-icon svg { width: 15px; height: 15px; stroke: var(--gold); stroke-width: 1.8; }
.detail-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--ink4); margin-bottom: 3px; }
.detail-value { font-size: 13px; font-weight: 600; color: var(--ink); }
.detail-value.message-text { font-weight: 400; line-height: 1.7; font-size: 13.5px; color: var(--ink2); }

.dates-grid { display: flex; flex-wrap: wrap; gap: 8px; }
.date-chip { background: rgba(201,168,76,0.1); border: 1px solid rgba(201,168,76,0.3); border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 600; color: #7a5a10; font-family: 'DM Mono', monospace; }

.bookings-affected { display: flex; flex-wrap: wrap; gap: 8px; }
.bk-detail-chip { background: var(--off); border: 1.5px solid var(--border2); border-radius: 10px; padding: 8px 14px; font-size: 12px; font-weight: 700; color: var(--ink); font-family: 'DM Mono', monospace; display: flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.2s; }
.bk-detail-chip:hover { border-color: var(--gold); background: rgba(201,168,76,0.06); transform: scale(1.02); }
.bk-detail-chip-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--gold); }

.notify-row { display: flex; align-items: center; justify-content: space-between; padding: 14px; background: var(--green-bg); border-radius: 12px; border: 1px solid rgba(46,125,50,0.2); }
.notify-row-left { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600; color: var(--green); }
.notify-row-left svg { width: 18px; height: 18px; stroke: currentColor; }

.modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; gap: 10px; justify-content: flex-end; background: var(--white); flex-shrink: 0; }

/* New Advisory Form */
.form-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(6px); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
.form-overlay.open { display: flex; }
.form-container { background: var(--white); border-radius: 24px; width: 100%; max-width: 600px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: var(--shadow-xl); animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1); }
.form-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, var(--ink) 0%, var(--ink2) 100%); color: white; }
.form-title { font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 600; }
.form-body { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 18px; }
.form-group { display: flex; flex-direction: column; gap: 7px; }
.form-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--ink3); }
.form-input, .form-select, .form-textarea {
  border: 1.5px solid var(--border2); border-radius: 12px; padding: 11px 14px;
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; color: var(--ink);
  background: var(--off); outline: none; transition: all 0.2s; width: 100%;
}
.form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.1); background: var(--white); }
.form-textarea { resize: vertical; min-height: 100px; line-height: 1.6; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; gap: 10px; justify-content: flex-end; background: var(--white); }

/* Toggle */
.toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: var(--off); border-radius: 12px; border: 1.5px solid var(--border); }
.toggle-info h4 { font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 2px; }
.toggle-info p { font-size: 11px; color: var(--ink3); }
.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
.toggle-switch input { display: none; }
.toggle-slider { position: absolute; inset: 0; background: #ddd; border-radius: 24px; cursor: pointer; transition: 0.25s; }
.toggle-slider:before { content: ''; position: absolute; width: 18px; height: 18px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: 0.25s; }
input:checked + .toggle-slider { background: var(--gold); }
input:checked + .toggle-slider:before { transform: translateX(20px); }

/* Toast */
.toast { position: fixed; bottom: 30px; right: 30px; background: linear-gradient(135deg, var(--ink) 0%, var(--ink2) 100%); color: white; padding: 14px 24px; border-radius: 50px; font-size: 13px; font-weight: 500; opacity: 0; transition: all 0.3s; z-index: 1100; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-lg); transform: translateX(100%); }
.toast.show { opacity: 1; transform: translateX(0); }
.toast.success { background: linear-gradient(135deg, var(--green) 0%, #1b5e20 100%); }
.toast.danger { background: linear-gradient(135deg, var(--red) 0%, #b71c1c 100%); }

/* Action buttons in cards */
.card-actions { display: flex; gap: 6px; padding: 14px 20px; border-top: 1px solid var(--border); background: var(--off); }
.card-action-btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 8px; border-radius: 10px; font-size: 11px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; font-family: 'Plus Jakarta Sans', sans-serif; }
.card-action-btn svg { width: 13px; height: 13px; stroke: currentColor; }
.card-action-btn.view { background: var(--white); color: var(--ink); border: 1.5px solid var(--border2); }
.card-action-btn.view:hover { background: var(--ink); color: white; border-color: var(--ink); }
.card-action-btn.notify { background: var(--green-bg); color: var(--green); border: 1.5px solid rgba(46,125,50,0.2); }
.card-action-btn.notify:hover { background: var(--green); color: white; }
.card-action-btn.resolve { background: var(--blue-bg); color: var(--blue); border: 1.5px solid rgba(21,101,192,0.2); }
.card-action-btn.resolve:hover { background: var(--blue); color: white; }
.card-action-btn.dismiss { background: var(--red-bg); color: var(--red); border: 1.5px solid rgba(198,40,40,0.2); }
.card-action-btn.dismiss:hover { background: var(--red); color: white; }

/* Responsive */
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
  .sidebar.mobile-open { transform: translateX(0); }
  .main-area { margin-left: 0 !important; }
  .mobile-bottom-nav { display: flex; }
  .content { padding: 14px; padding-bottom: calc(var(--mobile-nav-h) + 14px); }
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .advisories-grid { grid-template-columns: 1fr; }
  .topbar { padding: 0 16px; }
  .alert-bar { flex-wrap: wrap; }
  .form-row { grid-template-columns: 1fr; }
  .toast { left: 20px; right: 20px; bottom: 80px; justify-content: center; }
}
@media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>

<?php
$mountain = getManagerMountain();
$advisories = getAdvisories();
$criticalCount = count(array_filter($advisories, fn($a) => $a['severity'] === 'critical' && $a['status'] === 'active'));
$warningCount = count(array_filter($advisories, fn($a) => $a['severity'] === 'warning' && $a['status'] === 'active'));
$infoCount = count(array_filter($advisories, fn($a) => $a['severity'] === 'info' && $a['status'] === 'active'));
$resolvedCount = count(array_filter($advisories, fn($a) => $a['status'] === 'resolved'));
?>

<div class="app-shell">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <a href="#" class="sidebar-brand">
    <div class="sidebar-logo">
      <svg viewBox="0 0 28 28" fill="none"><path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="#100600" opacity=".9"/><path d="M14 16L18 8L24 22H14V16Z" fill="#100600" opacity=".35"/></svg>
    </div>
    <div class="sidebar-brand-text">
      <div class="sidebar-app-name">LAKBAY</div>
      <div class="sidebar-app-sub">Manager Portal</div>
    </div>
  </a>
  <div class="mountain-badge">
    <div class="mountain-badge-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg></div>
    <div class="mountain-badge-text">
      <div class="mountain-badge-name"><?= htmlspecialchars($mountain['name']) ?></div>
      <div class="mountain-badge-role"><?= htmlspecialchars($mountain['location']) ?></div>
    </div>
  </div>
  <nav class="nav-section">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      <span class="nav-text">Dashboard</span>
    </a>
    <a href="bookings_manager.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span class="nav-text">Bookings</span>
    </a>
    <a href="payments.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      <span class="nav-text">Payments</span>
    </a>
    <div class="nav-divider"></div>
    <div class="nav-label">Reports</div>
    <a href="analytics.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span class="nav-text">Analytics</span>
    </a>
    <a href="advisories.php" class="nav-item active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span class="nav-text">Advisories</span>
      <?php if($criticalCount > 0): ?>
      <span style="margin-left:auto;background:var(--critical);color:white;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;"><?= $criticalCount ?></span>
      <?php endif; ?>
    </a>
    <a href="logout.php" class="nav-item logout-red">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span class="nav-text">Log out</span>
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-footer-avatar"><?= htmlspecialchars($MANAGER->initials) ?></div>
    <div class="sidebar-footer-text">
      <div class="sidebar-footer-name"><?= htmlspecialchars($MANAGER->name) ?></div>
      <div class="sidebar-footer-role">Mountain Manager</div>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main-area" id="mainArea">

  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-left">
      <button class="sidebar-toggle" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-page-title">Advisories</div>
        <div class="topbar-page-sub" id="advisoryCount"><?= count($advisories) ?> advisories for <?= htmlspecialchars($mountain['name']) ?></div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="topbar-date" id="topbarDate"></div>
      <div class="topbar-avatar"><?= htmlspecialchars($MANAGER->initials) ?></div>
    </div>
  </div>

  <div class="content">

    <!-- Critical Alert Bar -->
    <?php if($criticalCount > 0): $critAdv = array_values(array_filter($advisories, fn($a) => $a['severity'] === 'critical' && $a['status'] === 'active'))[0]; ?>
    <div class="alert-bar" id="alertBar">
      <div class="alert-bar-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div class="alert-bar-content">
        <div class="alert-bar-title">⚠ ACTIVE CRITICAL ADVISORY — <?= htmlspecialchars($critAdv['title']) ?></div>
        <div class="alert-bar-sub">Issued by <?= htmlspecialchars($critAdv['issuedBy']) ?> · <?= date('M d, Y g:i A', strtotime($critAdv['issuedAt'])) ?> · <?= count($critAdv['affectedBookings']) ?> booking(s) affected</div>
      </div>
      <button class="alert-bar-action" onclick="openAdvisory('<?= $critAdv['id'] ?>')">View Details</button>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card critical" onclick="filterSeverity('critical')">
        <div class="stat-card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="stat-value"><?= $criticalCount ?></div>
        <div class="stat-label">Critical</div>
      </div>
      <div class="stat-card warning" onclick="filterSeverity('warning')">
        <div class="stat-card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="stat-value"><?= $warningCount ?></div>
        <div class="stat-label">Warnings</div>
      </div>
      <div class="stat-card info-card" onclick="filterSeverity('info')">
        <div class="stat-card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        </div>
        <div class="stat-value"><?= $infoCount ?></div>
        <div class="stat-label">Informational</div>
      </div>
      <div class="stat-card resolved-card" onclick="filterSeverity('resolved')">
        <div class="stat-card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-value"><?= $resolvedCount ?></div>
        <div class="stat-label">Resolved</div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="panel">
      <div class="panel-body">
        <div class="toolbar">
          <div class="search-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" id="searchInp" placeholder="Search advisories, issuers, keywords..." oninput="applyFilters()">
          </div>
          <select class="select" id="filterType" onchange="applyFilters()">
            <option value="">All Types</option>
            <option value="weather">Weather</option>
            <option value="trail">Trail</option>
            <option value="health">Health</option>
            <option value="capacity">Capacity</option>
            <option value="maintenance">Maintenance</option>
            <option value="wildlife">Wildlife</option>
          </select>
          <button class="btn btn-outline btn-sm" onclick="clearFilters()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="12" height="12"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Clear
          </button>
          <button class="btn btn-primary btn-sm" onclick="openNewForm()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="12" height="12"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Advisory
          </button>
        </div>
        <div class="filter-row" id="severityChips">
          <div class="chip active" data-sev="">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>All
          </div>
          <div class="chip" data-sev="critical">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>Critical
          </div>
          <div class="chip" data-sev="warning">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>Warning
          </div>
          <div class="chip" data-sev="info">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/></svg>Info
          </div>
          <div class="chip" data-sev="resolved">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>Resolved
          </div>
        </div>
      </div>
    </div>

    <!-- Advisories Grid -->
    <div class="advisories-grid" id="advisoriesGrid"></div>
    <div id="emptyState" class="empty-state" style="display:none;">
      <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg></div>
      <h3>No advisories found</h3>
      <p>Adjust your filters or add a new advisory.</p>
    </div>

  </div>
</div>

<!-- Mobile Bottom Nav -->
<div class="mobile-bottom-nav">
  <a href="dashboard.php" class="mobile-nav-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    <span>Home</span>
  </a>
  <a href="bookings.php" class="mobile-nav-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    <span>Bookings</span>
  </a>
  <button class="mobile-nav-item active">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>
    <span>Advisories</span>
  </button>
  <button class="mobile-nav-item" onclick="toggleMobileSidebar()">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    <span>Menu</span>
  </button>
</div>

<!-- DETAIL MODAL -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
  <div class="modal-container" id="modalContainer" onclick="event.stopPropagation()">
    <div class="modal-banner"></div>
    <div class="modal-header">
      <div class="modal-header-left">
        <div class="modal-header-icon" id="modalHeaderIcon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>
        </div>
        <div>
          <div class="modal-title" id="modalTitle">Advisory Details</div>
          <div class="modal-sub" id="modalSub"></div>
        </div>
      </div>
      <button class="modal-close" onclick="document.getElementById('modalOverlay').classList.remove('open')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body" id="modalBody"></div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="document.getElementById('modalOverlay').classList.remove('open')">Close</button>
      <button class="btn btn-primary" id="modalNotifyBtn" onclick="notifyFromModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="13" height="13"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
        Notify Hikers
      </button>
      <button class="btn btn-outline" id="modalResolveBtn" onclick="resolveFromModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="13" height="13"><polyline points="20 6 9 17 4 12"/></svg>
        Mark Resolved
      </button>
    </div>
  </div>
</div>

<!-- NEW ADVISORY FORM -->
<div class="form-overlay" id="formOverlay" onclick="closeForm(event)">
  <div class="form-container" onclick="event.stopPropagation()">
    <div class="form-header">
      <div class="form-title">Post New Advisory</div>
      <button class="modal-close" onclick="document.getElementById('formOverlay').classList.remove('open')" style="background:rgba(255,255,255,0.15);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="form-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Advisory Type</label>
          <select class="form-select" id="newType">
            <option value="weather">🌧 Weather</option>
            <option value="trail">🏔 Trail</option>
            <option value="health">💊 Health</option>
            <option value="capacity">👥 Capacity</option>
            <option value="maintenance">🔧 Maintenance</option>
            <option value="wildlife">🦅 Wildlife</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Severity Level</label>
          <select class="form-select" id="newSeverity">
            <option value="info">ℹ Info</option>
            <option value="warning">⚠ Warning</option>
            <option value="critical">🚨 Critical</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Advisory Title</label>
        <input type="text" class="form-input" id="newTitle" placeholder="e.g. Typhoon Warning — Trail Suspension">
      </div>
      <div class="form-group">
        <label class="form-label">Full Advisory Message</label>
        <textarea class="form-textarea" id="newMessage" placeholder="Describe the advisory in full detail. Include affected areas, instructions for guides and hikers, and any safety precautions..."></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Issued By</label>
          <input type="text" class="form-input" id="newIssuedBy" placeholder="e.g. PAGASA / DENR">
        </div>
        <div class="form-group">
          <label class="form-label">Expires On</label>
          <input type="date" class="form-input" id="newExpires">
        </div>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <h4>Notify affected hikers</h4>
          <p>Send an SMS/app notification to hikers with upcoming bookings.</p>
        </div>
        <label class="toggle-switch">
          <input type="checkbox" id="newNotify" checked>
          <span class="toggle-slider"></span>
        </label>
      </div>
    </div>
    <div class="form-footer">
      <button class="btn btn-outline" onclick="document.getElementById('formOverlay').classList.remove('open')">Cancel</button>
      <button class="btn btn-primary" onclick="submitNewAdvisory()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="13" height="13"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Post Advisory
      </button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const ALL_ADVISORIES = <?= json_encode($advisories) ?>;
let currentAdvisories = [...ALL_ADVISORIES];
let currentAdvisoryId = null;

const TYPE_ICONS = {
  weather: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>`,
  trail: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20"><path d="M8 3l4 8 5-5 5 15H2L8 3z"/></svg>`,
  health: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>`,
  capacity: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`,
  maintenance: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>`,
  wildlife: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20"><circle cx="11" cy="11" r="3"/><path d="M11 14V20M11 8V2M8 11H2M20 11h-6M17.66 6.34l-1.42 1.42M7.76 16.24l-1.42 1.42M17.66 17.66l-1.42-1.42M7.76 7.76L6.34 6.34"/></svg>`
};

const SEV_ICON = {
  critical: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20" stroke-width="1.8"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
  warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
  info: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`,
  resolved: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`
};

function getDaysUntilExpiry(dateStr) {
  if (!dateStr) return null;
  const diff = new Date(dateStr) - new Date();
  return Math.ceil(diff / (1000 * 60 * 60 * 24));
}

function fmtDate(d) {
  return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

function fmtDateTime(d) {
  return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}

function renderAdvisories() {
  const grid = document.getElementById('advisoriesGrid');
  const empty = document.getElementById('emptyState');

  if (currentAdvisories.length === 0) {
    grid.innerHTML = '';
    empty.style.display = 'block';
    return;
  }
  empty.style.display = 'none';

  const sev = (a) => a.status === 'resolved' ? 'resolved' : a.severity;

  grid.innerHTML = currentAdvisories.map((adv, i) => {
    const severity = sev(adv);
    const days = getDaysUntilExpiry(adv.expiresAt);
    const isUrgent = days !== null && days <= 3;

    return `
      <div class="advisory-card ${severity}" style="animation-delay:${i * 0.06}s" onclick="openAdvisory('${adv.id}')">
        <div class="card-banner"></div>
        <div class="card-body">
          <div class="card-top">
            <div class="card-type-icon">${TYPE_ICONS[adv.type] || TYPE_ICONS.trail}</div>
            <div class="card-meta">
              <div class="card-badges">
                <span class="severity-badge ${severity}">
                  <span class="severity-badge-dot"></span>
                  ${severity.toUpperCase()}
                </span>
                <span class="type-badge">${adv.type}</span>
              </div>
              <div class="card-title">${escapeHtml(adv.title)}</div>
              <div class="card-issued">${fmtDateTime(adv.issuedAt)}</div>
            </div>
          </div>
          <div class="card-message">${escapeHtml(adv.message)}</div>
          <div class="card-footer">
            <div class="card-footer-left">
              <div class="card-issuer">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                ${escapeHtml(adv.issuedBy)}
              </div>
              ${adv.affectedBookings.length > 0 ? `
                <div class="affected-bookings">
                  ${adv.affectedBookings.map(b => `<span class="bk-chip">${b}</span>`).join('')}
                </div>
              ` : ''}
              ${adv.notifyHikers ? `
                <div class="notify-indicator">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                  Notified
                </div>
              ` : ''}
            </div>
            ${adv.expiresAt ? `
              <div class="expiry-pill ${isUrgent ? 'urgent' : ''}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                ${days !== null ? (days <= 0 ? 'Expired' : `${days}d left`) : 'No expiry'}
              </div>
            ` : ''}
          </div>
        </div>
        <div class="card-actions">
          <button class="card-action-btn view" onclick="event.stopPropagation();openAdvisory('${adv.id}')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            View
          </button>
          ${adv.status !== 'resolved' ? `
            <button class="card-action-btn notify" onclick="event.stopPropagation();notifyHikers('${adv.id}')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
              Notify
            </button>
            <button class="card-action-btn resolve" onclick="event.stopPropagation();resolveAdvisory('${adv.id}')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
              Resolve
            </button>
          ` : ''}
          <button class="card-action-btn dismiss" onclick="event.stopPropagation();dismissAdvisory('${adv.id}')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
            Remove
          </button>
        </div>
      </div>
    `;
  }).join('');
}

function openAdvisory(id) {
  const adv = ALL_ADVISORIES.find(a => a.id === id) || currentAdvisories.find(a => a.id === id);
  if (!adv) return;
  currentAdvisoryId = id;

  const severity = adv.status === 'resolved' ? 'resolved' : adv.severity;
  const days = getDaysUntilExpiry(adv.expiresAt);

  const modal = document.getElementById('modalContainer');
  modal.className = `modal-container ${severity}`;
  document.getElementById('modalHeaderIcon').innerHTML = SEV_ICON[severity] || SEV_ICON.info;
  document.getElementById('modalTitle').textContent = adv.title;
  document.getElementById('modalSub').textContent = `${adv.id} · ${adv.type.charAt(0).toUpperCase() + adv.type.slice(1)} Advisory · Issued by ${adv.issuedBy}`;

  const notifyBtn = document.getElementById('modalNotifyBtn');
  const resolveBtn = document.getElementById('modalResolveBtn');
  notifyBtn.style.display = adv.status === 'resolved' ? 'none' : '';
  resolveBtn.style.display = adv.status === 'resolved' ? 'none' : '';

  document.getElementById('modalBody').innerHTML = `
    <div class="detail-block">
      <div class="detail-block-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
        Advisory Information
      </div>
      <div class="detail-row">
        <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
        <div>
          <div class="detail-label">Severity</div>
          <div class="detail-value"><span class="severity-badge ${severity}"><span class="severity-badge-dot"></span>${severity.toUpperCase()}</span></div>
        </div>
      </div>
      <div class="detail-row">
        <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div>
          <div class="detail-label">Date Issued</div>
          <div class="detail-value">${fmtDateTime(adv.issuedAt)}</div>
        </div>
      </div>
      ${adv.expiresAt ? `
      <div class="detail-row">
        <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div>
          <div class="detail-label">Expires</div>
          <div class="detail-value" style="color:${days !== null && days <= 3 ? 'var(--critical)' : 'inherit'}">
            ${fmtDate(adv.expiresAt)}${days !== null ? ` (${days <= 0 ? 'Expired' : `${days} day${days !== 1 ? 's' : ''} remaining`})` : ''}
          </div>
        </div>
      </div>` : ''}
      <div class="detail-row">
        <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
        <div>
          <div class="detail-label">Issued By</div>
          <div class="detail-value">${escapeHtml(adv.issuedBy)}</div>
        </div>
      </div>
    </div>

    <div class="detail-block">
      <div class="detail-block-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Full Advisory Message
      </div>
      <div class="detail-value message-text">${escapeHtml(adv.message)}</div>
    </div>

    ${adv.affectedDates && adv.affectedDates.length > 0 ? `
    <div class="detail-block">
      <div class="detail-block-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
        Affected Dates
      </div>
      <div class="dates-grid">
        ${adv.affectedDates.map(d => `<div class="date-chip">${fmtDate(d)}</div>`).join('')}
      </div>
    </div>` : ''}

    ${adv.affectedBookings && adv.affectedBookings.length > 0 ? `
    <div class="detail-block">
      <div class="detail-block-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Affected Bookings (${adv.affectedBookings.length})
      </div>
      <div class="bookings-affected">
        ${adv.affectedBookings.map(b => `
          <div class="bk-detail-chip" onclick="window.location.href='bookings.php?id=${b}'">
            <span class="bk-detail-chip-dot"></span>${b}
          </div>
        `).join('')}
      </div>
    </div>` : ''}

    <div class="detail-block">
      <div class="detail-block-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
        Hiker Notifications
      </div>
      <div class="notify-row">
        <div class="notify-row-left">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">${adv.notifyHikers ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>' : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>'}</svg>
          ${adv.notifyHikers ? 'Hikers have been notified about this advisory.' : 'Hikers have not been notified yet.'}
        </div>
        ${!adv.notifyHikers && adv.status !== 'resolved' ? `<button class="btn btn-sm" style="background:var(--green);color:white;border:none;" onclick="notifyHikers('${adv.id}')">Send Now</button>` : ''}
      </div>
    </div>
  `;

  document.getElementById('modalOverlay').classList.add('open');
}

function closeModal(event) {
  if (event && event.target !== document.getElementById('modalOverlay')) return;
  document.getElementById('modalOverlay').classList.remove('open');
}

function notifyHikers(id) {
  const adv = ALL_ADVISORIES.find(a => a.id === id);
  if (!adv) return;
  adv.notifyHikers = true;
  showToast(`📢 Hikers notified for: ${adv.title}`, 'success');
  renderAdvisories();
  if (document.getElementById('modalOverlay').classList.contains('open')) {
    openAdvisory(id);
  }
}
function notifyFromModal() { if (currentAdvisoryId) notifyHikers(currentAdvisoryId); }

function resolveAdvisory(id) {
  const adv = ALL_ADVISORIES.find(a => a.id === id);
  if (!adv) return;
  adv.status = 'resolved';
  showToast(`✅ Advisory marked as resolved.`, 'success');
  currentAdvisories = [...ALL_ADVISORIES];
  applyFilters();
  document.getElementById('modalOverlay').classList.remove('open');
}
function resolveFromModal() { if (currentAdvisoryId) resolveAdvisory(currentAdvisoryId); }

function dismissAdvisory(id) {
  if (!confirm('Remove this advisory permanently?')) return;
  const idx = ALL_ADVISORIES.findIndex(a => a.id === id);
  if (idx > -1) ALL_ADVISORIES.splice(idx, 1);
  currentAdvisories = [...ALL_ADVISORIES];
  applyFilters();
  showToast('Advisory removed.', 'danger');
}

function applyFilters() {
  const search = document.getElementById('searchInp').value.toLowerCase();
  const type = document.getElementById('filterType').value;
  const activeSevChip = document.querySelector('#severityChips .chip.active');
  const sev = activeSevChip ? activeSevChip.dataset.sev : '';

  currentAdvisories = ALL_ADVISORIES.filter(adv => {
    const advSev = adv.status === 'resolved' ? 'resolved' : adv.severity;
    if (sev && advSev !== sev) return false;
    if (type && adv.type !== type) return false;
    if (search) {
      const text = `${adv.title} ${adv.message} ${adv.issuedBy} ${adv.type} ${adv.id}`.toLowerCase();
      if (!text.includes(search)) return false;
    }
    return true;
  });

  document.getElementById('advisoryCount').textContent = `${currentAdvisories.length} advisor${currentAdvisories.length !== 1 ? 'ies' : 'y'} shown`;
  renderAdvisories();
}

function filterSeverity(sev) {
  document.querySelectorAll('#severityChips .chip').forEach(c => {
    c.classList.toggle('active', c.dataset.sev === sev);
  });
  applyFilters();
  showToast(`Showing ${sev || 'all'} advisories`);
}

function clearFilters() {
  document.getElementById('searchInp').value = '';
  document.getElementById('filterType').value = '';
  document.querySelectorAll('#severityChips .chip').forEach((c, i) => c.classList.toggle('active', i === 0));
  currentAdvisories = [...ALL_ADVISORIES];
  document.getElementById('advisoryCount').textContent = `${currentAdvisories.length} advisories`;
  renderAdvisories();
  showToast('Filters cleared');
}

document.querySelectorAll('#severityChips .chip').forEach(chip => {
  chip.addEventListener('click', () => {
    document.querySelectorAll('#severityChips .chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    applyFilters();
  });
});

function openNewForm() {
  document.getElementById('formOverlay').classList.add('open');
  document.getElementById('newExpires').min = new Date().toISOString().split('T')[0];
}

function closeForm(event) {
  if (event && event.target !== document.getElementById('formOverlay')) return;
  document.getElementById('formOverlay').classList.remove('open');
}

function submitNewAdvisory() {
  const title = document.getElementById('newTitle').value.trim();
  const message = document.getElementById('newMessage').value.trim();
  const issuedBy = document.getElementById('newIssuedBy').value.trim();
  if (!title || !message || !issuedBy) {
    showToast('Please fill in all required fields.', 'danger');
    return;
  }

  const newAdv = {
    id: 'ADV' + String(Date.now()).slice(-4),
    type: document.getElementById('newType').value,
    severity: document.getElementById('newSeverity').value,
    title,
    message,
    affectedDates: [],
    issuedBy,
    issuedAt: new Date().toISOString().replace('T', ' ').slice(0, 19),
    expiresAt: document.getElementById('newExpires').value ? document.getElementById('newExpires').value + ' 00:00:00' : null,
    status: 'active',
    affectedBookings: [],
    notifyHikers: document.getElementById('newNotify').checked,
  };

  ALL_ADVISORIES.unshift(newAdv);
  currentAdvisories = [...ALL_ADVISORIES];
  applyFilters();
  document.getElementById('formOverlay').classList.remove('open');
  document.getElementById('newTitle').value = '';
  document.getElementById('newMessage').value = '';
  document.getElementById('newIssuedBy').value = '';
  showToast(`📋 Advisory "${title}" posted!`, 'success');
}

function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const main = document.getElementById('mainArea');
  sidebar.classList.toggle('collapsed');
  main.classList.toggle('expanded');
}

function toggleMobileSidebar() {
  document.getElementById('sidebar').classList.toggle('mobile-open');
}

function showToast(msg, type = '') {
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.className = `toast ${type} show`;
  setTimeout(() => toast.classList.remove('show'), 3500);
}

setInterval(() => {
  const el = document.getElementById('topbarDate');
  if (el) el.textContent = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}, 1000);

document.addEventListener('click', function(e) {
  const sidebar = document.getElementById('sidebar');
  const menuBtn = e.target.closest('.mobile-nav-item');
  const isMenuBtn = menuBtn && menuBtn.querySelector('span')?.textContent === 'Menu';
  if (!sidebar.contains(e.target) && !isMenuBtn && sidebar.classList.contains('mobile-open')) {
    sidebar.classList.remove('mobile-open');
  }
});

// Init
renderAdvisories();
</script>

</body>
</html>
