<?php
declare(strict_types=1);
namespace Systopic\Cms\Media;

final class EditorTag
{
    public static function page(int $nodeId, string $label, ?string $language = null): string
    {
        $label = trim($label) !== '' ? $label : 'Page ' . $nodeId;
        $ref = 'page:' . $nodeId . ($language ? '?lang=' . rawurlencode($language) : '');
        return '<a href="' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    public static function stored(int $nodeId, string $filename, string $label, string $mime, string $imageFormat = 'original'): string
    {
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $label = trim($label) !== '' ? $label : $filename;
        $ref = 'media:' . $nodeId;
        return match (strtolower($mime)) {
            'image/jpeg','image/jpg','image/png','image/gif','image/webp','image/svg+xml','image/svg' => '<img src="' . $ref . '?format=' . $e($imageFormat) . '" alt="' . $e($label) . '">',
            'video/mp4' => '<video src="' . $ref . '" poster="' . $ref . '" controls></video>',
            'audio/mpeg','audio/mp4','audio/x-m4a' => '<audio src="' . $ref . '" controls></audio>',
            'application/pdf' => '<iframe src="' . $ref . '"></iframe>',
            default => '<a href="' . $ref . '" download>' . $e($label) . '</a>',
        };
    }
}
