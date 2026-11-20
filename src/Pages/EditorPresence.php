<?php

declare(strict_types=1);
namespace Systopic\Cms\Pages;

/** Advisory presence. Editing remains possible; field versions guard writes. */
final class EditorPresence
{
    /**
     * Seconds an entry lives without a request. There is no heartbeat: a tab
     * reports with its requests and leaves on pagehide, so this only clears
     * a departure that never arrived.
     */
    public const TTL = 1800;

    public static function touch(string $path, string $page, string $client, int $now, int $user = 0, ?array &$users = null, string $device = '', ?array &$peers = null): ?int
    {
        if (!is_dir(dirname($path)) && !@mkdir(dirname($path), 0770, true)) return null;
        $file = @fopen($path, 'c+');
        if (!$file) return null;
        try {
            if (!flock($file, LOCK_EX)) return null;
            $entries = json_decode(stream_get_contents($file), true);
            $entries = is_array($entries) ? $entries : [];
            foreach ($entries as $key => $clients) {
                $entries[$key] = array_filter(is_array($clients) ? $clients : [], static fn($entry) => (is_array($entry) ? (int) ($entry['time'] ?? 0) : (int) $entry) > $now - self::TTL);
                if (!$entries[$key]) unset($entries[$key]);
            }
            // One tab can only be viewing one page at a time.
            foreach ($entries as &$clients) unset($clients[$client]);
            unset($clients);
            if ($page !== '') $entries[$page][$client] = ['time' => $now, 'user' => $user, 'device' => mb_substr($device, 0, 100)];
            $others = $page === '' ? 0 : count($entries[$page]) - 1;
            $users = [];
            $peers = [];
            foreach ($entries[$page] ?? [] as $key => $entry) {
                if ($key !== $client && is_array($entry)) $peers[] = ['user' => (int) ($entry['user'] ?? 0), 'device' => (string) ($entry['device'] ?? '')];
                if ($key !== $client && is_array($entry) && ($entry['user'] ?? 0) > 0 && $entry['user'] !== $user) $users[] = (int) $entry['user'];
            }
            $users = array_values(array_unique($users));
            rewind($file); ftruncate($file, 0);
            fwrite($file, json_encode($entries, JSON_THROW_ON_ERROR));
            fflush($file);
            return $others;
        } finally { fclose($file); }
    }
}
