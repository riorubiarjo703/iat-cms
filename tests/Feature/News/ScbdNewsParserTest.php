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
        // Deliberately NOT the saved fixture: the fixture contains no hostile
        // markup, so a pass-through implementation satisfies these assertions
        // from the input rather than from the code. The source is somebody
        // else's site and what it returns is stored and later rendered with
        // {!! !!}, so the test has to hand detail() the markup a compromised
        // source would actually serve.
        $hostile = <<<'HTML'
            <html><body><div class="col-md-9">
                <p>Opening paragraph.</p>
                <script>alert('xss')</script>
                <style>body{display:none}</style>
                <p><img src="x" onerror="alert('img')"> caption</p>
                <p><a href="javascript:alert('link')">click me</a></p>
                <p>Closing paragraph.</p>
            </div></body></html>
            HTML;

        $body = ScbdNewsParser::detail($hostile)['body'];

        // Nothing that arrived as a tag or an attribute may survive as one.
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('</script', $body);
        $this->assertStringNotContainsString('<style', $body);
        $this->assertStringNotContainsString('<img', $body);
        $this->assertStringNotContainsString('<a ', $body);
        $this->assertStringNotContainsString('onerror=', $body);
        $this->assertStringNotContainsString('href=', $body);
        $this->assertStringNotContainsString('javascript:alert', $body);

        // The prose the story actually carries is still there.
        $this->assertStringContainsString('Opening paragraph.', $body);
        $this->assertStringContainsString('Closing paragraph.', $body);

        // <script> and <style> are element content, so their text is dropped
        // entirely rather than escaped — only the anchor's visible text
        // survives, and where markup-ish text does survive it is escaped.
        $this->assertStringNotContainsString("alert('xss')", $body);
        $this->assertStringNotContainsString('display:none', $body);

        $escaped = ScbdNewsParser::detail(
            '<html><body><div class="col-md-9"><p>a &lt;script&gt;alert(1)&lt;/script&gt; b</p></div></body></html>'
        )['body'];

        $this->assertStringContainsString('&lt;script&gt;', $escaped);
        $this->assertStringNotContainsString('<script>', $escaped);
    }

    public function test_images_outside_the_story_container_are_ignored(): void
    {
        // An unscoped //img collects every matching image on the page,
        // including one injected into a footer or a sidebar the story does not
        // own — and the importer then fetches and stores it.
        $html = <<<'HTML'
            <html><body>
                <div class="col-md-9"><p>Story.</p><img src="/news/images/real.jpg"></div>
                <footer><img src="/news/images/injected.jpg"></footer>
            </body></html>
            HTML;

        $images = ScbdNewsParser::detail($html)['images'];

        $this->assertSame(['https://scbd.com/news/images/real.jpg'], $images);
    }

    public function test_malformed_html_yields_nothing_rather_than_throwing(): void
    {
        $this->assertSame([], ScbdNewsParser::listing('<html><body>nothing here</body></html>'));
        $this->assertSame('', ScbdNewsParser::detail('<html><body></body></html>')['body']);
    }
}
