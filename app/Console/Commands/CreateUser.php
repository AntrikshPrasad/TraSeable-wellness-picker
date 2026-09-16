<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;

/**
 * Self-registration is disabled, so this is how accounts come into existence.
 * The password is prompted for rather than passed as an argument to keep it
 * out of the shell history.
 */
class CreateUser extends Command
{
    protected $signature = 'user:create {name : The person\'s display name} {email : Their email address}';

    protected $description = 'Create a user account (self-registration is disabled)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $email = $this->argument('email');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $plain = password('Password', required: true, validate: fn (string $value) => match (true) {
            strlen($value) < 8 => 'The password must be at least 8 characters.',
            default => null,
        });

        $confirmation = password('Confirm password', required: true);

        if ($plain !== $confirmation) {
            $this->components->error('The passwords did not match.');

            return self::FAILURE;
        }

        // 'password' is cast to 'hashed' on the User model, so no manual Hash::make.
        $user = User::create(['name' => $name, 'email' => $email, 'password' => $plain]);

        $this->components->info("Created user #{$user->id} <{$user->email}>.");

        return self::SUCCESS;
    }
}
