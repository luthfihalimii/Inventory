<?php

namespace Tests\Unit;

use App\Support\AnnouncementHtmlSanitizer;
use Tests\TestCase;

class AnnouncementHtmlSanitizerTest extends TestCase
{
    public function test_it_removes_script_and_event_handler_attributes(): void
    {
        $clean = AnnouncementHtmlSanitizer::clean('<h1 onclick="alert(1)">Judul</h1><script>alert(1)</script><p onmouseover="x()">Isi</p>');
        $this->assertStringContainsString('<h1>Judul</h1>', $clean);
        $this->assertStringContainsString('<p>Isi</p>', $clean);
        $this->assertStringNotContainsString('script', strtolower($clean));
        $this->assertStringNotContainsString('onclick', strtolower($clean));
        $this->assertStringNotContainsString('onmouseover', strtolower($clean));
    }

    public function test_it_rejects_dangerous_links_and_keeps_https_links(): void
    {
        $clean = AnnouncementHtmlSanitizer::clean('<a href="javascript:alert(1)">buruk</a><a href="https://example.com">aman</a>');
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('rel="nofollow noopener noreferrer"', $clean);
    }

    public function test_it_preserves_safe_quill_list_metadata_and_internal_uploaded_images(): void
    {
        $clean = AnnouncementHtmlSanitizer::clean('<ol><li data-list="bullet" class="ql-indent-2">Item</li></ol><img src="https://localhost/announcements/images/a123.png" onerror="x()">');
        $this->assertStringContainsString('data-list="bullet"', $clean);
        $this->assertStringContainsString('ql-indent-2', $clean);
        $this->assertStringContainsString('src="/announcements/images/a123.png"', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
    }
}
