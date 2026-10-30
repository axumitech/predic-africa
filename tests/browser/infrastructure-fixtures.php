<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('testing')||config('database.default')!=='sqlite'||!str_contains(config('database.connections.sqlite.database'),'predic-e2e-infrastructure-'))throw new RuntimeException('Isolated test DB required.');
foreach(['superadmin','trader']as$role){
 $user=App\Models\User::create(['name'=>'Test '.$role,'email'=>$role.'@example.invalid','password'=>'infra-password-123','country'=>'GAB']);
 $user->role=$role;$user->save();
}
