<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class CheckUserRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-user-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check user roles and permissions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Recent Users and Their Roles:');
        $this->info('================================');

        $users = User::with('roles')->latest()->take(5)->get();

        foreach ($users as $user) {
            $roles = $user->roles->pluck('name')->toArray();
            $roleStr = empty($roles) ? 'NO ROLES' : implode(', ', $roles);
            $this->info("ID: {$user->id} | {$user->name} ({$user->email}) | Roles: {$roleStr}");
        }

        $this->info("\nAvailable Roles:");
        $this->info("================");

        $roles = Role::all();
        foreach ($roles as $role) {
            $this->info("Role: {$role->name}");
        }

        $this->info("\nLast user can create property?");
        $this->info("==============================");
        
        if ($users->isNotEmpty()) {
            $lastUser = $users->first();
            $canCreate = $lastUser->hasRole('admin') || $lastUser->hasRole('landlord');
            $this->info("User {$lastUser->name} can create property: " . ($canCreate ? 'YES' : 'NO'));
        }
    }
}
