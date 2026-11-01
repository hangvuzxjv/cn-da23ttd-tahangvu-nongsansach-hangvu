<?php

declare(strict_types=1);

final class StaticPageService
{
    public static function sanitizeHtml(string $html): string
    {
        if (!class_exists(DOMDocument::class)) {
            throw new RuntimeException('Máy chủ chưa bật thư viện lọc nội dung HTML.');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="cms-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new InvalidArgumentException('Nội dung trang không hợp lệ.');
        }
        $root = null;
        foreach ($document->getElementsByTagName('div') as $element) {
            if ($element->getAttribute('id') === 'cms-root') {
                $root = $element;
                break;
            }
        }
        if (!$root) {
            throw new InvalidArgumentException('Nội dung trang không hợp lệ.');
        }
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= self::sanitizeNode($child);
        }
        return $output;
    }

    public static function publishedSlugs(): array
    {
        return [
            'gioi-thieu', 'chinh-sach-doi-tra', 'chinh-sach-van-chuyen',
            'chinh-sach-bao-mat', 'dieu-khoan-su-dung', 'cau-hoi-thuong-gap',
        ];
    }

    private static function sanitizeNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (!$node instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'a'];
        if (!in_array($tag, $allowed, true)) {
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg', 'math', 'template'], true)) {
                return '';
            }
            $children = '';
            foreach ($node->childNodes as $child) {
                $children .= self::sanitizeNode($child);
            }
            return $children;
        }
        if ($tag === 'br') {
            return '<br>';
        }
        $attributes = '';
        if ($tag === 'a') {
            $href = trim($node->getAttribute('href'));
            $safeHref = preg_match('/\A(?:https?:\/\/|mailto:|\/(?!\/))/i', $href) === 1;
            if ($safeHref) {
                $attributes = ' href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="nofollow noopener"';
            }
        }
        $children = '';
        foreach ($node->childNodes as $child) {
            $children .= self::sanitizeNode($child);
        }
        return '<' . $tag . $attributes . '>' . $children . '</' . $tag . '>';
    }
}
