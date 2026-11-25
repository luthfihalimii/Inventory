<?php

namespace App\Support;

/**
 * Conservative allow-list sanitizer for Quill HTML.
 * All attributes are discarded except validated href/src and safe alt text.
 * This deliberately avoids trusting editor-generated markup.
 */
class AnnouncementHtmlSanitizer
{
    private const TAGS = [
        'p','br','strong','b','em','i','u','s','ol','ul','li','blockquote',
        'h1','h2','h3','h4','h5','h6','a','img','pre','code','sub','sup',
    ];

    public static function clean(string $html): string
    {
        $html = substr($html, 0, 200000);
        // Remove comments and dangerous elements with their contents before token handling.
        $html = preg_replace('/<!--[\s\S]*?-->/u', '', $html) ?? '';
        $html = preg_replace('~<(script|style|iframe|object|embed|svg|math|form|input|button|textarea|select)[^>]*>[\s\S]*?</\1\s*>~i', '', $html) ?? '';
        $html = preg_replace('~<(script|style|iframe|object|embed|svg|math|form|input|button|textarea|select)\b[^>]*/?>~i', '', $html) ?? '';
        $html = strip_tags($html, '<'.implode('><', self::TAGS).'>');

        $html = preg_replace_callback('~</?([a-z0-9]+)(?:\s+([^>]*))?\s*/?>~i', function (array $m): string {
            $tag = strtolower($m[1]);
            if (! in_array($tag, self::TAGS, true)) return '';
            $closing = str_starts_with($m[0], '</');
            if ($closing) return in_array($tag, ['br','img'], true) ? '' : "</{$tag}>";

            if ($tag === 'a') {
                $href = self::attribute($m[2] ?? '', 'href');
                $safe = self::safeLink($href);
                return $safe ? '<a href="'.e($safe).'" rel="nofollow noopener noreferrer" target="_blank">' : '<a>';
            }
            if ($tag === 'img') {
                $src = self::attribute($m[2] ?? '', 'src');
                $path = parse_url($src, PHP_URL_PATH) ?: '';
                $alt = self::attribute($m[2] ?? '', 'alt');
                if (! preg_match('~^/announcements/images/[A-Za-z0-9-]+\.(jpg|jpeg|png|webp)$~iD', $path)) return '';
                return '<img src="'.e($path).'" alt="'.e(substr($alt, 0, 160)).'" loading="lazy">';
            }
            if ($tag === 'li') {
                $attrs = $m[2] ?? '';
                $list = self::attribute($attrs, 'data-list');
                preg_match('/(?:^|\\s)class\\s*=\\s*([\"\\\'])(.*?)\\1/is', $attrs, $classMatch);
                $class = $classMatch[2] ?? '';
                preg_match('/ql-indent-([1-8])/', $class, $indentMatch);
                $indent = isset($indentMatch[1]) ? ' ql-indent-'.$indentMatch[1] : '';
                $type = in_array($list, ['bullet','ordered'], true) ? ' data-list="'.$list.'"' : '';
                return '<li'.$type.($indent ? ' class="'.trim($indent).'"' : '').'>';
            }
            return in_array($tag, ['br'], true) ? '<br>' : "<{$tag}>";
        }, $html) ?? '';

        return trim($html);
    }

    private static function attribute(string $attributes, string $name): string
    {
        $quoted = preg_match('/(?:^|\s)'.preg_quote($name, '/').'\s*=\s*(["\'])(.*?)\1/is', $attributes, $m);
        if ($quoted) return html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5);
        return '';
    }

    private static function safeLink(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
        $url = preg_replace('/[\x00-\x20\x7f]+/u', '', $url) ?? '';
        if ($url === '' || str_starts_with($url, '//')) return null;
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme !== null && ! in_array(strtolower($scheme), ['http','https','mailto'], true)) return null;
        if ($scheme === null && ! str_starts_with($url, '/') && ! str_starts_with($url, '#')) return null;
        return substr($url, 0, 2048);
    }
}
