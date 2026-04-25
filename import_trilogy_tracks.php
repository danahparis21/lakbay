<?php
require_once __DIR__ . '/config/db.php';

// Trilogy mountain ID (assuming it's 5 from your previous insert)
$trilogyMountainId = 5;
$fileId = 'TRILOGY';

// Parse the GPX file content
$gpxContent = '<?xml version="1.0" encoding="UTF-8"?>
<gpx creator="Wikiloc - https://www.wikiloc.com" version="1.1" xmlns="http://www.topografix.com/GPX/1/1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd">
  <metadata>
    <name>Wikiloc - 3 summit hike nasugbu batangas philippines (Mt.lantik Mt.tal...</name>
    <link href="https://www.wikiloc.com/hiking-trails/3-summit-hike-nasugbu-batangas-philippines-mt-lantik-mt-talamitam-mt-apayang-214034035">
      <text>3 summit hike nasugbu batangas philippines (Mt.lantik Mt.tal... on Wikiloc</text>
    </link>
    <time>2025-05-18T02:38:41.308Z</time>
  </metadata>
  <wpt lat="14.107570" lon="120.766684">
    <ele>391.2</ele>
    <time>2025-05-17T22:45:44.365Z</time>
    <name>Mountain pass</name>
  </wpt>
  <wpt lat="14.108911" lon="120.767502">
    <ele>386.6</ele>
    <time>2025-05-17T22:53:39.296Z</time>
    <name>Mountain pass</name>
  </wpt>
  <wpt lat="14.109151" lon="120.767575">
    <ele>382.2</ele>
    <time>2025-05-17T22:57:24.269Z</time>
    <name>Mountain pass</name>
  </wpt>
  <wpt lat="14.109151" lon="120.767575">
    <ele>382.2</ele>
    <time>2025-05-17T22:57:45.296Z</time>
    <name>Mountain pass</name>
  </wpt>
  <wpt lat="14.112649" lon="120.764377">
    <ele>496.2</ele>
    <time>2025-05-17T23:36:21.335Z</time>
    <name>Campsite</name>
  </wpt>
  <wpt lat="14.112649" lon="120.764377">
    <ele>496.2</ele>
    <time>2025-05-17T23:36:46.326Z</time>
    <name>Campsite</name>
  </wpt>
  <wpt lat="14.114857" lon="120.765355">
    <ele>573.8</ele>
    <time>2025-05-17T23:59:40.287Z</time>
    <name>Mt. Lantik summit</name>
  </wpt>
  <wpt lat="14.107809" lon="120.760013">
    <ele>644.0</ele>
    <time>2025-05-18T01:21:05.331Z</time>
    <name>Mt. Talamitam summit</name>
  </wpt>
  <wpt lat="14.105086" lon="120.754163">
    <ele>647.5</ele>
    <time>2025-05-18T01:59:08.266Z</time>
    <name>Mt. Apayang summit</name>
  </wpt>
  <trk>
    <name>3 summit hike nasugbu batangas philippines (Mt.lantik Mt.tal... - Wikiloc</name>
    <trkseg>
      <trkpt lat="14.105756" lon="120.763602"><ele>451.657</ele><time>2025-05-17T22:33:55.294Z</time></trkpt>
      <trkpt lat="14.105970" lon="120.763666"><ele>451.100</ele><time>2025-05-17T22:34:36.333Z</time></trkpt>
      <trkpt lat="14.106086" lon="120.763829"><ele>448.420</ele><time>2025-05-17T22:34:59.355Z</time></trkpt>
      <trkpt lat="14.106268" lon="120.763898"><ele>445.609</ele><time>2025-05-17T22:35:24.337Z</time></trkpt>
      <trkpt lat="14.106456" lon="120.764171"><ele>436.647</ele><time>2025-05-17T22:36:27.338Z</time></trkpt>
      <trkpt lat="14.106659" lon="120.764048"><ele>433.616</ele><time>2025-05-17T22:37:12.346Z</time></trkpt>
      <trkpt lat="14.106719" lon="120.764487"><ele>428.639</ele><time>2025-05-17T22:38:36.346Z</time></trkpt>
      <trkpt lat="14.107029" lon="120.764810"><ele>416.675</ele><time>2025-05-17T22:40:03.355Z</time></trkpt>
      <trkpt lat="14.107121" lon="120.764831"><ele>416.748</ele><time>2025-05-17T22:40:17.349Z</time></trkpt>
      <trkpt lat="14.107215" lon="120.765482"><ele>419.839</ele><time>2025-05-17T22:42:08.343Z</time></trkpt>
      <trkpt lat="14.107373" lon="120.765598"><ele>415.275</ele><time>2025-05-17T22:42:37.357Z</time></trkpt>
      <trkpt lat="14.107536" lon="120.765923"><ele>411.748</ele><time>2025-05-17T22:43:45.333Z</time></trkpt>
      <trkpt lat="14.107522" lon="120.766372"><ele>400.990</ele><time>2025-05-17T22:44:55.330Z</time></trkpt>
      <trkpt lat="14.107599" lon="120.766774"><ele>388.817</ele><time>2025-05-17T22:45:56.345Z</time></trkpt>
      <trkpt lat="14.107556" lon="120.766974"><ele>386.987</ele><time>2025-05-17T22:46:28.362Z</time></trkpt>
      <trkpt lat="14.107701" lon="120.767425"><ele>379.715</ele><time>2025-05-17T22:47:15.360Z</time></trkpt>
      <trkpt lat="14.107741" lon="120.767469"><ele>377.809</ele><time>2025-05-17T22:47:23.347Z</time></trkpt>
      <trkpt lat="14.107778" lon="120.767439"><ele>377.063</ele><time>2025-05-17T22:47:30.353Z</time></trkpt>
      <trkpt lat="14.108010" lon="120.767092"><ele>370.392</ele><time>2025-05-17T22:48:19.358Z</time></trkpt>
      <trkpt lat="14.108231" lon="120.766996"><ele>366.475</ele><time>2025-05-17T22:49:01.375Z</time></trkpt>
      <trkpt lat="14.108444" lon="120.767123"><ele>366.531</ele><time>2025-05-17T22:49:49.339Z</time></trkpt>
      <trkpt lat="14.108564" lon="120.767304"><ele>370.254</ele><time>2025-05-17T22:50:16.353Z</time></trkpt>
      <trkpt lat="14.108738" lon="120.767359"><ele>373.955</ele><time>2025-05-17T22:50:44.370Z</time></trkpt>
      <trkpt lat="14.108831" lon="120.767535"><ele>380.211</ele><time>2025-05-17T22:51:07.351Z</time></trkpt>
      <trkpt lat="14.109062" lon="120.767509"><ele>383.094</ele><time>2025-05-17T22:55:23.367Z</time></trkpt>
      <trkpt lat="14.109446" lon="120.767662"><ele>379.486</ele><time>2025-05-17T22:59:25.366Z</time></trkpt>
      <trkpt lat="14.109591" lon="120.768035"><ele>378.925</ele><time>2025-05-17T23:00:04.346Z</time></trkpt>
      <trkpt lat="14.109781" lon="120.768195"><ele>378.926</ele><time>2025-05-17T23:00:34.349Z</time></trkpt>
      <trkpt lat="14.109732" lon="120.768383"><ele>370.508</ele><time>2025-05-17T23:01:02.361Z</time></trkpt>
      <trkpt lat="14.109960" lon="120.768921"><ele>381.209</ele><time>2025-05-17T23:02:42.369Z</time></trkpt>
      <trkpt lat="14.110345" lon="120.769053"><ele>376.884</ele><time>2025-05-17T23:03:28.358Z</time></trkpt>
      <trkpt lat="14.110571" lon="120.769343"><ele>377.953</ele><time>2025-05-17T23:04:43.344Z</time></trkpt>
      <trkpt lat="14.110531" lon="120.769891"><ele>392.416</ele><time>2025-05-17T23:08:42.290Z</time></trkpt>
      <trkpt lat="14.110708" lon="120.769827"><ele>386.212</ele><time>2025-05-17T23:13:18.372Z</time></trkpt>
      <trkpt lat="14.111091" lon="120.769826"><ele>394.365</ele><time>2025-05-17T23:13:50.337Z</time></trkpt>
      <trkpt lat="14.111662" lon="120.769718"><ele>406.666</ele><time>2025-05-17T23:15:26.343Z</time></trkpt>
      <trkpt lat="14.111786" lon="120.769655"><ele>409.882</ele><time>2025-05-17T23:15:49.373Z</time></trkpt>
      <trkpt lat="14.112025" lon="120.769360"><ele>414.935</ele><time>2025-05-17T23:16:58.298Z</time></trkpt>
      <trkpt lat="14.112079" lon="120.769100"><ele>417.957</ele><time>2025-05-17T23:17:17.361Z</time></trkpt>
      <trkpt lat="14.111965" lon="120.768768"><ele>420.629</ele><time>2025-05-17T23:18:19.362Z</time></trkpt>
      <trkpt lat="14.111990" lon="120.768359"><ele>418.400</ele><time>2025-05-17T23:19:04.362Z</time></trkpt>
      <trkpt lat="14.112091" lon="120.768117"><ele>420.120</ele><time>2025-05-17T23:19:34.365Z</time></trkpt>
      <trkpt lat="14.112068" lon="120.767983"><ele>423.309</ele><time>2025-05-17T23:20:03.361Z</time></trkpt>
      <trkpt lat="14.112451" lon="120.767896"><ele>433.194</ele><time>2025-05-17T23:21:02.367Z</time></trkpt>
      <trkpt lat="14.112336" lon="120.767590"><ele>442.113</ele><time>2025-05-17T23:22:40.363Z</time></trkpt>
      <trkpt lat="14.112406" lon="120.767253"><ele>448.956</ele><time>2025-05-17T23:23:27.379Z</time></trkpt>
      <trkpt lat="14.112408" lon="120.766781"><ele>460.282</ele><time>2025-05-17T23:28:47.307Z</time></trkpt>
      <trkpt lat="14.112509" lon="120.766420"><ele>465.755</ele><time>2025-05-17T23:29:40.300Z</time></trkpt>
      <trkpt lat="14.112724" lon="120.766108"><ele>470.436</ele><time>2025-05-17T23:30:16.336Z</time></trkpt>
      <trkpt lat="14.112703" lon="120.765898"><ele>474.162</ele><time>2025-05-17T23:30:58.337Z</time></trkpt>
      <trkpt lat="14.112559" lon="120.765709"><ele>476.915</ele><time>2025-05-17T23:31:47.295Z</time></trkpt>
      <trkpt lat="14.112468" lon="120.765345"><ele>477.937</ele><time>2025-05-17T23:32:11.330Z</time></trkpt>
      <trkpt lat="14.112650" lon="120.764527"><ele>487.047</ele><time>2025-05-17T23:34:36.365Z</time></trkpt>
      <trkpt lat="14.112566" lon="120.764407"><ele>492.629</ele><time>2025-05-17T23:35:10.366Z</time></trkpt>
      <trkpt lat="14.112589" lon="120.764450"><ele>493.321</ele><time>2025-05-17T23:35:29.380Z</time></trkpt>
      <trkpt lat="14.112650" lon="120.764369"><ele>495.809</ele><time>2025-05-17T23:36:14.370Z</time></trkpt>
      <trkpt lat="14.112640" lon="120.764452"><ele>496.086</ele><time>2025-05-17T23:38:57.369Z</time></trkpt>
      <trkpt lat="14.112814" lon="120.764553"><ele>494.575</ele><time>2025-05-17T23:39:22.341Z</time></trkpt>
      <trkpt lat="14.112594" lon="120.764414"><ele>494.858</ele><time>2025-05-17T23:41:59.276Z</time></trkpt>
      <trkpt lat="14.112598" lon="120.764461"><ele>493.334</ele><time>2025-05-17T23:43:58.302Z</time></trkpt>
      <trkpt lat="14.112983" lon="120.764428"><ele>495.527</ele><time>2025-05-17T23:46:22.356Z</time></trkpt>
      <trkpt lat="14.113589" lon="120.764622"><ele>507.628</ele><time>2025-05-17T23:47:50.349Z</time></trkpt>
      <trkpt lat="14.113714" lon="120.764564"><ele>511.594</ele><time>2025-05-17T23:48:18.364Z</time></trkpt>
      <trkpt lat="14.113938" lon="120.764578"><ele>520.277</ele><time>2025-05-17T23:49:20.345Z</time></trkpt>
      <trkpt lat="14.114118" lon="120.764725"><ele>533.559</ele><time>2025-05-17T23:50:44.357Z</time></trkpt>
      <trkpt lat="14.114342" lon="120.764738"><ele>540.426</ele><time>2025-05-17T23:51:52.350Z</time></trkpt>
      <trkpt lat="14.114551" lon="120.765008"><ele>555.047</ele><time>2025-05-17T23:54:25.362Z</time></trkpt>
      <trkpt lat="14.114815" lon="120.765123"><ele>568.511</ele><time>2025-05-17T23:56:17.369Z</time></trkpt>
      <trkpt lat="14.114876" lon="120.765300"><ele>571.409</ele><time>2025-05-17T23:56:47.362Z</time></trkpt>
      <trkpt lat="14.114803" lon="120.765384"><ele>577.044</ele><time>2025-05-18T00:09:56.329Z</time></trkpt>
      <trkpt lat="14.114863" lon="120.765195"><ele>573.883</ele><time>2025-05-18T00:13:12.347Z</time></trkpt>
      <trkpt lat="14.114694" lon="120.765042"><ele>569.976</ele><time>2025-05-18T00:15:06.352Z</time></trkpt>
      <trkpt lat="14.114539" lon="120.765010"><ele>560.282</ele><time>2025-05-18T00:16:06.341Z</time></trkpt>
      <trkpt lat="14.114450" lon="120.764832"><ele>554.685</ele><time>2025-05-18T00:16:42.318Z</time></trkpt>
      <trkpt lat="14.114331" lon="120.764757"><ele>548.776</ele><time>2025-05-18T00:17:32.338Z</time></trkpt>
      <trkpt lat="14.114050" lon="120.764716"><ele>536.872</ele><time>2025-05-18T00:18:30.334Z</time></trkpt>
      <trkpt lat="14.113934" lon="120.764574"><ele>529.295</ele><time>2025-05-18T00:19:55.334Z</time></trkpt>
      <trkpt lat="14.113707" lon="120.764563"><ele>517.997</ele><time>2025-05-18T00:20:42.345Z</time></trkpt>
      <trkpt lat="14.113583" lon="120.764634"><ele>512.731</ele><time>2025-05-18T00:21:08.345Z</time></trkpt>
      <trkpt lat="14.113472" lon="120.764542"><ele>508.561</ele><time>2025-05-18T00:21:32.337Z</time></trkpt>
      <trkpt lat="14.112889" lon="120.764405"><ele>499.607</ele><time>2025-05-18T00:22:38.332Z</time></trkpt>
      <trkpt lat="14.112306" lon="120.764017"><ele>493.209</ele><time>2025-05-18T00:27:34.344Z</time></trkpt>
      <trkpt lat="14.112293" lon="120.763915"><ele>492.925</ele><time>2025-05-18T00:27:44.324Z</time></trkpt>
      <trkpt lat="14.112144" lon="120.763727"><ele>492.244</ele><time>2025-05-18T00:28:25.327Z</time></trkpt>
      <trkpt lat="14.111964" lon="120.763652"><ele>493.747</ele><time>2025-05-18T00:28:49.318Z</time></trkpt>
      <trkpt lat="14.111634" lon="120.763335"><ele>499.223</ele><time>2025-05-18T00:29:46.327Z</time></trkpt>
      <trkpt lat="14.111527" lon="120.763088"><ele>502.356</ele><time>2025-05-18T00:30:17.317Z</time></trkpt>
      <trkpt lat="14.111442" lon="120.763050"><ele>501.337</ele><time>2025-05-18T00:30:33.334Z</time></trkpt>
      <trkpt lat="14.111382" lon="120.762872"><ele>511.733</ele><time>2025-05-18T00:31:20.318Z</time></trkpt>
      <trkpt lat="14.111153" lon="120.762586"><ele>535.576</ele><time>2025-05-18T00:34:56.273Z</time></trkpt>
      <trkpt lat="14.110795" lon="120.762497"><ele>549.578</ele><time>2025-05-18T00:38:06.325Z</time></trkpt>
      <trkpt lat="14.110803" lon="120.762253"><ele>559.615</ele><time>2025-05-18T00:39:19.312Z</time></trkpt>
      <trkpt lat="14.110611" lon="120.762050"><ele>572.865</ele><time>2025-05-18T00:41:28.321Z</time></trkpt>
      <trkpt lat="14.110563" lon="120.761863"><ele>583.911</ele><time>2025-05-18T00:43:44.277Z</time></trkpt>
      <trkpt lat="14.110614" lon="120.761732"><ele>589.209</ele><time>2025-05-18T00:44:53.324Z</time></trkpt>
      <trkpt lat="14.110492" lon="120.761595"><ele>601.123</ele><time>2025-05-18T00:45:55.321Z</time></trkpt>
      <trkpt lat="14.110434" lon="120.761422"><ele>608.842</ele><time>2025-05-18T00:47:12.315Z</time></trkpt>
      <trkpt lat="14.110167" lon="120.761466"><ele>616.945</ele><time>2025-05-18T00:48:05.325Z</time></trkpt>
      <trkpt lat="14.109732" lon="120.761259"><ele>622.821</ele><time>2025-05-18T00:49:49.325Z</time></trkpt>
      <trkpt lat="14.109445" lon="120.761190"><ele>620.834</ele><time>2025-05-18T00:51:01.332Z</time></trkpt>
      <trkpt lat="14.108999" lon="120.761263"><ele>617.245</ele><time>2025-05-18T00:51:55.325Z</time></trkpt>
      <trkpt lat="14.108663" lon="120.761205"><ele>616.921</ele><time>2025-05-18T00:52:40.329Z</time></trkpt>
      <trkpt lat="14.108386" lon="120.760526"><ele>621.794</ele><time>2025-05-18T00:56:13.313Z</time></trkpt>
      <trkpt lat="14.107835" lon="120.760085"><ele>643.216</ele><time>2025-05-18T00:58:38.317Z</time></trkpt>
      <trkpt lat="14.107883" lon="120.760049"><ele>646.625</ele><time>2025-05-18T01:17:26.318Z</time></trkpt>
      <trkpt lat="14.107811" lon="120.759998"><ele>643.926</ele><time>2025-05-18T01:18:06.311Z</time></trkpt>
      <trkpt lat="14.107837" lon="120.759867"><ele>643.808</ele><time>2025-05-18T01:22:16.336Z</time></trkpt>
      <trkpt lat="14.108071" lon="120.759272"><ele>643.809</ele><time>2025-05-18T01:23:42.335Z</time></trkpt>
      <trkpt lat="14.108034" lon="120.759052"><ele>637.644</ele><time>2025-05-18T01:24:40.341Z</time></trkpt>
      <trkpt lat="14.108006" lon="120.758963"><ele>633.833</ele><time>2025-05-18T01:25:11.335Z</time></trkpt>
      <trkpt lat="14.107787" lon="120.758874"><ele>629.196</ele><time>2025-05-18T01:25:56.348Z</time></trkpt>
      <trkpt lat="14.107444" lon="120.758245"><ele>604.862</ele><time>2025-05-18T01:31:57.292Z</time></trkpt>
      <trkpt lat="14.107405" lon="120.758057"><ele>601.925</ele><time>2025-05-18T01:33:15.287Z</time></trkpt>
      <trkpt lat="14.107235" lon="120.757885"><ele>597.596</ele><time>2025-05-18T01:34:23.277Z</time></trkpt>
      <trkpt lat="14.107328" lon="120.757563"><ele>573.772</ele><time>2025-05-18T01:35:49.338Z</time></trkpt>
      <trkpt lat="14.107266" lon="120.757292"><ele>566.765</ele><time>2025-05-18T01:36:10.328Z</time></trkpt>
      <trkpt lat="14.106821" lon="120.756459"><ele>574.597</ele><time>2025-05-18T01:38:37.330Z</time></trkpt>
      <trkpt lat="14.106663" lon="120.756335"><ele>583.098</ele><time>2025-05-18T01:40:01.338Z</time></trkpt>
      <trkpt lat="14.106292" lon="120.756181"><ele>590.115</ele><time>2025-05-18T01:41:08.333Z</time></trkpt>
      <trkpt lat="14.106117" lon="120.755984"><ele>592.326</ele><time>2025-05-18T01:41:35.334Z</time></trkpt>
      <trkpt lat="14.105811" lon="120.755860"><ele>599.009</ele><time>2025-05-18T01:43:05.271Z</time></trkpt>
      <trkpt lat="14.105488" lon="120.755623"><ele>605.911</ele><time>2025-05-18T01:44:30.328Z</time></trkpt>
      <trkpt lat="14.105500" lon="120.755476"><ele>612.140</ele><time>2025-05-18T01:45:03.334Z</time></trkpt>
      <trkpt lat="14.105365" lon="120.755197"><ele>613.913</ele><time>2025-05-18T01:49:15.334Z</time></trkpt>
      <trkpt lat="14.104930" lon="120.754583"><ele>631.669</ele><time>2025-05-18T01:52:46.349Z</time></trkpt>
      <trkpt lat="14.104935" lon="120.754440"><ele>637.972</ele><time>2025-05-18T01:53:16.341Z</time></trkpt>
      <trkpt lat="14.105084" lon="120.754146"><ele>647.373</ele><time>2025-05-18T01:56:10.322Z</time></trkpt>
      <trkpt lat="14.105127" lon="120.754158"><ele>649.859</ele><time>2025-05-18T02:02:01.334Z</time></trkpt>
      <trkpt lat="14.104951" lon="120.754453"><ele>642.822</ele><time>2025-05-18T02:05:58.339Z</time></trkpt>
      <trkpt lat="14.104966" lon="120.754650"><ele>638.934</ele><time>2025-05-18T02:06:42.319Z</time></trkpt>
      <trkpt lat="14.105315" lon="120.755051"><ele>620.769</ele><time>2025-05-18T02:08:52.327Z</time></trkpt>
      <trkpt lat="14.105505" lon="120.755498"><ele>616.004</ele><time>2025-05-18T02:09:57.308Z</time></trkpt>
      <trkpt lat="14.105519" lon="120.755691"><ele>613.824</ele><time>2025-05-18T02:10:34.321Z</time></trkpt>
      <trkpt lat="14.105743" lon="120.755789"><ele>607.909</ele><time>2025-05-18T02:11:13.325Z</time></trkpt>
      <trkpt lat="14.105797" lon="120.755870"><ele>604.048</ele><time>2025-05-18T02:11:36.307Z</time></trkpt>
      <trkpt lat="14.106044" lon="120.755898"><ele>594.467</ele><time>2025-05-18T02:13:25.279Z</time></trkpt>
      <trkpt lat="14.106248" lon="120.756150"><ele>592.560</ele><time>2025-05-18T02:13:48.296Z</time></trkpt>
      <trkpt lat="14.106675" lon="120.756302"><ele>589.826</ele><time>2025-05-18T02:14:29.314Z</time></trkpt>
      <trkpt lat="14.106816" lon="120.756411"><ele>582.939</ele><time>2025-05-18T02:14:51.320Z</time></trkpt>
      <trkpt lat="14.107375" lon="120.757497"><ele>565.610</ele><time>2025-05-18T02:17:42.283Z</time></trkpt>
      <trkpt lat="14.107235" lon="120.757810"><ele>576.076</ele><time>2025-05-18T02:20:59.281Z</time></trkpt>
      <trkpt lat="14.106696" lon="120.758350"><ele>563.289</ele><time>2025-05-18T02:22:56.301Z</time></trkpt>
      <trkpt lat="14.106762" lon="120.758615"><ele>552.691</ele><time>2025-05-18T02:24:49.300Z</time></trkpt>
      <trkpt lat="14.106501" lon="120.758911"><ele>547.934</ele><time>2025-05-18T02:25:46.307Z</time></trkpt>
      <trkpt lat="14.106541" lon="120.759110"><ele>543.155</ele><time>2025-05-18T02:26:23.278Z</time></trkpt>
      <trkpt lat="14.106492" lon="120.759203"><ele>542.210</ele><time>2025-05-18T02:26:32.307Z</time></trkpt>
      <trkpt lat="14.106336" lon="120.759403"><ele>535.509</ele><time>2025-05-18T02:27:03.294Z</time></trkpt>
      <trkpt lat="14.105947" lon="120.759555"><ele>536.195</ele><time>2025-05-18T02:28:12.332Z</time></trkpt>
      <trkpt lat="14.105857" lon="120.759908"><ele>536.294</ele><time>2025-05-18T02:28:54.317Z</time></trkpt>
      <trkpt lat="14.105853" lon="120.760205"><ele>530.387</ele><time>2025-05-18T02:29:46.297Z</time></trkpt>
      <trkpt lat="14.105722" lon="120.760439"><ele>525.293</ele><time>2025-05-18T02:30:24.308Z</time></trkpt>
      <trkpt lat="14.105724" lon="120.760887"><ele>518.076</ele><time>2025-05-18T02:31:31.315Z</time></trkpt>
      <trkpt lat="14.105593" lon="120.761416"><ele>503.167</ele><time>2025-05-18T02:34:21.320Z</time></trkpt>
      <trkpt lat="14.105635" lon="120.761494"><ele>500.783</ele><time>2025-05-18T02:35:02.315Z</time></trkpt>
      <trkpt lat="14.105518" lon="120.761570"><ele>497.456</ele><time>2025-05-18T02:35:28.322Z</time></trkpt>
      <trkpt lat="14.105405" lon="120.762163"><ele>482.228</ele><time>2025-05-18T02:36:57.315Z</time></trkpt>
      <trkpt lat="14.105491" lon="120.762339"><ele>480.189</ele><time>2025-05-18T02:37:18.323Z</time></trkpt>
      <trkpt lat="14.105136" lon="120.762815"><ele>468.006</ele><time>2025-05-18T02:38:14.320Z</time></trkpt>
      <trkpt lat="14.105044" lon="120.762797"><ele>464.826</ele><time>2025-05-18T02:38:27.336Z</time></trkpt>
      <trkpt lat="14.105025" lon="120.762706"><ele>464.609</ele><time>2025-05-18T02:38:41.308Z</time></trkpt>
    </trkseg>
  </trk>
</gpx>';

// Parse the GPX file
$xml = simplexml_load_string($gpxContent);
if (!$xml) {
    die("Failed to parse GPX file.\n");
}

// Register namespaces
$xml->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');

// Find all track points
$trackPoints = $xml->xpath('//gpx:trkpt');

if (empty($trackPoints)) {
    die("No track points found in GPX file.\n");
}

echo "Found " . count($trackPoints) . " track points.\n";

// First, delete existing trilogy tracks if any
$stmt = $pdo->prepare("DELETE FROM tracks WHERE fileId = ? OR mountain_id = ?");
$stmt->execute([$fileId, $trilogyMountainId]);
echo "Deleted existing trilogy tracks.\n";

// Insert new track points
$stmt = $pdo->prepare("INSERT INTO tracks (fileId, mountain_id, idx, lat, lon, ele) VALUES (?, ?, ?, ?, ?, ?)");

$idx = 0;
foreach ($trackPoints as $point) {
    $lat = (float)$point['lat'];
    $lon = (float)$point['lon'];
    
    // Get elevation
    $ele = null;
    if (isset($point->children('http://www.topografix.com/GPX/1/1')->ele)) {
        $ele = (float)$point->children('http://www.topografix.com/GPX/1/1')->ele;
    }
    
    $stmt->execute([$fileId, $trilogyMountainId, $idx, $lat, $lon, $ele]);
    $idx++;
    
    if ($idx % 50 === 0) {
        echo "Inserted $idx track points...\n";
    }
}

echo "✓ Successfully inserted $idx track points for Mountain Trilogy!\n";

// Also insert waypoints as points of interest
echo "\nInserting waypoints (summits, campsites)...\n";

$waypoints = $xml->xpath('//gpx:wpt');
$wpStmt = $pdo->prepare("
    INSERT INTO trail_waypoints (mountain_id, name, type, latitude, longitude, description, order_index, is_active) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
");

$wpIdx = 0;
foreach ($waypoints as $wp) {
    $lat = (float)$wp['lat'];
    $lon = (float)$wp['lon'];
    $name = (string)$wp->name;
    $ele = isset($wp->ele) ? (float)$wp->ele : null;
    
    // Determine type based on name
    $type = 'viewpoint';
    if (strpos($name, 'summit') !== false) {
        $type = 'summit';
    } elseif (strpos($name, 'Campsite') !== false) {
        $type = 'rest';
    } elseif (strpos($name, 'Mountain pass') !== false) {
        $type = 'viewpoint';
    }
    
    $description = $ele ? "Elevation: {$ele}m" : "Trilogy waypoint";
    
    $wpStmt->execute([$trilogyMountainId, $name, $type, $lat, $lon, $description, $wpIdx]);
    $wpIdx++;
}

echo "✓ Inserted $wpIdx waypoints!\n";
echo "\n🎉 Trilogy tracks import complete!\n";