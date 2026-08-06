<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Turns scbd.com news HTML into arrays.
 *
 * Kept apart from the import command so the parsing — which is all of the
 * fiddly, breakable logic — can be tested against saved pages with no network
 * and no database.
 *
 * Every method returns empty rather than throwing on markup it does not
 * recognise: the source is somebody else's site and may change without notice,
 * and an import that skips a post it cannot read beats one that dies partway.
 */
final class ScbdNewsParser
{
    private const BASE = 'https://scbd.com';

    /**
     * One listing page's posts.
     *
     * @return array<int, array{title: string, date: string, cover: ?string, url: string}>
     */
    public static function listing(string $html): array
    {
        $xpath = self::xpath($html);
        $posts = [];

        foreach ($xpath->query("//div[contains(@class, 'niche-box-post')]") as $box) {
            /** @var DOMElement $box */
            $link = $xpath->query(".//h2/a", $box)->item(0);

            if (! $link instanceof DOMElement) {
                continue;
            }

            $day = $xpath->query(".//p[contains(@class, 'bd-day')]", $box)->item(0)?->textContent;
            $month = $xpath->query(".//p[contains(@class, 'bd-month')]", $box)->item(0)?->textContent;

            $posts[] = [
                'title' => self::text($link->textContent),
                'date' => self::date($day, $month),
                'cover' => self::cover($xpath, $box),
                'url' => self::absolute($link->getAttribute('href')),
            ];
        }

        return $posts;
    }

    /**
     * A post's body and its images.
     *
     * @return array{body: string, images: array<int, string>}
     */
    public static function detail(string $html): array
    {
        $xpath = self::xpath($html);
        // The real markup has no "niche-box-content" wrapper; the story's
        // paragraphs sit directly in the main nine-column area.
        $content = $xpath->query("//div[contains(@class, 'col-md-9')]")->item(0);

        if (! $content instanceof DOMElement) {
            return ['body' => '', 'images' => []];
        }

        $paragraphs = [];

        foreach ($xpath->query('.//p', $content) as $p) {
            /** @var DOMElement $p */
            $class = $p->getAttribute('class');

            // The story header's day/month badge is a <p> too, carrying the
            // same "bd-day"/"bd-month" classes the listing cards use for the
            // same purpose — it is not part of the story text.
            if (str_contains($class, 'bd-day') || str_contains($class, 'bd-month')) {
                continue;
            }

            $text = self::text($p->textContent);

            if ($text !== '') {
                $paragraphs[] = '<p>'.e($text).'</p>';
            }
        }

        $images = [];

        // Scoped to the story container, not the document: an unscoped //img
        // collects every matching image on the page, including any a hostile
        // or compromised source injected outside the article.
        foreach ($xpath->query(".//img[contains(@src, '/news/images/')]", $content) as $img) {
            /** @var DOMElement $img */
            $images[] = self::absolute($img->getAttribute('src'));
        }

        return [
            // Rebuilt from text, never passed through. The scraped markup is
            // stored and later rendered with {!! !!}, so nothing that arrives
            // as a tag survives — no script, no style, no event attribute.
            'body' => implode('', $paragraphs),
            'images' => array_values(array_unique($images)),
        ];
    }

    private static function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;

        // The source is not well-formed; warnings here are expected and
        // uninteresting.
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private static function text(string $raw): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private static function date(?string $day, ?string $month): string
    {
        try {
            return Carbon::parse(trim(($day ?? '').' '.($month ?? '')))->format('Y-m-d');
        } catch (Throwable) {
            // An unreadable date must not cost us the post. Today is wrong but
            // recoverable by hand; a dead import is not.
            return now()->format('Y-m-d');
        }
    }

    private static function cover(DOMXPath $xpath, DOMElement $box): ?string
    {
        foreach ($xpath->query('.//div[@style]', $box) as $div) {
            /** @var DOMElement $div */
            // Filenames in the source can themselves contain parentheses
            // (uploads re-saved under the same name get a "-(1)" suffix), so
            // the URL is captured by its matching quote delimiter rather than
            // by stopping at the first closing paren — the naive version
            // truncates "...-(1).jpeg-1774519231.jpg" down to "...-(1".
            if (preg_match("#background-image:\s*url\((['\"])(.*?)\\1\)#", $div->getAttribute('style'), $matches)) {
                return self::absolute($matches[2]);
            }
        }

        return null;
    }

    private static function absolute(string $url): string
    {
        return str_starts_with($url, 'http') ? $url : self::BASE.'/'.ltrim($url, '/');
    }
}
