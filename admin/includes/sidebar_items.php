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
      ['id' => 'submodule1', 'label' => 'Operators', 'path' => '/module1/submodule1', 'anyPermissions' => ['module1.read','module1.write']],
      ['id' => 'submodule2', 'label' => 'Vehicles', 'path' => '/module1/submodule2', 'anyPermissions' => ['module1.read','module1.write']],
      ['id' => 'submodule4', 'label' => 'Link Vehicle to Operator', 'path' => '/module1/submodule4', 'anyPermissions' => ['module1.link_vehicle','module1.write']],
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
    ],
  ],
  [
    'id' => 'module3',
    'label' => 'Traffic Violation Monitoring',
    'icon' => 'ticket',
    'subItems' => [
      ['id' => 'submodule1', 'label' => 'Issue Ticket', 'path' => '/module3/submodule1', 'anyPermissions' => ['module3.issue','module3.read']],
      ['id' => 'submodule2', 'label' => 'Payment', 'path' => '/module3/submodule2', 'anyPermissions' => ['module3.settle']],
      ['id' => 'submodule3', 'label' => 'Analytics & Reports', 'path' => '/module3/submodule3', 'anyPermissions' => ['module3.analytics']],
    ],
  ],
  [
    'id' => 'module4',
    'label' => 'Vehicle Inspection & Registration',
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
      ['id' => 'submodule2', 'label' => 'Assign Vehicle to Terminal', 'path' => '/module5/submodule2', 'anyPermissions' => ['module5.assign_vehicle']],
      ['id' => 'submodule3', 'label' => 'Parking Slot Management', 'path' => '/module5/submodule3', 'anyPermissions' => ['module5.manage_terminal']],
      ['id' => 'submodule4', 'label' => 'Payment', 'path' => '/module5/submodule4', 'anyPermissions' => ['module5.parking_fees']],
    ],
  ],
  [
    'id' => 'users',
    'label' => 'User Management',
    'icon' => 'users',
    'subItems' => [
      ['id' => 'accounts', 'label' => 'Accounts & Roles', 'path' => '/users/accounts', 'roles' => ['SuperAdmin']],
      ['id' => 'security', 'label' => 'Security Policy', 'path' => '/users/security', 'anyPermissions' => ['settings.manage']],
      ['id' => 'activity', 'label' => 'Activity Logs', 'path' => '/users/activity', 'roles' => ['SuperAdmin']],
    ],
  ],
  [
    'id' => 'settings',
    'label' => 'Settings',
    'icon' => 'settings',
    'subItems' => [
      ['id' => 'general-settings', 'label' => 'General', 'path' => '/settings/general', 'anyPermissions' => ['settings.manage']],
    ],
  ],
];
