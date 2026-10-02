<?php

namespace App\Actions\Accounts;

/**
 * Generates readable default passwords, e.g. "Arka-7kQm-Xp2R". Look-alike characters
 * (0/O, 1/l/I) are left out so the password is easy to type from the screen or email.
 */
class DefaultPassword
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function generate(): string
    {
        return 'Arka-'.$this->chunk().'-'.$this->chunk();
    }

    private function chunk(): string
    {
        $chunk = '';

        for ($i = 0; $i < 4; $i++) {
            $chunk .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $chunk;
    }
}
