INSERT INTO routes (route_id, route_code, route_name, vehicle_type, origin, destination, via, structure, authorized_units, max_vehicle_limit, status)
VALUES
('TR-01','TR-01','Monumento Loop','Jeepney','Monumento','Monumento','EDSA • Rizal Ave Ext • Samson Rd • 10th Ave/5th Ave (Caloocan)','Loop',60,60,'Active'),
('TR-02','TR-02','Monumento–Sangandaan Connector','Jeepney','Monumento','Sangandaan','Samson Rd • Sangandaan Area (Caloocan)','Point-to-Point',45,45,'Active'),
('TR-03','TR-03','Deparo–Tala Service','Jeepney','Deparo','Tala','Deparo Rd • Tala Area (Caloocan)','Point-to-Point',40,40,'Active'),
('TR-04','TR-04','Bagong Silang–Camarin Loop','Jeepney','Bagong Silang','Camarin','Bagong Silang • Camarin (Caloocan)','Loop',55,55,'Active'),
('TR-05','TR-05','Bagumbong–Deparo Line','Jeepney','Bagumbong','Deparo','Bagumbong • Deparo (Caloocan)','Point-to-Point',35,35,'Active'),
('TR-06','TR-06','Grace Park–Monumento Shuttle','UV','Grace Park','Monumento','Grace Park • EDSA/Monumento (Caloocan)','Point-to-Point',30,30,'Active'),
('TR-07','TR-07','North Caloocan Loop (Camarin–Deparo)','Jeepney','Camarin','Deparo','Camarin • Bagong Silang • Deparo (Caloocan)','Loop',50,50,'Active'),
('TR-08','TR-08','South Caloocan Loop (Grace Park)','Jeepney','Grace Park','Grace Park','Grace Park • 10th Ave/5th Ave (Caloocan)','Loop',50,50,'Active'),
('JEEP-01','JEEP-01','Monumento–Divisoria','Jeepney','Monumento','Divisoria','Rizal Ave Ext • Avenida • Recto • Divisoria','Point-to-Point',80,80,'Active'),
('JEEP-02','JEEP-02','Monumento–Blumentritt','Jeepney','Monumento','Blumentritt','Rizal Ave Ext • Blumentritt','Point-to-Point',70,70,'Active'),
('JEEP-03','JEEP-03','Monumento–Sta. Cruz','Jeepney','Monumento','Sta. Cruz','Rizal Ave Ext • Avenida • Sta. Cruz','Point-to-Point',70,70,'Active'),
('JEEP-04','JEEP-04','Camarin–Trinoma/SM North','Jeepney','Camarin','SM North EDSA','Camarin Rd • Mindanao Ave • SM North/Trinoma','Point-to-Point',60,60,'Active'),
('JEEP-05','JEEP-05','Novaliches–Tala via Camarin','Jeepney','Novaliches','Tala','Quirino Hwy • Camarin Rd • Tala','Point-to-Point',55,55,'Active'),
('UV-01','UV-01','Deparo–SM North EDSA/C.I.T.','UV','Deparo','SM North EDSA','Deparo • Mindanao Ave • SM North/C.I.T.','Point-to-Point',40,40,'Active'),
('UV-02','UV-02','Deparo–Blumentritt','UV','Deparo','Blumentritt','Deparo • Quirino Hwy • Blumentritt','Point-to-Point',35,35,'Active'),
('UV-03','UV-03','Bagong Silang–SM North/C.I.T.','UV','Bagong Silang','SM North EDSA','Bagong Silang • Zabarte Rd • Mindanao Ave • SM North/C.I.T.','Point-to-Point',45,45,'Active'),
('UV-04','UV-04','Bagong Silang–Cubao','UV','Bagong Silang','Cubao','Bagong Silang • Commonwealth Ave • Cubao','Point-to-Point',40,40,'Active'),
('UV-05','UV-05','Bagumbong–Blumentritt','UV','Bagumbong','Blumentritt','Bagumbong • Quirino Hwy • Blumentritt','Point-to-Point',30,30,'Active'),
('BUS-01','BUS-01','EDSA Carousel (Monumento–PITX)','Bus','Monumento','PITX','EDSA Busway stops: Monumento • Bagong Barrio • Balintawak • North Ave/SM North • Quezon Ave • Ortigas • Guadalupe • One Ayala • Taft • MOA • PITX','Point-to-Point',120,120,'Active'),
('BUS-02','BUS-02','Bagong Silang–NAIA via Maligaya Park/EDSA','Bus','Bagong Silang','NAIA','Bagong Silang • Maligaya Park • Commonwealth Ave • EDSA • NAIA','Point-to-Point',60,60,'Active'),
('BUS-03','BUS-03','Baclaran–Bagong Silang via EDSA/Commonwealth','Bus','Baclaran','Bagong Silang','Baclaran • EDSA • Commonwealth Ave • Bagong Silang','Point-to-Point',60,60,'Active'),
('BUS-04','BUS-04','Bagong Silang–Lawton (Valariano Fugoso)','Bus','Bagong Silang','Lawton','Bagong Silang • Commonwealth Ave • Quezon Ave • Manila City Hall • Lawton','Point-to-Point',60,60,'Active'),
('BUS-05','BUS-05','Monumento–SM Fairview via EDSA/Commonwealth','Bus','Monumento','SM Fairview','Monumento • EDSA • Quezon Ave • Commonwealth Ave • SM Fairview','Point-to-Point',80,80,'Active'),
('UV-06','UV-06','Novaliches–Monumento','UV','Novaliches','Monumento','Quirino Hwy • Novaliches Bayan • Monumento','Point-to-Point',45,45,'Active'),
('UV-07','UV-07','Novaliches–Trinoma/North Ave','UV','Novaliches','Trinoma','Quirino Hwy • Mindanao Ave • North Ave/Trinoma','Point-to-Point',45,45,'Active'),
('UV-08','UV-08','Robinsons Novaliches–Buendia','UV','Robinsons Novaliches','Buendia','Quirino Hwy • Commonwealth Ave • Quezon Ave • Buendia','Point-to-Point',55,55,'Active'),
('UV-09','UV-09','Novaliches–Cubao Farmers','UV','Novaliches','Cubao','Quirino Hwy • Commonwealth Ave • Cubao','Point-to-Point',45,45,'Active'),
('JEEP-06','JEEP-06','Bagong Silang–Philcoa via Commonwealth','Jeepney','Bagong Silang','Philcoa','Bagong Silang • Zabarte Rd • Commonwealth Ave • Philcoa','Point-to-Point',70,70,'Active'),
('JEEP-07','JEEP-07','Bagong Silang–SM Fairview via Zabarte','Jeepney','Bagong Silang','SM Fairview','Bagong Silang • Zabarte Rd • SM Fairview','Point-to-Point',65,65,'Active'),
('JEEP-08','JEEP-08','Bagong Silang–Novaliches Bayan','Jeepney','Bagong Silang','Novaliches','Bagong Silang • Quirino Hwy • Novaliches Bayan','Point-to-Point',65,65,'Active'),
('JEEP-09','JEEP-09','Balintawak–Camarin via Susano','Jeepney','Balintawak','Camarin','Balintawak • Susano Rd • Camarin Rd','Point-to-Point',70,70,'Active'),
('TRI-01','TRI-01','Bagong Silang Tricycle Loop (Phase 1–5A)','Tricycle','Bagong Silang','Bagong Silang','Phase 1 • Phase 3 • Phase 5A • Langit Rd (Bagong Silang)','Loop',250,250,'Active'),
('TRI-02','TRI-02','Tala–Camarin Tricycle Feeder','Tricycle','Tala','Camarin','Tala • Camarin Rd • Zabarte Rd junctions','Point-to-Point',200,200,'Active'),
('TRI-03','TRI-03','Grace Park Tricycle Loop (10th/5th Ave)','Tricycle','Grace Park','Grace Park','10th Ave • 5th Ave • Grace Park areas','Loop',180,180,'Active')
ON DUPLICATE KEY UPDATE
  route_code=VALUES(route_code),
  route_name=VALUES(route_name),
  vehicle_type=VALUES(vehicle_type),
  origin=VALUES(origin),
  destination=VALUES(destination),
  via=VALUES(via),
  structure=VALUES(structure),
  authorized_units=VALUES(authorized_units),
  max_vehicle_limit=VALUES(max_vehicle_limit),
  status=VALUES(status);

INSERT INTO terminals (name, location, city, address, capacity, type)
SELECT 'MCU/Monumento Terminal','Monumento','Caloocan City','Rizal Ave Ext / EDSA (near LRT-1 Monumento)',800,'Terminal'
WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='MCU/Monumento Terminal');
INSERT INTO terminals (name, location, city, address, capacity, type)
SELECT 'Bagong Barrio Terminal','Bagong Barrio','Caloocan City','EDSA Bagong Barrio',500,'Terminal'
WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Bagong Barrio Terminal');
INSERT INTO terminals (name, location, city, address, capacity, type)
SELECT 'Sangandaan Terminal','Sangandaan','Caloocan City','Samson Rd / Sangandaan',250,'Terminal'
WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Sangandaan Terminal');
INSERT INTO terminals (name, location, city, address, capacity, type)
SELECT 'Grace Park Terminal','Grace Park','Caloocan City','10th Ave / 5th Ave area',300,'Terminal'
WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Grace Park Terminal');
INSERT INTO terminals (name, location, city, address, capacity, type)
SELECT 'Camarin Terminal','Camarin','Caloocan City','Camarin Rd / Zabarte Rd area',400,'Terminal'
WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Camarin Terminal');
INSERT INTO terminals (name, location, city, address, capacity, type)
SELECT 'Deparo Terminal','Deparo','Caloocan City','Deparo Rd area',350,'Terminal'
WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Deparo Terminal');
INSERT INTO terminals (name, location, city, address, capacity, type)
SELECT 'Tala Terminal','Tala','Caloocan City','Tala area / Dr. Jose N. Rodriguez Memorial Hospital vicinity',250,'Terminal'
WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Tala Terminal');
INSERT INTO terminals (name, location, city, address, capacity, type)
SELECT 'Bagong Silang Terminal','Bagong Silang','Caloocan City','Zabarte Rd / Phase terminals',600,'Terminal'
WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Bagong Silang Terminal');
INSERT INTO terminals (name, location, city, address, capacity, type)
SELECT 'Novaliches Bayan Terminal','Novaliches','Caloocan City','Quirino Highway / Novaliches Bayan',500,'Terminal'
WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Novaliches Bayan Terminal');
INSERT INTO terminals (name, location, city, address, capacity, type)
SELECT 'MCU/Monumento Parking','Monumento','Caloocan City','Rizal Ave Ext / EDSA (near LRT-1 Monumento)',200,'Parking'
WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='MCU/Monumento Parking');
INSERT INTO terminals (name, location, city, address, capacity, type)
SELECT 'Bagong Silang Parking','Bagong Silang','Caloocan City','Zabarte Rd / Bagong Silang',150,'Parking'
WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Bagong Silang Parking');
INSERT INTO terminals (name, location, city, address, capacity, type)
SELECT 'Grace Park Parking','Grace Park','Caloocan City','10th Ave / 5th Ave area',120,'Parking'
WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Grace Park Parking');

CREATE TABLE IF NOT EXISTS terminal_routes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  terminal_id INT NOT NULL,
  route_id VARCHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_terminal_route (terminal_id, route_id),
  INDEX idx_terminal (terminal_id),
  INDEX idx_route (route_id),
  FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TR-01' FROM terminals t WHERE t.name='MCU/Monumento Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TR-02' FROM terminals t WHERE t.name='MCU/Monumento Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'JEEP-01' FROM terminals t WHERE t.name='MCU/Monumento Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'JEEP-02' FROM terminals t WHERE t.name='MCU/Monumento Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'JEEP-03' FROM terminals t WHERE t.name='MCU/Monumento Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TR-06' FROM terminals t WHERE t.name='MCU/Monumento Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'BUS-01' FROM terminals t WHERE t.name='MCU/Monumento Terminal';

INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'BUS-01' FROM terminals t WHERE t.name='Bagong Barrio Terminal';

INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TR-02' FROM terminals t WHERE t.name='Sangandaan Terminal';

INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TR-06' FROM terminals t WHERE t.name='Grace Park Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TR-08' FROM terminals t WHERE t.name='Grace Park Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TRI-03' FROM terminals t WHERE t.name='Grace Park Terminal';

INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TR-04' FROM terminals t WHERE t.name='Camarin Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TR-07' FROM terminals t WHERE t.name='Camarin Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'JEEP-04' FROM terminals t WHERE t.name='Camarin Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'JEEP-09' FROM terminals t WHERE t.name='Camarin Terminal';

INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TR-03' FROM terminals t WHERE t.name='Deparo Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TR-05' FROM terminals t WHERE t.name='Deparo Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'UV-01' FROM terminals t WHERE t.name='Deparo Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'UV-02' FROM terminals t WHERE t.name='Deparo Terminal';

INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TR-03' FROM terminals t WHERE t.name='Tala Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'JEEP-05' FROM terminals t WHERE t.name='Tala Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TRI-02' FROM terminals t WHERE t.name='Tala Terminal';

INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TR-04' FROM terminals t WHERE t.name='Bagong Silang Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'JEEP-06' FROM terminals t WHERE t.name='Bagong Silang Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'JEEP-07' FROM terminals t WHERE t.name='Bagong Silang Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'JEEP-08' FROM terminals t WHERE t.name='Bagong Silang Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'UV-03' FROM terminals t WHERE t.name='Bagong Silang Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'UV-04' FROM terminals t WHERE t.name='Bagong Silang Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'BUS-02' FROM terminals t WHERE t.name='Bagong Silang Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'BUS-03' FROM terminals t WHERE t.name='Bagong Silang Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'TRI-01' FROM terminals t WHERE t.name='Bagong Silang Terminal';

INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'JEEP-05' FROM terminals t WHERE t.name='Novaliches Bayan Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'UV-06' FROM terminals t WHERE t.name='Novaliches Bayan Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'UV-07' FROM terminals t WHERE t.name='Novaliches Bayan Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'UV-08' FROM terminals t WHERE t.name='Novaliches Bayan Terminal';
INSERT IGNORE INTO terminal_routes (terminal_id, route_id)
SELECT t.id, 'UV-09' FROM terminals t WHERE t.name='Novaliches Bayan Terminal';
