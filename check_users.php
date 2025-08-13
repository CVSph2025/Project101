<?php

use App\Models\User;

// Get latest users and their roles
$users = User::with('roles')->latest()->take(5)->get();

echo "Recent Users and Their Roles:\n";
echo "================================\n";

foreach ($users as $user) {
    $roles = $user->roles->pluck('name')->toArray();
    $roleStr = empty($roles) ? 'NO ROLES' : implode(', ', $roles);
    echo "ID: {$user->id} | {$user->name} ({$user->email}) | Roles: {$roleStr}\n";
}

echo "\nChecking if roles exist:\n";
echo "========================\n";

$roles = \Spatie\Permission\Models\Role::all();
foreach ($roles as $role) {
    echo "Role: {$role->name}\n";
}
