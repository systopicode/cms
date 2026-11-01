<?php

declare(strict_types=1);
namespace Systopic\Cms\Pages;

use DateTimeImmutable;

/** The public publishing window, independently of an editor's preview session. */
final class PublishingState
{
    /** @return array{public: bool, key: string, label: string, reason: string} */
    public static function of(bool $exists, bool $trashed, bool $enabled, ?DateTimeImmutable $from, ?DateTimeImmutable $to, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        [$key, $label, $reason] = match (true) {
            !$exists => ['missing', 'Not public', 'No page exists in this language.'],
            $trashed => ['trashed', 'Not public', 'This page is in the trash.'],
            !$enabled => ['offline', 'Not public', 'Publishing is turned off.'],
            $from !== null && $from > $now => ['scheduled', 'Scheduled', 'Publication starts ' . $from->format(\Systopic\System\Sys\LocalTime::DATETIME) . '.'],
            $to !== null && $to < $now => ['expired', 'Expired', 'Publication ended ' . $to->format(\Systopic\System\Sys\LocalTime::DATETIME) . '.'],
            default => ['online', 'Public', 'Publishing is enabled and the current time is inside its publishing window.'],
        };
        return ['public' => $key === 'online', 'key' => $key, 'label' => $label, 'reason' => $reason];
    }
}
