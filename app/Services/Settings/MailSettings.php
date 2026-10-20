<?php

namespace App\Services\Settings;

use App\Models\HomeOffice;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Gmail account ARKA sends email from (invite links, password resets), set by the Super Admin
 * in System & Rules instead of the .env file. The App Password is stored encrypted and is never
 * sent back to the browser. Until it is set, the .env mail settings apply.
 */
class MailSettings
{
    public const HOST = 'smtp.gmail.com';

    public const PORT = 587;

    private const TABLE = 'system_settings';

    private const USERNAME = 'mail_username';

    private const PASSWORD = 'mail_password';

    private const FROM_NAME = 'mail_from_name';

    /**
     * Values already read, per home office.
     *
     * @var array<int, array<string, string>>
     */
    private array $values = [];

    public function username(): ?string
    {
        return $this->values()[self::USERNAME] ?? null;
    }

    public function fromName(): string
    {
        return $this->values()[self::FROM_NAME] ?? config('app.name');
    }

    public function hasPassword(): bool
    {
        return $this->password() !== null;
    }

    public function isConfigured(): bool
    {
        return filled($this->username()) && $this->hasPassword();
    }

    /**
     * Saves the sender. A blank password keeps the one already saved.
     */
    public function save(string $username, ?string $password, string $fromName, User $by): void
    {
        $changes = [self::USERNAME => $username, self::FROM_NAME => $fromName];

        if (filled($password)) {
            // Google shows App Passwords in groups of four; the spaces are not part of it.
            $changes[self::PASSWORD] = Crypt::encryptString(str_replace(' ', '', $password));
        }

        foreach ($changes as $key => $value) {
            DB::table(self::TABLE)->updateOrInsert(['home_office_id' => $by->home_office_id, 'setting_key' => $key], [
                'setting_value' => $value,
                'data_type' => 'string',
                'description' => 'Email sender',
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        $this->values = [];
        $this->apply();
    }

    /**
     * Points ARKA's mailer at the saved Gmail account. Does nothing until both username and password are set.
     */
    public function apply(?MailManager $manager = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => null,
            'mail.mailers.smtp.host' => self::HOST,
            'mail.mailers.smtp.port' => self::PORT,
            'mail.mailers.smtp.username' => $this->username(),
            'mail.mailers.smtp.password' => $this->password(),
            'mail.from.address' => $this->username(),
            'mail.from.name' => $this->fromName(),
        ]);

        // Rebuild the SMTP mailer if it was already created with the old settings.
        ($manager ?? (app()->resolved('mail.manager') ? app('mail.manager') : null))?->purge('smtp');
    }

    private function password(): ?string
    {
        $encrypted = $this->values()[self::PASSWORD] ?? null;

        if (blank($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            // Saved under a different APP_KEY: treat as not set so the Super Admin enters it again.
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private function values(): array
    {
        $homeOfficeId = HomeOffice::currentId();

        if (isset($this->values[$homeOfficeId])) {
            return $this->values[$homeOfficeId];
        }

        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }

        return $this->values[$homeOfficeId] = DB::table(self::TABLE)
            ->where('home_office_id', $homeOfficeId)
            ->whereIn('setting_key', [self::USERNAME, self::PASSWORD, self::FROM_NAME])
            ->pluck('setting_value', 'setting_key')
            ->all();
    }
}
