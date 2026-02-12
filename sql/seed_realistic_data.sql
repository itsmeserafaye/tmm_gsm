-- 1. Insert Realistic Cooperatives (Seed Data)
-- Note: 'operators' table has a 'coop_name' field, but there is also a 'coops' table.
-- We will populate 'coops' table first for reference, then use names in 'operators'.

INSERT INTO coops (coop_name, address, chairperson_name, lgu_approval_number) VALUES
('Caloocan North Transport Cooperative', 'Phase 8, Bagong Silang, Caloocan City', 'Roberto Dela Cruz', 'LGU-CAL-2024-001'),
('Novaliches-Blumentritt Transport Coop', 'Quirino Highway, Novaliches, QC', 'Antonio Reyes', 'LGU-QC-2024-055'),
('Monumento-Malabon Jeepney Operators Coop', 'Samson Road, Caloocan City', 'Danilo Santos', 'LGU-CAL-2024-002'),
('United Transport Service Cooperative', 'Edsa, Balintawak, Caloocan City', 'Eduardo Manalo', 'LGU-CAL-2024-003'),
('Deparo UV Express Service Cooperative', 'Deparo Road, Caloocan City', 'Ricardo Dantes', 'LGU-CAL-2024-004'),
('Bagumbong Tricycle Operators and Drivers Association (TODA)', 'Bagumbong Dulo, Caloocan City', 'Felipe Garcia', 'LGU-CAL-TR-001'),
('Camarin-Almar Transport Coop', 'Zapote Road, Camarin, Caloocan City', 'Gregorio Diaz', 'LGU-CAL-2024-005'),
('Victory Liner Inc.', 'Rizal Avenue Ext, Caloocan City', 'Johnny Hernandez', 'LGU-CAL-BUS-001'),
('Baliwag Transit Inc.', '2nd Ave, Grace Park, Caloocan City', 'Victoria Tengco', 'LGU-CAL-BUS-002');

-- 2. Insert Operators (50 Records)
INSERT INTO operators (full_name, name, registered_name, contact_info, contact_no, email, address, coop_name, operator_type, status, verification_status, workflow_status) VALUES
-- Bus Operators (Corporations)
('Victory Liner Inc.', 'Victory Liner', 'Victory Liner Incorporated', '0917-555-1001', '0917-555-1001', 'admin@victoryliner.com', '713 Rizal Avenue Ext, Brgy 72, Caloocan City', NULL, 'Corporation', 'Approved', 'Verified', 'Active'),
('Baliwag Transit Inc.', 'Baliwag Transit', 'Baliwag Transit Incorporated', '0918-555-2002', '0918-555-2002', 'ops@baliwagtransit.com', '2nd Avenue, Grace Park, Caloocan City', NULL, 'Corporation', 'Approved', 'Verified', 'Active'),
('First North Luzon Transit', 'First North Luzon', 'First North Luzon Transit Inc.', '0919-555-3003', '0919-555-3003', 'info@fnlt.com', 'EDSA, Cubao, QC', NULL, 'Corporation', 'Approved', 'Verified', 'Active'),

-- Coop Chairpersons / Key Operators
('Roberto Dela Cruz', 'Roberto Dela Cruz', 'Roberto S. Dela Cruz', '0920-123-4567', '0920-123-4567', 'robert.dc@gmail.com', 'Lot 5 Blk 2, Bagong Silang, Caloocan', 'Caloocan North Transport Cooperative', 'Cooperative', 'Approved', 'Verified', 'Active'),
('Antonio Reyes', 'Antonio Reyes', 'Antonio M. Reyes', '0921-234-5678', '0921-234-5678', 'antonio.reyes@yahoo.com', '12 Quirino Hi-way, Novaliches', 'Novaliches-Blumentritt Transport Coop', 'Cooperative', 'Approved', 'Verified', 'Active'),
('Danilo Santos', 'Danilo Santos', 'Danilo P. Santos', '0922-345-6789', '0922-345-6789', 'dan.santos@outlook.com', '45 Samson Road, Caloocan City', 'Monumento-Malabon Jeepney Operators Coop', 'Cooperative', 'Approved', 'Verified', 'Active'),

-- Individual Jeepney Operators
('Juan Paulo Dizon', 'Juan Dizon', 'Juan Paulo A. Dizon', '0917-111-2222', '0917-111-2222', 'jdizon@gmail.com', '123 A. Mabini St, Caloocan', 'Monumento-Malabon Jeepney Operators Coop', 'Individual', 'Approved', 'Verified', 'Active'),
('Maria Clara Bautista', 'Maria Bautista', 'Maria Clara T. Bautista', '0917-222-3333', '0917-222-3333', 'mbautista@gmail.com', '456 10th Ave, Caloocan', 'United Transport Service Cooperative', 'Individual', 'Approved', 'Verified', 'Active'),
('Jose Rizalino', 'Jose Rizalino', 'Jose P. Rizalino', '0917-333-4444', '0917-333-4444', 'jrizalino@yahoo.com', '789 C-3 Road, Caloocan', 'United Transport Service Cooperative', 'Individual', 'Approved', 'Verified', 'Active'),
('Andres Bonifacio Jr.', 'Andres Bonifacio', 'Andres L. Bonifacio Jr.', '0917-444-5555', '0917-444-5555', 'abonifacio@gmail.com', '321 EDSA, Balintawak', 'Caloocan North Transport Cooperative', 'Individual', 'Approved', 'Verified', 'Active'),
('Emilio Aguinaldo IV', 'Emilio Aguinaldo', 'Emilio S. Aguinaldo IV', '0917-555-6666', '0917-555-6666', 'emilio.ag@gmail.com', '654 Camarin Road, Caloocan', 'Camarin-Almar Transport Coop', 'Individual', 'Approved', 'Verified', 'Active'),
('Apolinario Mabini Desc', 'Pol Mabini', 'Apolinario V. Mabini', '0917-666-7777', '0917-666-7777', 'pol.mabini@hotmail.com', '987 Zapote Rd, Caloocan', 'Camarin-Almar Transport Coop', 'Individual', 'Approved', 'Verified', 'Active'),
('Gabriela Silang', 'Gabriela Silang', 'Gabriela D. Silang', '0917-777-8888', '0917-777-8888', 'gabs.silang@gmail.com', '159 Bagumbong, Caloocan', 'Caloocan North Transport Cooperative', 'Individual', 'Approved', 'Verified', 'Active'),
('Melchora Aquino', 'Tandang Sora', 'Melchora T. Aquino', '0917-888-9999', '0917-888-9999', 'melchora@gmail.com', '753 Tandang Sora St, QC', 'Novaliches-Blumentritt Transport Coop', 'Individual', 'Approved', 'Verified', 'Active'),
('Gregorio Del Pilar', 'Goyo Pilar', 'Gregorio H. Del Pilar', '0917-999-0000', '0917-999-0000', 'goyong@gmail.com', '357 Bulacan St, Gagalangin', 'Monumento-Malabon Jeepney Operators Coop', 'Individual', 'Approved', 'Verified', 'Active'),
('Diego Silang', 'Diego Silang', 'Diego A. Silang', '0917-000-1111', '0917-000-1111', 'diego.silang@yahoo.com', '246 Dagat-Dagatan, Caloocan', 'Monumento-Malabon Jeepney Operators Coop', 'Individual', 'Approved', 'Verified', 'Active'),

-- UV Express Operators
('Ricardo Dantes', 'Ricardo Dantes', 'Ricardo F. Dantes', '0917-123-1234', '0917-123-1234', 'rdantes@deparocoop.com', 'Deparo Rd, Caloocan', 'Deparo UV Express Service Cooperative', 'Cooperative', 'Approved', 'Verified', 'Active'),
('Marian Rivera', 'Marian Rivera', 'Maria Gracia D. Rivera', '0917-234-2345', '0917-234-2345', 'marian.r@gmail.com', 'Vicas, Caloocan City', 'Deparo UV Express Service Cooperative', 'Individual', 'Approved', 'Verified', 'Active'),
('Dingdong Dantes', 'Dingdong Dantes', 'Jose Sixto G. Dantes', '0917-345-3456', '0917-345-3456', 'dong.d@gmail.com', 'Amparo Subd, Caloocan', 'Deparo UV Express Service Cooperative', 'Individual', 'Approved', 'Verified', 'Active'),
('Coco Martin', 'Coco Martin', 'Rodel P. Nacianceno', '0917-456-4567', '0917-456-4567', 'coco.m@gmail.com', 'Palmerola, Caloocan', 'Deparo UV Express Service Cooperative', 'Individual', 'Approved', 'Verified', 'Active'),
('Julia Montes', 'Julia Montes', 'Mara Hautea Schnittka', '0917-567-5678', '0917-567-5678', 'julia.m@gmail.com', 'Novaliches Bayan', 'Novaliches-Blumentritt Transport Coop', 'Individual', 'Approved', 'Verified', 'Active'),

-- Tricycle Operators (Individual, usually TODA members)
('Pedro Penduko', 'Pedro Penduko', 'Pedro A. Penduko', '0918-111-1111', '0918-111-1111', NULL, 'Bagumbong, Caloocan', 'Bagumbong Tricycle Operators and Drivers Association (TODA)', 'Individual', 'Approved', 'Verified', 'Active'),
('Juan Tamad', 'Juan Tamad', 'Juan B. Tamad', '0918-222-2222', '0918-222-2222', NULL, 'Bagumbong, Caloocan', 'Bagumbong Tricycle Operators and Drivers Association (TODA)', 'Individual', 'Approved', 'Verified', 'Active'),
('Enteng Kabisote', 'Enteng Kabisote', 'Vicente M. Kabisote', '0918-333-3333', '0918-333-3333', NULL, 'Deparo, Caloocan', 'Deparo Tricycle Terminal', 'Individual', 'Approved', 'Verified', 'Active'),
('Agimat Agila', 'Agimat', 'Ramon B. Revilla', '0918-444-4444', '0918-444-4444', NULL, 'Camarin, Caloocan', 'Camarin Tricycle Terminal', 'Individual', 'Approved', 'Verified', 'Active'),
('Panday', 'Panday', 'Flavio Batungbakal', '0918-555-5555', '0918-555-5555', NULL, 'Tala, Caloocan', 'Tala Tricycle Terminal', 'Individual', 'Approved', 'Verified', 'Active'),

-- Additional Mixed
('Liza Soberano', 'Liza Soberano', 'Hope Elizabeth Soberano', '0919-123-9876', '0919-123-9876', 'liza.s@gmail.com', 'Grace Park, Caloocan', 'United Transport Service Cooperative', 'Individual', 'Approved', 'Verified', 'Active'),
('Enrique Gil', 'Enrique Gil', 'Enrique Mari Gil', '0919-234-8765', '0919-234-8765', 'quen.g@gmail.com', 'Grace Park, Caloocan', 'United Transport Service Cooperative', 'Individual', 'Approved', 'Verified', 'Active'),
('Kathryn Bernardo', 'Kathryn Bernardo', 'Kathryn Chandria Bernardo', '0919-345-7654', '0919-345-7654', 'kath.b@gmail.com', 'Sangandaan, Caloocan', 'Monumento-Malabon Jeepney Operators Coop', 'Individual', 'Approved', 'Verified', 'Active'),
('Daniel Padilla', 'Daniel Padilla', 'Daniel John Ford Padilla', '0919-456-6543', '0919-456-6543', 'dj.p@gmail.com', 'Sangandaan, Caloocan', 'Monumento-Malabon Jeepney Operators Coop', 'Individual', 'Approved', 'Verified', 'Active'),
('James Reid', 'James Reid', 'Robert James Reid', '0919-567-5432', '0919-567-5432', 'james.r@gmail.com', '5th Ave, Caloocan', 'United Transport Service Cooperative', 'Individual', 'Approved', 'Verified', 'Active');


-- 3. Insert Vehicles (Linked to Operators and Routes)
-- Note: 'operator_id' references the IDs generated above. Assuming IDs 1-30 sequentially.
-- Note: 'route_id' references 'route_id' string from routes table (e.g., 'JEEP-10')

INSERT INTO vehicles (plate_number, vehicle_type, operator_id, operator_name, coop_name, route_id, make, model, year_model, engine_no, chassis_no, fuel_type, record_status, status, compliance_status) VALUES
-- Victory Liner Buses (Op ID 1) - Route: BUS-VICTORY-OLONGAPO, BUS-VICTORY-BAGUIO
('NBH-1234', 'Bus', 1, 'Victory Liner Inc.', NULL, 'BUS-VICTORY-OLONGAPO', 'Yutong', 'ZK6129H', '2023', 'YC6L330-42-1001', 'L012345678901', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('NBH-1235', 'Bus', 1, 'Victory Liner Inc.', NULL, 'BUS-VICTORY-OLONGAPO', 'Yutong', 'ZK6129H', '2023', 'YC6L330-42-1002', 'L012345678902', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('NBH-1236', 'Bus', 1, 'Victory Liner Inc.', NULL, 'BUS-VICTORY-BAGUIO', 'Higer', 'KLQ6129G', '2022', 'ISL8.9E5-2001', 'H98765432101', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('NBH-1237', 'Bus', 1, 'Victory Liner Inc.', NULL, 'BUS-VICTORY-BAGUIO', 'Higer', 'KLQ6129G', '2022', 'ISL8.9E5-2002', 'H98765432102', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('NBH-1238', 'Bus', 1, 'Victory Liner Inc.', NULL, 'BUS-VICTORY-TUGUEGARAO', 'Hyundai', 'Universe', '2024', 'D6CC-3001', 'K11223344556', 'Diesel', 'Linked', 'Declared/linked', 'Active'),

-- Baliwag Transit Buses (Op ID 2) - Route: BUS-BALIWAG-BALIWAG
('BAL-2001', 'Bus', 2, 'Baliwag Transit Inc.', NULL, 'BUS-BALIWAG-BALIWAG', 'Hino', 'RM2P', '2021', 'P11C-4001', 'J22334455667', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('BAL-2002', 'Bus', 2, 'Baliwag Transit Inc.', NULL, 'BUS-BALIWAG-BALIWAG', 'Hino', 'RM2P', '2021', 'P11C-4002', 'J22334455668', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('BAL-2003', 'Bus', 2, 'Baliwag Transit Inc.', NULL, 'BUS-BALIWAG-CABANATUAN', 'Nissan', 'Diesel UD', '2020', 'PF6-5001', 'N33445566778', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('BAL-2004', 'Bus', 2, 'Baliwag Transit Inc.', NULL, 'BUS-BALIWAG-GAPAN', 'Daewoo', 'BV115', '2019', 'DE12T-6001', 'D44556677889', 'Diesel', 'Linked', 'Declared/linked', 'Active'),

-- Carousel Buses (Assigned to Op 3 - First North Luzon)
('CAR-3001', 'Bus', 3, 'First North Luzon', NULL, 'BUS-CAROUSEL-MONUMENTO-PITX', 'Volvo', 'B7R', '2023', 'D7E-7001', 'V55667788990', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('CAR-3002', 'Bus', 3, 'First North Luzon', NULL, 'BUS-CAROUSEL-MONUMENTO-PITX', 'Volvo', 'B7R', '2023', 'D7E-7002', 'V55667788991', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('CAR-3003', 'Bus', 3, 'First North Luzon', NULL, 'BUS-CAROUSEL-MONUMENTO-PITX', 'Ankai', 'HFF6100', '2022', 'WP10-8001', 'A66778899001', 'Diesel', 'Linked', 'Declared/linked', 'Active'),

-- Traditional Jeepneys (Individual Operators)
-- Op 7: Juan Dizon (Monumento-Malabon)
('PUJ-101', 'Jeepney', 7, 'Juan Paulo Dizon', 'Monumento-Malabon Jeepney Operators Coop', 'JEEP-16', 'Sarao', 'Traditional', '2005', '4BC2-9001', 'S77889900112', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('PUJ-102', 'Jeepney', 7, 'Juan Paulo Dizon', 'Monumento-Malabon Jeepney Operators Coop', 'JEEP-16', 'Sarao', 'Traditional', '2008', '4BC2-9002', 'S77889900113', 'Diesel', 'Linked', 'Declared/linked', 'Active'),

-- Op 8: Maria Bautista (Sangandaan-Divisoria) - Route: JEEP-SANGANDAAN-DIVISORIA
('PUJ-201', 'Jeepney', 8, 'Maria Clara Bautista', 'United Transport Service Cooperative', 'JEEP-SANGANDAAN-DIVISORIA', 'Francisco', 'Traditional', '2010', '4D30-1001', 'F88990011223', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('PUJ-202', 'Jeepney', 8, 'Maria Clara Bautista', 'United Transport Service Cooperative', 'JEEP-SANGANDAAN-DIVISORIA', 'Francisco', 'Traditional', '2011', '4D30-1002', 'F88990011224', 'Diesel', 'Linked', 'Declared/linked', 'Active'),

-- Op 9: Jose Rizalino (Sangandaan-Recto) - Route: JEEP-SANGANDAAN-RECTO
('PUJ-301', 'Jeepney', 9, 'Jose Rizalino', 'United Transport Service Cooperative', 'JEEP-SANGANDAAN-RECTO', 'Morales', 'Traditional', '2009', '4DR5-2001', 'M99001122334', 'Diesel', 'Linked', 'Declared/linked', 'Active'),

-- Op 10: Andres Bonifacio (Bagumbong-Novaliches) - Route: JEEP-BAGUMBONG-NOVALICHES_BAYAN
('PUJ-401', 'Jeepney', 10, 'Andres Bonifacio Jr.', 'Caloocan North Transport Cooperative', 'JEEP-BAGUMBONG-NOVALICHES_BAYAN', 'Armak', 'Modern Jeep', '2023', 'ISF2.8-3001', 'A00112233445', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('PUJ-402', 'Jeepney', 10, 'Andres Bonifacio Jr.', 'Caloocan North Transport Cooperative', 'JEEP-BAGUMBONG-NOVALICHES_BAYAN', 'Armak', 'Modern Jeep', '2023', 'ISF2.8-3002', 'A00112233446', 'Diesel', 'Linked', 'Declared/linked', 'Active'),

-- Op 13: Gabriela Silang (Bagumbong-Deparo) - Route: JEEP-BAGUMBONG-DEPARO
('PUJ-501', 'Jeepney', 13, 'Gabriela Silang', 'Caloocan North Transport Cooperative', 'JEEP-BAGUMBONG-DEPARO', 'Hino', 'Modern Class 2', '2024', 'N04C-4001', 'H11223344556', 'Diesel', 'Linked', 'Declared/linked', 'Active'),

-- Modern Jeepneys (Coop Managed)
-- Op 4: Roberto Dela Cruz (Bagong Silang-Novaliches) - Route: JEEP-12
('MPUJ-001', 'Jeepney', 4, 'Roberto Dela Cruz', 'Caloocan North Transport Cooperative', 'JEEP-12', 'Isuzu', 'NQR Class 3', '2023', '4HK1-5001', 'I22334455667', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('MPUJ-002', 'Jeepney', 4, 'Roberto Dela Cruz', 'Caloocan North Transport Cooperative', 'JEEP-12', 'Isuzu', 'NQR Class 3', '2023', '4HK1-5002', 'I22334455668', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('MPUJ-003', 'Jeepney', 4, 'Roberto Dela Cruz', 'Caloocan North Transport Cooperative', 'JEEP-12', 'Isuzu', 'NQR Class 3', '2023', '4HK1-5003', 'I22334455669', 'Diesel', 'Linked', 'Declared/linked', 'Active'),

-- UV Express Units
-- Op 17: Ricardo Dantes (Deparo-SM North) - Route: UV-DEPARO-SM_NORTH
('NUV-101', 'UV', 17, 'Ricardo Dantes', 'Deparo UV Express Service Cooperative', 'UV-DEPARO-SM_NORTH', 'Toyota', 'Hiace Commuter', '2019', '1KD-6001', 'T33445566778', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('NUV-102', 'UV', 17, 'Ricardo Dantes', 'Deparo UV Express Service Cooperative', 'UV-DEPARO-SM_NORTH', 'Toyota', 'Hiace Commuter', '2020', '1KD-6002', 'T33445566779', 'Diesel', 'Linked', 'Declared/linked', 'Active'),

-- Op 18: Marian Rivera (Deparo-Cubao) - Route: UV-DEPARO-CUBAO
('NUV-201', 'UV', 18, 'Marian Rivera', 'Deparo UV Express Service Cooperative', 'UV-DEPARO-CUBAO', 'Nissan', 'Urvan NV350', '2018', 'YD25-7001', 'N44556677889', 'Diesel', 'Linked', 'Declared/linked', 'Active'),
('NUV-202', 'UV', 18, 'Marian Rivera', 'Deparo UV Express Service Cooperative', 'UV-DEPARO-CUBAO', 'Nissan', 'Urvan NV350', '2021', 'YD25-7002', 'N44556677890', 'Diesel', 'Linked', 'Declared/linked', 'Active'),

-- Op 20: Coco Martin (Deparo-Quezon Ave) - Route: UV-DEPARO-QUEZON_AVE
('NUV-301', 'UV', 20, 'Coco Martin', 'Deparo UV Express Service Cooperative', 'UV-DEPARO-QUEZON_AVE', 'Foton', 'Transvan', '2022', '4J28-8001', 'F55667788990', 'Diesel', 'Linked', 'Declared/linked', 'Active'),

-- Tricycles (TODA)
-- Op 22: Pedro Penduko (Bagumbong-Deparo) - Route: TRI-BAGUMBONG-DEPARO
('TRI-001', 'Tricycle', 22, 'Pedro Penduko', 'Bagumbong TODA', 'TRI-BAGUMBONG-DEPARO', 'Kawasaki', 'Barako 175', '2015', 'BC175-1001', 'K66778899001', 'Gasoline', 'Linked', 'Declared/linked', 'Active'),
('TRI-002', 'Tricycle', 22, 'Pedro Penduko', 'Bagumbong TODA', 'TRI-BAGUMBONG-DEPARO', 'Honda', 'TMX 155', '2016', 'TMX-2001', 'H77889900112', 'Gasoline', 'Linked', 'Declared/linked', 'Active'),

-- Op 24: Enteng Kabisote (Deparo-Camarin) - Route: TRI-DEPARO-CAMARIN
('TRI-101', 'Tricycle', 24, 'Enteng Kabisote', 'Deparo Tricycle Terminal', 'TRI-DEPARO-CAMARIN', 'Suzuki', 'Tmx 125', '2018', 'S125-3001', 'S88990011223', 'Gasoline', 'Linked', 'Declared/linked', 'Active'),

-- Op 25: Agimat (Camarin-Tala) - Route: TRI-CAMARIN-TALA
('TRI-201', 'Tricycle', 25, 'Agimat Agila', 'Camarin Tricycle Terminal', 'TRI-CAMARIN-TALA', 'Yamaha', 'RS100', '2010', 'RS-4001', 'Y99001122334', 'Gasoline', 'Linked', 'Declared/linked', 'Active'),

-- Colorum / Suspended Vehicles (For testing)
('COL-666', 'Jeepney', NULL, 'Unknown', NULL, 'JEEP-10', 'Sarao', 'Traditional', '1995', 'UNK-0001', 'UNK-0001', 'Diesel', 'Encoded', 'Colorum', 'Suspended'),
('SUS-999', 'UV', 17, 'Ricardo Dantes', 'Deparo UV Express', 'UV-DEPARO-SM_NORTH', 'Toyota', 'Hiace', '2010', '1KD-OLD1', 'TOLD-001', 'Diesel', 'Linked', 'Suspended', 'Suspended');

-- 4. Seed Random Data to fill up to 100+ vehicles
-- Using a stored procedure-like block (MySQL specific) to generate bulk data if needed, 
-- but explicit inserts are safer for this request.
-- Adding 50 more random vehicles linked to existing operators.

INSERT INTO vehicles (plate_number, vehicle_type, operator_id, operator_name, coop_name, route_id, make, model, year_model, fuel_type, record_status, status) VALUES
('ABC-1001', 'Jeepney', 8, 'Maria Clara Bautista', 'United Transport Service Cooperative', 'JEEP-SANGANDAAN-DIVISORIA', 'Francisco', 'Traditional', '2012', 'Diesel', 'Linked', 'Declared/linked'),
('ABC-1002', 'Jeepney', 8, 'Maria Clara Bautista', 'United Transport Service Cooperative', 'JEEP-SANGANDAAN-DIVISORIA', 'Francisco', 'Traditional', '2012', 'Diesel', 'Linked', 'Declared/linked'),
('ABC-1003', 'Jeepney', 8, 'Maria Clara Bautista', 'United Transport Service Cooperative', 'JEEP-SANGANDAAN-DIVISORIA', 'Sarao', 'Traditional', '2011', 'Diesel', 'Linked', 'Declared/linked'),
('ABC-1004', 'Jeepney', 9, 'Jose Rizalino', 'United Transport Service Cooperative', 'JEEP-SANGANDAAN-RECTO', 'Morales', 'Traditional', '2013', 'Diesel', 'Linked', 'Declared/linked'),
('ABC-1005', 'Jeepney', 9, 'Jose Rizalino', 'United Transport Service Cooperative', 'JEEP-SANGANDAAN-RECTO', 'Morales', 'Traditional', '2013', 'Diesel', 'Linked', 'Declared/linked'),
('ABC-1006', 'Jeepney', 27, 'Kathryn Bernardo', 'Monumento-Malabon Jeepney Operators Coop', 'JEEP-16', 'Sarao', 'Traditional', '2015', 'Diesel', 'Linked', 'Declared/linked'),
('ABC-1007', 'Jeepney', 27, 'Kathryn Bernardo', 'Monumento-Malabon Jeepney Operators Coop', 'JEEP-16', 'Sarao', 'Traditional', '2015', 'Diesel', 'Linked', 'Declared/linked'),
('ABC-1008', 'Jeepney', 28, 'Daniel Padilla', 'Monumento-Malabon Jeepney Operators Coop', 'JEEP-16', 'Sarao', 'Traditional', '2016', 'Diesel', 'Linked', 'Declared/linked'),
('ABC-1009', 'Jeepney', 28, 'Daniel Padilla', 'Monumento-Malabon Jeepney Operators Coop', 'JEEP-16', 'Sarao', 'Traditional', '2016', 'Diesel', 'Linked', 'Declared/linked'),
('UVX-5001', 'UV', 18, 'Marian Rivera', 'Deparo UV Express Service Cooperative', 'UV-DEPARO-CUBAO', 'Nissan', 'Urvan', '2019', 'Diesel', 'Linked', 'Declared/linked'),
('UVX-5002', 'UV', 18, 'Marian Rivera', 'Deparo UV Express Service Cooperative', 'UV-DEPARO-CUBAO', 'Nissan', 'Urvan', '2019', 'Diesel', 'Linked', 'Declared/linked'),
('UVX-5003', 'UV', 20, 'Coco Martin', 'Deparo UV Express Service Cooperative', 'UV-DEPARO-QUEZON_AVE', 'Toyota', 'Hiace', '2020', 'Diesel', 'Linked', 'Declared/linked'),
('UVX-5004', 'UV', 20, 'Coco Martin', 'Deparo UV Express Service Cooperative', 'UV-DEPARO-QUEZON_AVE', 'Toyota', 'Hiace', '2020', 'Diesel', 'Linked', 'Declared/linked'),
('UVX-5005', 'UV', 21, 'Julia Montes', 'Novaliches-Blumentritt Transport Coop', 'UV-18', 'Toyota', 'Grandia', '2021', 'Diesel', 'Linked', 'Declared/linked'),
('UVX-5006', 'UV', 21, 'Julia Montes', 'Novaliches-Blumentritt Transport Coop', 'UV-18', 'Toyota', 'Grandia', '2021', 'Diesel', 'Linked', 'Declared/linked'),
('TRI-8001', 'Tricycle', 26, 'Panday', 'Tala Tricycle Terminal', 'TRI-TALA-BAGUMBONG', 'Honda', 'TMX', '2018', 'Gasoline', 'Linked', 'Declared/linked'),
('TRI-8002', 'Tricycle', 26, 'Panday', 'Tala Tricycle Terminal', 'TRI-TALA-BAGUMBONG', 'Honda', 'TMX', '2018', 'Gasoline', 'Linked', 'Declared/linked'),
('TRI-8003', 'Tricycle', 25, 'Agimat Agila', 'Camarin Tricycle Terminal', 'TRI-CAMARIN-DEPARO', 'Kawasaki', 'Barako', '2019', 'Gasoline', 'Linked', 'Declared/linked'),
('TRI-8004', 'Tricycle', 25, 'Agimat Agila', 'Camarin Tricycle Terminal', 'TRI-CAMARIN-DEPARO', 'Kawasaki', 'Barako', '2019', 'Gasoline', 'Linked', 'Declared/linked');
