<?php
$dbHost='localhost';$dbName='yadaki_db';$dbUser='yadaki_dbuser';$dbPass='R4shAd3AbJnQBJCmfWAq';
$pdo=new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

// Enable foreign key checks and delete existing
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$pdo->exec("DELETE FROM part_categories");
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

// Insert parents first
$parents=[
  ['موتور و قطعات موتوری','engine-parts'],
  ['جلوبندی و تعلیق','suspension'],
  ['ترمز','brakes'],
  ['بدنه و شاسی','body-chassis'],
  ['برق و الکترونیک','electrical'],
  ['گیربکس و انتقال قدرت','transmission'],
  ['اگزوز','exhaust'],
  ['سیستم سوخت‌رسانی','fuel-system'],
  ['سیستم خنک‌کننده','cooling'],
  ['لوازم داخلی','interior'],
  ['چراغ و روشنایی','lighting'],
  ['شیشه و آینه','glass-mirrors'],
  ['لاستیک و چرخ','tires-wheels'],
  ['روغن و مایعات','fluids'],
];

$stmt=$pdo->prepare("INSERT INTO part_categories (name,slug,parent_id,sort_order) VALUES (?,?,NULL,?)");
foreach($parents as $i=>$p){$stmt->execute([$p[0],$p[1],$i+1]);}

// Insert children using slug lookups for parent_id
$children=[
  ['پیستون و رینگ','piston-rings','engine-parts',1],
  ['سرسیلندر و واشر','cylinder-head','engine-parts',2],
  ['تسمه تایم','timing-belt','engine-parts',3],
  ['شمع و کوئل','spark-coil','engine-parts',4],
  ['فیلترها','filters','engine-parts',5],
  ['کمک فنر','shock-absorber','suspension',1],
  ['سیبک و بوش','bushings','suspension',2],
  ['فنر و تعلیق','springs','suspension',3],
  ['لنت ترمز','brake-pads','brakes',1],
  ['دیسک ترمز','brake-discs','brakes',2],
  ['پمپ ترمز و بوستر','brake-pump','brakes',3],
  ['کالیپر ترمز','brake-caliper','brakes',4],
  ['سپر و جلوپنجره','bumpers-grille','body-chassis',1],
  ['گلگیر و کاپوت','fenders-hood','body-chassis',2],
  ['درب و قطعات بدنه','doors-body','body-chassis',3],
  ['استارت و دینام','starter-alternator','electrical',1],
  ['باتری و کابل','battery-cables','electrical',2],
  ['سنسورها','sensors','electrical',3],
  ['کلاچ','clutch','transmission',1],
  ['گیربکس اتوماتیک','auto-transmission','transmission',2],
  ['پلوس و دیفرانسیل','axles-differential','transmission',3],
  ['منبع اگزوز','muffler','exhaust',1],
  ['پمپ بنزین','fuel-pump','fuel-system',1],
  ['انژکتور','injector','fuel-system',2],
  ['رادیاتور','radiator','cooling',1],
  ['واتر پمپ','water-pump','cooling',2],
  ['فن خنک‌کننده','cooling-fan','cooling',3],
  ['داشبورد و رودری','dashboard','interior',1],
  ['صندلی و کمربند','seats-belts','interior',2],
  ['چراغ جلو و عقب','headlights-taillights','lighting',1],
  ['شیشه جلو و عقب','windshield','glass-mirrors',1],
  ['آینه بغل','side-mirrors','glass-mirrors',2],
  ['لاستیک','tires','tires-wheels',1],
  ['رینگ و قالپاق','rims-hubcaps','tires-wheels',2],
  ['روغن موتور','engine-oil','fluids',1],
  ['ضدیخ و آب رادیاتور','antifreeze','fluids',2],
];

$st2=$pdo->prepare("INSERT INTO part_categories (name,slug,parent_id,sort_order) SELECT ?,?,id,? FROM part_categories WHERE slug=? AND parent_id IS NULL");
foreach($children as $c){$st2->execute([$c[0],$c[1],$c[3],$c[2]]);}

// Re-assign products
$pdo->exec("UPDATE products SET part_category_id=NULL");

$map=[
'engine-parts'=>['پیستون','رینگ','شاتون','سرسیلندر','واشر','تسمه تایم','تایمینگ','شمع','کوئل','کویل','فیلتر'],
'suspension'=>['کمک فنر','سیبک','بوش','فنر','تعلیق'],
'brakes'=>['لنت','دیسک ترمز','پمپ ترمز','بوستر','کالیپر','ترمز'],
'body-chassis'=>['سپر','جلوپنجره','گلگیر','کاپوت','درب'],
'electrical'=>['استارت','دینام','باتری','کابل','سنسور','برق'],
'transmission'=>['کلاچ','گیربکس','دیفرانسیل','پلوس'],
'exhaust'=>['اگزوز','منبع'],
'fuel-system'=>['پمپ بنزین','انژکتور','افشانک'],
'cooling'=>['رادیاتور','واتر پمپ','فن خنک','خنک کننده'],
'interior'=>['داشبورد','رودری','صندلی','کمربند'],
'lighting'=>['چراغ'],
'glass-mirrors'=>['شیشه','آینه'],
'tires-wheels'=>['لاستیک','تایر','رینگ','قالپاق'],
'fluids'=>['روغن','ضدیخ'],
];

$st3=$pdo->prepare("UPDATE products p SET part_category_id=(SELECT id FROM part_categories WHERE slug=? AND parent_id IS NULL LIMIT 1) WHERE p.name LIKE ? AND p.part_category_id IS NULL");

foreach($map as $slug=>$keywords){
  foreach($keywords as $kw){
    $st3->execute([$slug,'%'.$kw.'%']);
  }
}

// Count results
$total=$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$done=$pdo->query("SELECT COUNT(*) FROM products WHERE part_category_id IS NOT NULL")->fetchColumn();

echo "OK: $done / $total products categorized";
@unlink(__FILE__);