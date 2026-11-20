<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The home page hero plays a YouTube video behind the headline. How it behaves in a real browser
 * (video playing, blocked, erroring) is checked in Chrome; these tests pin the two things in the
 * markup that make it hold up for viewers whose browser or network gets in the way.
 */
class HomeHeroTest extends TestCase
{
    use RefreshDatabase;

    private function homeHtml(): string
    {
        return $this->get(route('home'))->assertOk()->getContent();
    }

    /**
     * Browsers and extensions that hide the referrer (Brave Shields, privacy modes) made YouTube answer
     * "Error 153 - Video player configuration error" instead of playing. Sending the site's origin
     * (never the full address) is what YouTube asks embeds to do.
     */
    public function test_hero_video_frame_sends_the_sites_origin_as_referrer(): void
    {
        preg_match('/<iframe\b[^>]*\bid="hero-yt-player"[^>]*>/s', $this->homeHtml(), $frame);

        $this->assertNotEmpty($frame, 'The hero should have the YouTube frame #hero-yt-player.');
        $this->assertStringContainsString('referrerpolicy="strict-origin-when-cross-origin"', $frame[0]);
    }

    /**
     * The video stays invisible until YouTube reports ready, so when YouTube is blocked or errors the
     * hero used to be an empty dark box. A still image under the video is what shows instead.
     */
    public function test_hero_has_a_still_image_under_the_video(): void
    {
        $html = $this->homeHtml();

        preg_match('/<img\b[^>]*\bid="hero-fallback"[^>]*>/s', $html, $img);
        $this->assertNotEmpty($img, 'The hero should have a still image #hero-fallback.');

        preg_match('/\bsrc="([^"]+)"/', $img[0], $src);
        $path = public_path(ltrim(parse_url($src[1], PHP_URL_PATH), '/'));
        $this->assertFileExists($path, 'The still image file must exist where the page points to it.');

        // The video layer is the element whose class list has hero-video-mask (not the CSS rule of that name).
        preg_match('/class="[^"]*\bhero-video-mask\b[^"]*"/', $html, $layer, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($layer, 'The hero should have the video layer .hero-video-mask.');

        $this->assertLessThan(
            $layer[0][1],
            strpos($html, 'id="hero-fallback"'),
            'The still image must come before the video layer, so it sits underneath it.'
        );
    }
}
