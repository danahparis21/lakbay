<?php
// includes/data.php — Shared mock data for LAKBAY

$mountains = [
  [
    'id'          => 1,
    'name'        => 'Mt. Batulao',
    'description' => 'Known for its iconic rolling hills and stunning panoramic views, Mt. Batulao is one of the most popular hiking destinations in Batangas. The trail features a series of steep ascents and descents across grassy ridges.',
    'difficulty'  => 'Intermediate',
    'rating'      => 4.7,
    'location'    => 'Nasugbu, Batangas',
    'image'       => 'https://images.unsplash.com/photo-1613144492511-59e7d7b4b14d?w=800&q=80',
    'jumpOff'     => 'Barangay Evercrest, Nasugbu',
    'duration'    => '3–4 hours',
    'fee'         => 50,
    'elevation'   => '811 MASL',
    'crowdLevel'  => 'High',
    'weatherAdvisory' => 'Clear skies expected. Temperature: 24–28°C. UV Index: High. Bring sun protection.',
    'peakTimes'   => 'Weekends 6AM–9AM. Less crowded on weekdays.',
    'rules'       => ['Register at barangay hall before the hike', 'No littering — practice "Leave No Trace"', 'Follow designated trails only', 'Respect local communities and properties', 'No camping without permission'],
    'envReminders'=> ['Bring reusable water bottles', 'Pack out all trash including biodegradable waste', 'Stay on marked trails to prevent erosion', 'Do not pick plants or disturb wildlife', 'Use eco-friendly sunscreen and insect repellent'],
    'hazards'     => ['Steep and slippery trails during rainy season', 'Limited water sources along the trail', 'Strong winds at the summit', 'Heat exhaustion during peak hours'],
    'reviews'     => [
      ['name' => 'Maria Santos',  'rating' => 5, 'comment' => 'Amazing trail! The views are absolutely breathtaking. Guide was very knowledgeable.', 'date' => '2026-03-15'],
      ['name' => 'John Cruz',     'rating' => 4, 'comment' => 'Great hike but quite challenging for beginners. Make sure to bring enough water.',   'date' => '2026-03-10'],
    ],
  ],
  [
    'id'          => 2,
    'name'        => 'Mt. Apayang',
    'description' => 'A relatively easy climb perfect for beginners, Mt. Apayang offers beautiful views of the surrounding mountains and Taal Lake. The trail passes through pine forests and grasslands.',
    'difficulty'  => 'Beginner',
    'rating'      => 4.3,
    'location'    => 'Nasugbu, Batangas',
    'image'       => 'https://images.unsplash.com/photo-1663439834327-0543fc552131?w=800&q=80',
    'jumpOff'     => 'Barangay Aga, Nasugbu',
    'duration'    => '2–3 hours',
    'fee'         => 30,
    'elevation'   => '530 MASL',
    'crowdLevel'  => 'Moderate',
    'weatherAdvisory' => 'Partly cloudy. Temperature: 23–27°C. Light winds. Good hiking conditions.',
    'peakTimes'   => 'Saturday mornings. Best visited during weekdays for a quieter experience.',
    'rules'       => ['Register at the barangay hall', 'Hire a local guide', 'No open fires', 'Respect private properties', 'Group size limited to 15 persons'],
    'envReminders'=> ['Carry reusable containers', 'Dispose waste properly at designated areas', 'Avoid making loud noises that disturb wildlife', 'Take only photos, leave only footprints', 'Support local communities by buying local products'],
    'hazards'     => ['Rocky terrain in some sections', 'Limited mobile signal', 'Possibility of encountering snakes', 'Flash floods during heavy rain'],
    'reviews'     => [
      ['name' => 'Ana Reyes',     'rating' => 5, 'comment' => 'Perfect for first-time hikers! Beautiful scenery and friendly locals.', 'date' => '2026-03-18'],
      ['name' => 'Carlos Mendez', 'rating' => 4, 'comment' => 'Nice beginner trail. The pine forest section is really relaxing.',     'date' => '2026-03-12'],
    ],
  ],
  [
    'id'          => 3,
    'name'        => 'Mt. Lantik',
    'description' => 'An off-the-beaten-path destination, Mt. Lantik offers tranquility and scenic views of nearby mountains. The trail features a mix of forest and grassland terrain.',
    'difficulty'  => 'Beginner',
    'rating'      => 4.1,
    'location'    => 'Nasugbu, Batangas',
    'image'       => 'https://images.unsplash.com/photo-1659512821649-a2bb1c6ec537?w=800&q=80',
    'jumpOff'     => 'Barangay Papaya, Nasugbu',
    'duration'    => '2–3 hours',
    'fee'         => 30,
    'elevation'   => '450 MASL',
    'crowdLevel'  => 'Low',
    'weatherAdvisory' => 'Cloudy with chance of afternoon showers. Temperature: 22–26°C. Bring rain gear.',
    'peakTimes'   => 'Less crowded overall. Best for those seeking solitude.',
    'rules'       => ['Mandatory registration', 'Local guide required', 'No smoking on the trail', 'Keep noise levels down', 'Stick to marked paths'],
    'envReminders'=> ['Bring eco-bags for trash', 'Use biodegradable soap if washing', 'Minimize single-use plastics', 'Protect water sources from contamination', 'Report any environmental violations'],
    'hazards'     => ['Narrow trails in some areas', 'Thorny vegetation', 'Slippery when wet', 'Limited facilities'],
    'reviews'     => [
      ['name' => 'Lisa Garcia', 'rating' => 4, 'comment' => 'Peaceful trail away from the crowds. Great for meditation and nature appreciation.', 'date' => '2026-03-14'],
    ],
  ],
  [
    'id'          => 4,
    'name'        => 'Mt. Talamitam',
    'description' => 'Often combined with Mt. Batulao, Mt. Talamitam features rolling hills covered with lush vegetation. The summit offers 360-degree views of surrounding mountains and Taal Lake.',
    'difficulty'  => 'Intermediate',
    'rating'      => 4.6,
    'location'    => 'Nasugbu, Batangas',
    'image'       => 'https://images.unsplash.com/photo-1600257729950-13a634d32697?w=800&q=80',
    'jumpOff'     => 'Barangay Kaysuyo, Nasugbu',
    'duration'    => '3–4 hours',
    'fee'         => 50,
    'elevation'   => '630 MASL',
    'crowdLevel'  => 'High',
    'weatherAdvisory' => 'Sunny with scattered clouds. Temperature: 25–29°C. High UV index. Stay hydrated.',
    'peakTimes'   => 'Peak season: November to May. Weekends are busiest.',
    'rules'       => ['Registration required at jump-off point', 'Guides mandatory for first-timers', 'No alcohol on the trail', 'Camping requires special permit', 'Respect wildlife and vegetation'],
    'envReminders'=> ['Practice proper waste segregation', 'Use designated comfort rooms only', 'Avoid picking flowers or plants', 'Keep water sources clean', 'Participate in trail cleanup activities'],
    'hazards'     => ['Steep ascents and descents', 'Exposed ridges with strong winds', 'Possible heat stroke in summer', 'Limited shade in some sections'],
    'reviews'     => [
      ['name' => 'Miguel Torres', 'rating' => 5, 'comment' => 'Challenging but rewarding! The view from the summit is worth every step.', 'date' => '2026-03-16'],
      ['name' => 'Sarah Lim',     'rating' => 4, 'comment' => 'Beautiful trail with diverse scenery. Better to start early to avoid the heat.', 'date' => '2026-03-13'],
    ],
  ],
];

$guides = [
  ['id'=>1,'name'=>'Mang Jose Dela Cruz','mountains'=>[1,4],'rate'=>800,'maxHikers'=>10,'rating'=>4.9,'totalHikes'=>450,'image'=>'','bio'=>'Experienced guide with 15+ years on Mt. Batulao and Talamitam.'],
  ['id'=>2,'name'=>'Kuya Bong Reyes',    'mountains'=>[2,3],'rate'=>600,'maxHikers'=>12,'rating'=>4.7,'totalHikes'=>310,'image'=>'','bio'=>'Specialist in beginner-friendly trails around Apayang and Lantik.'],
  ['id'=>3,'name'=>'Ate Nena Santos',    'mountains'=>[1,2],'rate'=>750,'maxHikers'=>8, 'rating'=>4.8,'totalHikes'=>280,'image'=>'','bio'=>'Certified first-aider and passionate trail conservationist.'],
  ['id'=>4,'name'=>'Kuya Marco Lim',     'mountains'=>[3,4],'rate'=>700,'maxHikers'=>10,'rating'=>4.6,'totalHikes'=>195,'image'=>'','bio'=>'Expert on Mt. Talamitam\'s challenging ridgelines.'],
];

$bookings = [
  ['id'=>'BK001','mountainId'=>1,'guideId'=>1,'user'=>'Ana Reyes',  'date'=>'2026-04-25','hikers'=>4,'status'=>'pending',  'contact'=>'09171234567','details'=>'First-time hikers, need full guidance.'],
  ['id'=>'BK002','mountainId'=>4,'guideId'=>4,'user'=>'Carlos Tan', 'date'=>'2026-04-22','hikers'=>6,'status'=>'approved', 'contact'=>'09281234567','details'=>'Experienced group, want full trail.'],
  ['id'=>'BK003','mountainId'=>2,'guideId'=>2,'user'=>'Maria Cruz', 'date'=>'2026-04-20','hikers'=>2,'status'=>'approved', 'contact'=>'09351234567','details'=>'Casual weekend hike.'],
  ['id'=>'BK004','mountainId'=>1,'guideId'=>3,'user'=>'John Santos','date'=>'2026-04-28','hikers'=>8,'status'=>'pending',  'contact'=>'09461234567','details'=>'Group from Manila, need transport too.'],
  ['id'=>'BK005','mountainId'=>3,'guideId'=>2,'user'=>'Lisa Garcia','date'=>'2026-04-19','hikers'=>3,'status'=>'completed','contact'=>'09571234567','details'=>'Photography hike.'],
];

$posts = [
  ['id'=>1,'user'=>'Ana Reyes', 'mountain'=>'Mt. Apayang','image'=>'https://images.unsplash.com/photo-1663439834327-0543fc552131?w=600&q=80','caption'=>'Beautiful sunrise at Mt. Apayang! The pine forest was absolutely magical.','date'=>'2026-04-18','likes'=>42],
  ['id'=>2,'user'=>'Carlos Tan','mountain'=>'Mt. Batulao','image'=>'https://images.unsplash.com/photo-1613144492511-59e7d7b4b14d?w=600&q=80','caption'=>'Summit reached! Those rolling hills never disappoint. Highly recommend going early.','date'=>'2026-04-17','likes'=>87],
  ['id'=>3,'user'=>'Maria Cruz','mountain'=>'Mt. Talamitam','image'=>'https://images.unsplash.com/photo-1600257729950-13a634d32697?w=600&q=80','caption'=>'360-degree views from Talamitam summit. You can see Taal Lake on a clear day!','date'=>'2026-04-15','likes'=>61],
];

function getDifficultyBadge($d) {
  $map = ['Beginner'=>'badge-sage','Intermediate'=>'badge-moss','Advanced'=>'badge-teal'];
  return '<span class="badge '.($map[$d]??'badge-sky').'">'.$d.'</span>';
}
function getCrowdBadge($c) {
  $map = ['Low'=>'badge-moss','Moderate'=>'badge-yellow','High'=>'badge-red'];
  return '<span class="badge '.($map[$c]??'badge-sky').'">'.$c.' Crowd</span>';
}
function renderStars($r) {
  $full = round($r);
  $html = '<div class="rating-stars">';
  for($i=1;$i<=5;$i++) {
    if($i<=$full) $html .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
    else $html .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f39c12" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
  }
  $html .= '<span style="color:var(--navy);margin-left:0.25rem;">'.$r.'</span></div>';
  return $html;
}
