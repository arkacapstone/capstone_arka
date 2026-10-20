<?php

namespace App\Notifications;

use App\Models\Reward;

/**
 * Recognition for the employee (Blueprint §3.1 Performance & Rewards).
 */
class RewardGranted extends ArkaNotification
{
    public function __construct(private readonly Reward $reward) {}

    protected function title(object $notifiable): string
    {
        return "You received a reward: {$this->reward->kind()}";
    }

    protected function message(object $notifiable): string
    {
        $amount = $this->reward->amount ? ' of ₱'.number_format((float) $this->reward->amount, 2) : '';

        return trim("Congratulations! A {$this->reward->kind()}{$amount} was recorded for you. {$this->reward->note()}");
    }

    protected function category(): string
    {
        return 'workforce';
    }
}
