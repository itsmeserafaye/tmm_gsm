INSERT INTO routes (route_id, route_code, route_name, vehicle_type, origin, destination, via, structure, authorized_units, max_vehicle_limit, status)
VALUES
('TR-01','TR-01','Monumento Loop','Jeepney','Monumento','Monumento','EDSA • Rizal Ave Ext • Samson Rd • 10th Ave/5th Ave (Caloocan)','Loop',60,60,'Active'),
('TR-02','TR-02','Monumento–Sangandaan Connector','Jeepney','Monumento','Sangandaan','Samson Rd • Sangandaan Area (Caloocan)','Point-to-Point',45,45,'Active'),
('TR-03','TR-03','Deparo–Tala Service','Jeepney','Deparo','Tala','Deparo Rd • Tala Area (Caloocan)','Point-to-Point',40,40,'Active'),
('TR-04','TR-04','Bagong Silang–Camarin Loop','Jeepney','Bagong Silang','Camarin','Bagong Silang • Camarin (Caloocan)','Loop',55,55,'Active'),
('TR-05','TR-05','Bagumbong–Deparo Line','Jeepney','Bagumbong','Deparo','Bagumbong • Deparo (Caloocan)','Point-to-Point',35,35,'Active'),
('TR-06','TR-06','Grace Park–Monumento Shuttle','UV','Grace Park','Monumento','Grace Park • EDSA/Monumento (Caloocan)','Point-to-Point',30,30,'Active'),
('TR-07','TR-07','North Caloocan Loop (Camarin–Deparo)','Jeepney','Camarin','Deparo','Camarin • Bagong Silang • Deparo (Caloocan)','Loop',50,50,'Active'),
('TR-08','TR-08','South Caloocan Loop (Grace Park)','Jeepney','Grace Park','Grace Park','Grace Park • 10th Ave/5th Ave (Caloocan)','Loop',50,50,'Active')
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

