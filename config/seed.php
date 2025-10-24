<?php
require_once __DIR__ . '/db.php';


$db->exec("DELETE FROM coupons;");
$db->exec("DELETE FROM tickets;");
$db->exec("DELETE FROM trips;");
$db->exec("DELETE FROM firms;");
$db->exec("DELETE FROM users;");


$db->exec("INSERT INTO firms(name, description) VALUES
 ('Metro Turizm','Türkiye genelinde seferler'),
 ('Kamil Koç','Geniş otobüs ağı');");

$adminPass = password_hash('Admin123!', PASSWORD_DEFAULT);
$fadminPass = password_hash('Fadmin123!', PASSWORD_DEFAULT);
$userPass  = password_hash('User123!', PASSWORD_DEFAULT);

$st = $db->prepare("INSERT INTO users(name,email,password,role,firm_id,credit) VALUES(?,?,?,?,?,?)");
$st->execute(['Sistem Admin','admin@example.com',$adminPass,'admin',null,0]);
$st->execute(['Ayşe FirmaAdmin','fadmin@example.com',$fadminPass,'firma_admin',1,0]); 
$st->execute(['Burak Kullanıcı','burak@example.com',$userPass,'user',null,1000]);


$st = $db->prepare("INSERT INTO trips(firm_id,from_city,to_city,date,time,price,seat_count) VALUES(?,?,?,?,?,?,?)");
$st->execute([1,'İstanbul','Ankara','2025-10-20','09:30',350,40]);
$st->execute([1,'İstanbul','Ankara','2025-10-20','14:00',380,40]);
$st->execute([2,'İzmir','Bursa','2025-10-21','13:00',280,46]);


$st = $db->prepare("INSERT INTO coupons(code,discount_percent,usage_limit,used_count,expiry_date,firm_id,is_global)
                    VALUES(?,?,?,?,?,?,?)");
$st->execute(['GENEL15',15,500,0,'2025-12-31',null,1]);  
$st->execute(['METRO10',10,200,0,'2025-12-31',1,0]);     

echo "Seed yüklendi\n";
