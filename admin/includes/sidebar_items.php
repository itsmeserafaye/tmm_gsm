<?php
$sidebarItems = [
  [
    'id' => 'dashboard',
    'label' => 'Dashboard',
    'icon' => 'gauge',
    'path' => '/dashboard',
    'anyPermissions' => ['dashboard.view','module1.read','module2.read','module3.read','module4.read','module5.read'],
  ],
  [
    'id' => 'module1',
    'label' => 'PUV Database',
    'icon' => 'bus',
    'subItems' => [
      ['id' => 'submodule1', 'label' => 'Operator Encoding', 'path' => '/puv-database/operator-encoding', 'page' => 'module1/submodule1', 'anyPermissions' => ['module1.read','module1.write']],
      ['id' => 'submodule2', 'label' => 'Vehicle Encoding', 'path' => '/puv-database/vehicle-encoding', 'page' => 'module1/submodule2', 'anyPermissions' => ['module1.read','module1.write']],
      ['id' => 'submodule4', 'label' => 'Link Vehicle to Operator', 'path' => '/puv-database/link-vehicle-to-operator', 'page' => 'module1/submodule4', 'anyPermissions' => ['module1.link_vehicle','module1.write']],
      ['id' => 'submodule5', 'label' => 'Ownership Transfer', 'path' => '/puv-database/ownership-transfer', 'page' => 'module1/submodule5', 'anyPermissions' => ['module1.write','module1.vehicles.write']],
      ['id' => 'submodule6', 'label' => 'Routes & LPTRP', 'path' => '/puv-database/routes-lptrp', 'page' => 'module1/submodule6', 'anyPermissions' => ['module1.read','module1.write']],
    ],
  ],
  [
    'id' => 'module2',
    'label' => 'Franchise Management',
    'icon' => 'shield-check',
    'subItems' => [
      ['id' => 'submodule1', 'label' => 'Franchise Applications', 'path' => '/module2/submodule1', 'anyPermissions' => ['module2.read','module2.endorse','module2.approve','module2.history','module2.apply']],
      ['id' => 'submodule2', 'label' => 'Submit Franchise Application', 'path' => '/module2/submodule2', 'anyPermissions' => ['module2.apply']],
      ['id' => 'submodule3', 'label' => 'Endorsement & LTFRB Approval', 'path' => '/module2/submodule3', 'anyPermissions' => ['module2.endorse','module2.approve','module2.history']],
      ['id' => 'submodule4', 'label' => 'Operator Document Validation', 'path' => '/module2/submodule4', 'anyPermissions' => ['module1.write','module2.endorse','module2.approve','module2.apply']],
      ['id' => 'submodule5', 'label' => 'Route Assignment', 'path' => '/module2/submodule5', 'anyPermissions' => ['module2.read','module2.endorse','module2.approve','module2.history']],
    ],
  ],
  [
    'id' => 'module3',
    'label' => 'Traffic Violation & Ticketing',
    'icon' => 'ticket',
    'subItems' => [
      ['id' => 'submodule1', 'label' => 'Issue Ticket', 'path' => '/module3/submodule1', 'anyPermissions' => ['module3.issue','module3.read']],
      ['id' => 'submodule2', 'label' => 'Treasury Payment', 'path' => '/module3/submodule2', 'anyPermissions' => ['module3.settle']],
      ['id' => 'submodule3', 'label' => 'Analytics & Reports', 'path' => '/module3/submodule3', 'anyPermissions' => ['module3.analytics']],
    ],
  ],
  [
    'id' => 'module4',
    'label' => 'Vehicle Registration & Inspection',
    'icon' => 'clipboard-check',
    'subItems' => [
      ['id' => 'submodule1', 'label' => 'Vehicle Registration List', 'path' => '/module4/submodule1', 'anyPermissions' => ['module4.read','module4.schedule','module4.inspect','module4.certify']],
      ['id' => 'submodule2', 'label' => 'Register Vehicle', 'path' => '/module4/submodule2', 'anyPermissions' => ['module4.schedule']],
      ['id' => 'submodule3', 'label' => 'Schedule Inspection', 'path' => '/module4/submodule3', 'anyPermissions' => ['module4.schedule']],
      ['id' => 'submodule4', 'label' => 'Conduct Inspection', 'path' => '/module4/submodule4', 'anyPermissions' => ['module4.inspect','module4.certify']],
    ],
  ],
  [
    'id' => 'module5',
    'label' => 'Parking & Terminal Management',
    'icon' => 'map-pin',
    'subItems' => [
      ['id' => 'submodule1', 'label' => 'Terminal List', 'path' => '/module5/submodule1', 'anyPermissions' => ['module5.manage_terminal','module5.read']],
      ['id' => 'submodule2', 'label' => 'Assign Vehicle', 'path' => '/module5/submodule2', 'anyPermissions' => ['module5.assign_vehicle']],
      ['id' => 'submodule4', 'label' => 'Terminal Slots & Payments', 'path' => '/module5/submodule4', 'anyPermissions' => ['module5.manage_terminal','module5.parking_fees']],
      ['id' => 'parking-list', 'label' => 'Parking List', 'path' => '/parking/list', 'anyPermissions' => ['module5.manage_terminal','module5.parking_fees']],
      ['id' => 'parking-slots-payments', 'label' => 'Parking Slots & Payments', 'path' => '/parking/slots-payments', 'anyPermissions' => ['module5.manage_terminal','module5.parking_fees']],
    ],
  ],
  [
    'id' => 'users',
    'label' => 'User Management',
    'icon' => 'users',
    'subItems' => [
      ['id' => 'accounts', 'label' => 'Accounts & Roles', 'path' => '/users/accounts', 'roles' => ['SuperAdmin']],
      ['id' => 'operator-accounts', 'label' => 'Operator Portal Accounts', 'path' => '/users/operator-accounts', 'roles' => ['SuperAdmin']],
      ['id' => 'commuter-accounts', 'label' => 'Commuter Accounts', 'path' => '/users/commuters', 'roles' => ['SuperAdmin']],
      ['id' => 'public-portal-reports', 'label' => 'Public Portal Reports', 'path' => '/portal/commuter-reports', 'page' => 'portal/commuter-reports', 'roles' => ['SuperAdmin', 'Admin', 'Admin / Transport Officer', 'Franchise Officer']],
      ['id' => 'activity', 'label' => 'Activity Logs', 'path' => '/users/activity', 'roles' => ['SuperAdmin']],
    ],
  ],
  [
    'id' => 'settings',
    'label' => 'Settings',
    'icon' => 'settings',
    'subItems' => [
      ['id' => 'general-settings', 'label' => 'General', 'path' => '/settings/general', 'anyPermissions' => ['settings.manage']],
      ['id' => 'security-settings', 'label' => 'Security Settings', 'path' => '/settings/security', 'anyPermissions' => ['settings.manage']],
    ],
  ],
];
