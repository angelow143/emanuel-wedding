<?php foreach (App\Models\User::all() as $user) { $user->email = strtolower($user->email); $user->password = Hash::make('angelow'); $user->save(); }
