<?php

namespace Tests\Unit;

use App\Support\RichText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
    public function test_it_keeps_the_formatting_the_editor_offers(): void
    {
        $html = '<h2>Title</h2><p>Some <strong>bold</strong>, <em>italic</em>, <u>underlined</u> and <s>struck</s> text.</p>'
            .'<ul><li>One</li><li>Two</li></ul><ol><li>First</li></ol><blockquote>Quote</blockquote>';

        $this->assertSame($html, RichText::clean($html));
    }

    public static function attacks(): array
    {
        return [
            'script tag' => ['<p>Hi</p><script>alert(1)</script>', '<p>Hi</p>'],
            'script in text' => ['<p>a<script>alert(1)</script>b</p>', '<p>ab</p>'],
            'event handler' => ['<p onclick="alert(1)">Hi</p>', '<p>Hi</p>'],
            'image onerror' => ['<p>x</p><img src=x onerror=alert(1)>', '<p>x</p>'],
            'iframe' => ['<iframe src="https://evil"></iframe><p>ok</p>', '<p>ok</p>'],
            'style attribute' => ['<p style="position:fixed;top:0">Hi</p>', '<p>Hi</p>'],
            'style tag' => ['<style>body{display:none}</style><p>Hi</p>', '<p>Hi</p>'],
            'class and id' => ['<p class="ql-align-center" id="x">Hi</p>', '<p>Hi</p>'],
            'svg' => ['<svg onload=alert(1)><circle/></svg><p>ok</p>', '<p>ok</p>'],
            'form' => ['<form action="https://evil"><input name="pw"><button>Go</button></form><p>ok</p>', '<p>ok</p>'],
            'object embed' => ['<object data="x"></object><embed src="x"><p>ok</p>', '<p>ok</p>'],
            'html comment' => ['<p>a<!-- <script>alert(1)</script> -->b</p>', '<p>ab</p>'],
            // What is left is harmless text: no tag survives, and the "<" and ">" are escaped.
            'broken nesting' => ['<scr<script>ipt>alert(1)</scr</script>ipt><p>ok</p>', 'ipt&gt;alert(1)ipt&gt;<p>ok</p>'],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil"><p>ok</p>', '<p>ok</p>'],
        ];
    }

    #[DataProvider('attacks')]
    public function test_attacks_are_removed(string $dirty, string $expected): void
    {
        $out = RichText::clean($dirty);

        $this->assertStringNotContainsStringIgnoringCase('<script', $out);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $out);
        $this->assertStringNotContainsStringIgnoringCase('onload', $out);
        $this->assertSame($expected, $out);
    }

    public static function badLinks(): array
    {
        return [
            ['javascript:alert(1)'], ['JaVaScRiPt:alert(1)'], [' javascript:alert(1)'], ["java\tscript:alert(1)"], ["java\nscript:alert(1)"],
            ['java&#x73;cript:alert(1)'], ['&#106;avascript:alert(1)'], ['data:text/html,<script>alert(1)</script>'], ['vbscript:msgbox(1)'],
            ['//evil.example/x'], ['file:///etc/passwd'], [''], ['#'],
        ];
    }

    #[DataProvider('badLinks')]
    public function test_dangerous_links_lose_the_link_but_keep_the_words(string $href): void
    {
        $out = RichText::clean('<p>Click <a href="'.htmlspecialchars($href, ENT_QUOTES).'">here</a> now</p>');

        $this->assertSame('<p>Click here now</p>', $out, $href);
    }

    public function test_safe_links_are_kept_and_made_safe_to_open(): void
    {
        $out = RichText::clean('<p><a href="https://example.com/a?b=1&c=2" onclick="x()">Site</a> <a href="mailto:hi@x.com">Mail</a> <a href="/plans">Plans</a></p>');

        $this->assertStringContainsString('href="https://example.com/a?b=1&amp;c=2"', $out);
        $this->assertStringContainsString('target="_blank"', $out);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $out);
        $this->assertStringContainsString('href="mailto:hi@x.com"', $out);
        $this->assertStringContainsString('href="/plans"', $out);
        $this->assertStringNotContainsString('onclick', $out);
    }

    public function test_layout_tags_are_unwrapped_and_old_tags_are_renamed(): void
    {
        $this->assertSame('<p>Hello <strong>there</strong></p>', RichText::clean('<p><span style="color:red">Hello <b>there</b></span></p>'));
        $this->assertSame('<h2>Big</h2><p><em>x</em></p>', RichText::clean('<h1>Big</h1><p><i>x</i></p>'));
        $this->assertSame('Plain div text', RichText::clean('<div>Plain <font color="red">div</font> text</div>'));
    }

    public function test_quill_two_bullet_lists_become_real_bullet_lists(): void
    {
        $this->assertSame('<ul><li>a</li><li>b</li></ul>', RichText::clean('<ol><li data-list="bullet"><span class="ql-ui" contenteditable="false"></span>a</li><li data-list="bullet">b</li></ol>'));
        $this->assertSame('<ol><li>a</li></ol>', RichText::clean('<ol><li data-list="ordered">a</li></ol>'));
    }

    public function test_an_empty_editor_gives_an_empty_string(): void
    {
        foreach ([null, '', '   ', '<p><br></p>', '<p></p>', '<p> </p><p><br></p>', '<p>&nbsp;</p>'] as $empty) {
            $this->assertSame('', RichText::clean($empty), var_export($empty, true));
        }
        $this->assertSame('<p>Hi</p>', RichText::clean('<p>Hi</p><p><br></p><p><br></p>'), 'Empty paragraphs at the end are trimmed');
    }

    public function test_cleaning_twice_changes_nothing_and_unicode_survives(): void
    {
        $dirty = '<p>कीमत ₹2,240 — <strong>अभी खरीदें</strong> <script>x</script></p><ul><li>ऑफर</li></ul>';
        $once = RichText::clean($dirty);

        $this->assertSame($once, RichText::clean($once));
        $this->assertStringContainsString('₹2,240', $once);
        $this->assertStringContainsString('अभी खरीदें', $once);
        $this->assertStringNotContainsString('script', $once);
    }

    public function test_broken_or_huge_input_never_throws(): void
    {
        foreach (['<p><strong>unclosed', '</p></div></b>', '<<<>>>', '<p>'.str_repeat('<b>', 500).'x', str_repeat('<p>a</p>', 5000), "\x00\x01<p>x</p>", '<a href=>x</a>'] as $junk) {
            $this->assertIsString(RichText::clean($junk));
        }
    }

    public function test_plain_text_and_length_ignore_the_tags(): void
    {
        $this->assertSame('Hello big world One Two', RichText::plain('<p>Hello <strong>big</strong> world</p><ul><li>One</li><li>Two</li></ul>'));
        $this->assertSame('5 < 6 & 7 > 3', RichText::plain('<p>5 &lt; 6 &amp; 7 &gt; 3</p>'));
        $this->assertSame(11, RichText::length('<p><strong>Hello</strong> <em>world</em></p>'));
        $this->assertSame(0, RichText::length('<p><br></p>'));
    }

    public function test_text_written_before_the_editor_is_shown_as_safe_paragraphs(): void
    {
        $out = (string) RichText::render("First line\nsecond line\n\nNew paragraph with <b>no</b>-tags? 5 < 6");

        // It contains a tag, so it is treated as HTML: cleaned, not escaped away.
        $this->assertStringContainsString('<strong>no</strong>', $out);

        $plain = (string) RichText::render("Line one\nline two\n\nSecond & more < 5");
        $this->assertSame('<p>Line one<br />'."\n".'line two</p><p>Second &amp; more &lt; 5</p>', $plain);
        $this->assertSame('', (string) RichText::render(null));
        $this->assertSame('', (string) RichText::render("  \n "));
    }

    public function test_for_the_editor_returns_safe_html(): void
    {
        $this->assertSame('<p>Old text</p>', RichText::forEditor('Old text'));
        $this->assertSame('<p>Hi</p>', RichText::forEditor('<p>Hi</p><script>x</script>'));
    }
}
