<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\Duplicates;

/** Read-only byte comparison. Only equal-sized originals need hashing. */
final class IdenticalFiles
{
    /** @param list<array{id:int,label:string,path:string}> $files */
    public static function scan(array $files, float $seconds = 5): array
    {
        $deadline = microtime(true) + $seconds;
        $sizes = $hashes = [];
        $unreadable = $remaining = $checked = 0;
        foreach ($files as $file) {
            if (!is_file($file['path']) || !is_readable($file['path'])) { ++$unreadable; continue; }
            $size = filesize($file['path']);
            if ($size === false) { ++$unreadable; continue; }
            $sizes[$size][] = $file;
        }
        foreach ($sizes as $size => $sameSize) {
            if (count($sameSize) < 2) continue;
            foreach ($sameSize as $file) {
                if (microtime(true) >= $deadline) { ++$remaining; continue; }
                $stream = fopen($file['path'], 'rb');
                if (!$stream) { ++$unreadable; continue; }
                $hash = hash_init('sha256');
                while (!feof($stream) && microtime(true) < $deadline) {
                    $chunk = fread($stream, 1048576);
                    if ($chunk === false) break;
                    hash_update($hash, $chunk);
                }
                $complete = feof($stream);
                fclose($stream);
                if (!$complete) { ++$remaining; continue; }
                ++$checked;
                $hashes[$size . ':' . hash_final($hash)][] = $file;
            }
        }
        return ['groups' => array_values(array_filter($hashes, static fn($group) => count($group) > 1)), 'checked' => $checked, 'unreadable' => $unreadable, 'remaining' => $remaining];
    }
}
