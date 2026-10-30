<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\HtmlString;

/**
 * Descriptions are written in a rich-text editor, so they arrive as HTML from a browser: never trust it. clean() keeps
 * only a short list of formatting tags, drops every attribute (except a safe link address) and removes scripts, frames,
 * images, styles and the like with their content. It runs when something is SAVED and again before it is SHOWN or
 * EMAILED, so old rows and anything that slipped past are covered too. Text written before the editor existed (no tags)
 * is shown as plain paragraphs.
 */
class RichText
{
    private const ALLOWED = ['p', 'br', 'strong', 'em', 'u', 's', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'a'];

    private const RENAME = ['b' => 'strong', 'i' => 'em', 'strike' => 's', 'del' => 's', 'h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4'];

    /** Removed together with everything inside them. */
    private const DROP = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button', 'select', 'textarea',
        'svg', 'math', 'link', 'meta', 'base', 'img', 'picture', 'video', 'audio', 'source', 'canvas', 'template', 'noscript', 'title', 'head'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        // Tags the HTML parser does not know are self-closing would swallow the text after them: drop them first.
        $html = preg_replace('#<(embed|source|track|param|keygen|wbr)\b[^>]*>#i', '', $html);

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8"><body><div id="rt-root">'.$html.'</div></body>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('rt-root');
        if (! $root) {
            return '';
        }

        self::walk($root, $doc);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        // Empty paragraphs the editor leaves behind at the end, then anything that is only whitespace.
        $out = trim(preg_replace('#(?:\s|<p>(?:\s|<br>)*</p>)+$#', '', trim($out)));

        return self::plain($out) === '' && ! str_contains($out, '<br') && ! preg_match('#<(ul|ol|blockquote)#', $out) ? '' : $out;
    }

    private static function walk(DOMNode $node, DOMDocument $doc): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $name = strtolower($child->nodeName);
                $name = self::RENAME[$name] ?? $name;

                if (in_array($name, self::DROP, true)) {
                    $node->removeChild($child);

                    continue;
                }

                // Looked at before the children are cleaned, because cleaning removes the data-list marker.
                $bullet = $name === 'ol' && self::hasBulletItems($child);

                self::walk($child, $doc);

                if (! in_array($name, self::ALLOWED, true)) {
                    // An unknown or layout tag: keep its text and children, lose the tag.
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }

                $href = $name === 'a' ? self::safeHref($child->getAttribute('href')) : null;

                if ($child->nodeName !== $name || $bullet) {
                    $new = $doc->createElement($bullet ? 'ul' : $name);
                    while ($child->firstChild) {
                        $new->appendChild($child->firstChild);
                    }
                    $node->replaceChild($new, $child);
                    $child = $new;
                }

                // Every attribute goes; a link keeps only its checked address.
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $child->removeAttributeNode($attr);
                }
                if ($name === 'a') {
                    if ($href === null) {
                        while ($child->firstChild) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                    } else {
                        $child->setAttribute('href', $href);
                        $child->setAttribute('target', '_blank');
                        $child->setAttribute('rel', 'noopener noreferrer nofollow');
                    }
                }
            } elseif ($child->nodeType !== XML_TEXT_NODE) {
                $node->removeChild($child);     // comments, processing instructions, CDATA
            }
        }
    }

    private static function hasBulletItems(DOMElement $list): bool
    {
        foreach ($list->childNodes as $li) {
            if ($li instanceof DOMElement && strtolower($li->nodeName) === 'li' && $li->getAttribute('data-list') === 'bullet') {
                return true;
            }
        }

        return false;
    }

    /** Only web, mail and phone links (and site-relative ones) survive. Everything else, however it is disguised, does not. */
    private static function safeHref(string $href): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $compact = preg_replace('/[\x00-\x20\x7F]+/', '', $href);   // "java\tscript:" and similar tricks

        if ($compact === '') {
            return null;
        }
        if (preg_match('#^(https?:|mailto:|tel:)#i', $compact) || preg_match('#^/(?!/)#', $compact)) {
            return $compact;
        }

        return null;
    }

    /** The text only, with a space where a block ended: for snippets, search and length checks. */
    public static function plain(?string $html): string
    {
        $text = preg_replace('#<\s*/?\s*(p|br|li|ul|ol|h[1-6]|blockquote|div)\b[^>]*>#i', ' ', (string) $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }

    public static function length(?string $html): int
    {
        return mb_strlen(self::plain($html));
    }

    /** A short plain-text version, for cards, lists and tables where formatting would not fit. */
    public static function snippet(?string $value, int $limit = 160): string
    {
        return \Illuminate\Support\Str::limit(self::plain((string) self::render($value)), $limit);
    }

    /** Validation rule: at most $max characters of text (tags do not count). */
    public static function maxText(int $max): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($max) {
            if (is_string($value) && self::length(self::clean($value)) > $max) {
                $fail('Keep this under '.number_format($max).' characters of text.');
            }
        };
    }

    /** Validation rule: there must be some text once it is cleaned (an empty editor is "<p><br></p>", which is not empty to "required"). */
    public static function hasText(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (! is_string($value) || self::length(self::clean($value)) === 0) {
                $fail('Write something here first.');
            }
        };
    }

    /** Safe HTML ready to print with {!! !!}. Old plain-text values (no tags) become paragraphs. */
    public static function render(?string $value): HtmlString
    {
        $value = trim((string) $value);

        if ($value !== '' && $value === strip_tags($value)) {
            $paragraphs = preg_split('/\R{2,}/', $value);

            return new HtmlString(implode('', array_map(fn ($p) => '<p>'.nl2br(e(trim($p))).'</p>', array_filter($paragraphs, fn ($p) => trim($p) !== ''))));
        }

        return new HtmlString(self::clean($value));
    }

    /** What the editor should start with: the stored value as safe HTML (old plain text becomes paragraphs). */
    public static function forEditor(?string $value): string
    {
        return (string) self::render($value);
    }
}
