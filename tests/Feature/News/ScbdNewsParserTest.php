<?php

namespace Tests\Feature\News;

use App\Support\ScbdNewsParser;
use Tests\TestCase;

class ScbdNewsParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/scbd-news/{$name}.html"));
    }

    public function test_it_finds_every_post_on_a_listing_page(): void
    {
        // The source paginates four to a page.
        $posts = ScbdNewsParser::listing($this->fixture('listing-page-1'));

        $this->assertCount(4, $posts);
    }

    public function test_it_reads_a_posts_title_date_cover_and_url(): void
    {
        $posts = ScbdNewsParser::listing($this->fixture('listing-page-1'));
        $first = $posts[0];

        $this->assertStringContainsString("National Children's Day", $first['title']);
        $this->assertSame('2026-07-23', $first['date']);
        $this->assertStringStartsWith('https://scbd.com/', $first['cover']);
        $this->assertStringStartsWith('https://scbd.com/menu/detail/news/', $first['url']);
    }

    public function test_titles_come_back_decoded(): void
    {
        // The source emits &#039; for apostrophes; storing that literally would
        // render as &amp;#039; once Blade escapes it.
        $posts = ScbdNewsParser::listing($this->fixture('listing-page-1'));

        $this->assertStringNotContainsString('&#039;', $posts[0]['title']);
        $this->assertStringNotContainsString('&amp;', $posts[0]['title']);
    }

    public function test_it_extracts_a_detail_body_and_its_images(): void
    {
        $detail = ScbdNewsParser::detail($this->fixture('detail-earth-hour'));

        $this->assertStringContainsString('<p>', $detail['body']);
        $this->assertNotEmpty($detail['images']);

        foreach ($detail['images'] as $image) {
            $this->assertStringStartsWith('http', $image);
        }
    }

    public function test_the_body_carries_no_script_or_style(): void
    {
        // Whatever is scraped is stored and later rendered with {!! !!}.
        $detail = ScbdNewsParser::detail($this->fixture('detail-earth-hour'));

        $this->assertStringNotContainsString('<script', $detail['body']);
        $this->assertStringNotContainsString('<style', $detail['body']);
        $this->assertStringNotContainsString('onerror=', $detail['body']);
    }

    public function test_malformed_html_yields_nothing_rather_than_throwing(): void
    {
        $this->assertSame([], ScbdNewsParser::listing('<html><body>nothing here</body></html>'));
        $this->assertSame('', ScbdNewsParser::detail('<html><body></body></html>')['body']);
    }
}
